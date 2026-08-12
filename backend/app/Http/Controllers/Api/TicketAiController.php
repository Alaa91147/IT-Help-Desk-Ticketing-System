<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Priority;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AiTicketService;
use App\Services\TicketActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketAiController extends Controller
{
    public function __construct(
        private readonly AiTicketService $aiService,
        private readonly TicketActivityService $activityService
    ) {
    }

    /**
     * Generate and save a classification suggestion.
     */
    public function triage(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        if (! $this->canView($user, $ticket)) {
            return $this->forbidden(
                'You cannot access this ticket.'
            );
        }

        $result =
            $this->aiService->categorizeAndPrioritize(
                $ticket->subject,
                $ticket->description
            );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => $result,
            ], 422);
        }

        $ticket->update([
            'ai_category_id' =>
                $result['category']['id'],

            'ai_priority_id' =>
                $result['priority']['id'],

            'ai_metadata' => [
                'confidence' =>
                    $result['confidence'],

                'categoryConfidence' =>
                    $result['categoryConfidence'],

                'priorityConfidence' =>
                    $result['priorityConfidence'],

                'reason' =>
                    $result['reason'],

                'matchedIndicators' =>
                    $result['matchedIndicators'],

                'engine' =>
                    $result['metadata']['engine'],

                'generatedAt' =>
                    $result['metadata']['generatedAt'],

                'generatedByUserId' =>
                    $user->id,
            ],
        ]);

        $this->activityService->record(
            $ticket,
            $user,
            'ai_classification_generated',
            'An automated category and priority '
                .'suggestion was generated.',
            null,
            [
                'categoryId' =>
                    $result['category']['id'],

                'categoryName' =>
                    $result['category']['name'],

                'priorityId' =>
                    $result['priority']['id'],

                'priorityName' =>
                    $result['priority']['name'],

                'confidence' =>
                    $result['confidence'],
            ],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Classification suggestion generated.',

            'data' => [
                ...$result,

                'canApply' => in_array(
                    $user->role?->roleName,
                    ['Admin', 'Manager'],
                    true
                ),
            ],
        ]);
    }

    /**
     * Admin or Manager: apply a saved AI suggestion.
     */
    public function apply(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        if (! in_array(
            $user->role?->roleName,
            ['Admin', 'Manager'],
            true
        )) {
            return $this->forbidden(
                'Only Admins and Managers can apply '
                    .'classification suggestions.'
            );
        }

        $ticket->loadMissing([
            'status',
            'category',
            'priority',
        ]);

        if (in_array(
            $ticket->status?->statusName,
            ['Closed', 'Cancelled'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Closed or cancelled tickets cannot '
                    .'be reclassified.',
            ], 422);
        }

        if (
            ! $ticket->ai_category_id ||
            ! $ticket->ai_priority_id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Generate an automated suggestion first.',
            ], 409);
        }

        $priority = Priority::query()
            ->whereKey($ticket->ai_priority_id)
            ->where('isActive', true)
            ->first();

        if (! $priority) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The suggested priority is no longer available.',
            ], 422);
        }

        $categoryExists = DB::table('categories')
            ->where('id', $ticket->ai_category_id)
            ->where('isActive', true)
            ->exists();

        if (! $categoryExists) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The suggested category is no longer available.',
            ], 422);
        }

        $oldValues = [
            'categoryId' => $ticket->categoryId,
            'categoryName' =>
                $ticket->category?->categoryName,

            'priorityId' => $ticket->priorityId,
            'priorityName' =>
                $ticket->priority?->priorityName,

            'dueAt' =>
                $ticket->dueAt?->toISOString(),
        ];

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $priority,
            $oldValues
        ): void {
            $ticket->update([
                'categoryId' =>
                    $ticket->ai_category_id,

                'priorityId' =>
                    $ticket->ai_priority_id,

                'dueAt' =>
                    $this->calculateDueAt(
                        $priority,
                        $ticket->createdAt
                    ),
            ]);

            $ticket->refresh();
            $ticket->loadMissing([
                'category',
                'priority',
            ]);

            $this->activityService->record(
                $ticket,
                $user,
                'ai_classification_applied',
                'The automated category and priority '
                    .'suggestion was applied.',
                $oldValues,
                [
                    'categoryId' =>
                        $ticket->categoryId,

                    'categoryName' =>
                        $ticket->category?->categoryName,

                    'priorityId' =>
                        $ticket->priorityId,

                    'priorityName' =>
                        $ticket->priority?->priorityName,

                    'dueAt' =>
                        $ticket->dueAt?->toISOString(),
                ],
                $request->ip()
            );
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Classification suggestion applied successfully.',

            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'activityLogs.user',
            ]),
        ]);
    }

    private function canView(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($user->role?->roleName) {
            'Admin',
            'Manager',
            'SupportAgent' => true,

            'User' =>
                (int) $ticket->userId ===
                (int) $user->id,

            default => false,
        };
    }

    private function calculateDueAt(
        Priority $priority,
        mixed $startingAt = null
    ): mixed {
        $dueAt = $startingAt
            ? $startingAt->copy()
            : now();

        return match (
            mb_strtolower(
                $priority->priorityName
            )
        ) {
            'urgent',
            'critical' =>
                $dueAt->addHours(4),

            'high' =>
                $dueAt->addDay(),

            'medium' =>
                $dueAt->addDays(3),

            'low' =>
                $dueAt->addDays(5),

            default =>
                $dueAt->addDays(3),
        };
    }
/**
 * Generate a classification before creating a ticket.
 */
public function preview(
    Request $request
): JsonResponse {
    /** @var User $user */
    $user = $request->user();
    $user->loadMissing('role');

    if (! in_array(
        $user->role?->roleName,
        [
            'Admin',
            'Manager',
            'SupportAgent',
            'User',
        ],
        true
    )) {
        return $this->forbidden(
            'You cannot use automated classification.'
        );
    }

    $validated = $request->validate([
        'subject' => [
            'required',
            'string',
            'min:3',
            'max:255',
        ],

        'description' => [
            'required',
            'string',
            'min:5',
            'max:10000',
        ],
    ]);

    $result =
        $this->aiService->categorizeAndPrioritize(
            $validated['subject'],
            $validated['description']
        );

    if (! $result['success']) {
        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'data' => $result,
        ], 422);
    }

    return response()->json([
        'success' => true,
        'message' =>
            'Classification suggestion generated.',

        'data' => [
            ...$result,

            // A ticket does not exist yet, so this
            // suggestion is applied through the form.
            'canApply' => true,
        ],
    ]);
}
    private function forbidden(
        string $message
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }
    
}