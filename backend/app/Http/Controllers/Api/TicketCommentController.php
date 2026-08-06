<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketActivityService;
use App\Services\TicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketCommentController extends Controller
{
    public function __construct(
        private readonly TicketActivityService $activityService,
        private readonly TicketNotificationService $notificationService
    ) {
    }

    private function roleName(User $user): ?string
    {
        return $user
            ->loadMissing('role')
            ->role?->roleName;
    }

    private function canView(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            'Admin', 'Manager', 'SupportAgent' => true,
            'User' =>
                (int) $ticket->userId
                === (int) $user->id,
            default => false,
        };
    }

    private function canParticipate(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            'Admin', 'Manager' => true,
            'SupportAgent' =>
                (int) $ticket->assignedUserId
                === (int) $user->id,
            'User' =>
                (int) $ticket->userId
                === (int) $user->id,
            default => false,
        };
    }

    private function canViewInternalNotes(
        User $user
    ): bool {
        return in_array(
            $this->roleName($user),
            ['Admin', 'Manager', 'SupportAgent'],
            true
        );
    }

    private function canCreateInternalNotes(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            'Admin', 'Manager' => true,
            'SupportAgent' =>
                (int) $ticket->assignedUserId
                === (int) $user->id,
            default => false,
        };
    }

    private function forbidden(
        string $message
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    private function terminalTicketResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Comments on a Closed or Cancelled ticket '
                . 'cannot be changed.',
        ], 422);
    }

    private function isTerminal(Ticket $ticket): bool
    {
        $ticket->loadMissing('status');

        return in_array(
            $ticket->status?->statusName,
            ['Closed', 'Cancelled'],
            true
        );
    }

    public function index(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden(
                'You cannot view comments on this ticket.'
            );
        }

        $canViewInternal =
            $this->canViewInternalNotes($user);

        $comments = $ticket->comments()
            ->whereNull('parentCommentId')
            ->with([
                'user.role',
                'attachments.uploadedBy.role',
                'replies' => function ($query) use (
                    $canViewInternal
                ): void {
                    $query
                        ->when(
                            !$canViewInternal,
                            fn ($replyQuery) =>
                                $replyQuery->where(
                                    'isInternal',
                                    false
                                )
                        )
                        ->with([
                            'user.role',
                            'attachments.uploadedBy.role',
                        ])
                        ->orderBy('createdAt');
                },
            ])
            ->when(
                !$canViewInternal,
                fn ($query) =>
                    $query->where(
                        'isInternal',
                        false
                    )
            )
            ->orderBy('createdAt')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    public function store(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canParticipate($user, $ticket)) {
            return $this->forbidden(
                'You cannot comment on this ticket.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return $this->terminalTicketResponse();
        }

        $validated = $request->validate([
            'comment' => [
                'required',
                'string',
                'max:5000',
            ],
            'isInternal' => [
                'sometimes',
                'boolean',
            ],
            'parentCommentId' => [
                'nullable',
                'integer',
                'exists:ticketcomments,id',
            ],
        ]);

        $parent = null;

        if (!empty($validated['parentCommentId'])) {
            $parent = TicketComment::query()
                ->findOrFail(
                    $validated['parentCommentId']
                );

            if (
                (int) $parent->ticketId
                !== (int) $ticket->id
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'The parent comment does not belong '
                        . 'to this ticket.',
                ], 422);
            }

            if ($parent->parentCommentId !== null) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Replies can only be added to a '
                        . 'top-level comment.',
                ], 422);
            }

            if (
                $parent->isInternal
                && !$this->canViewInternalNotes($user)
            ) {
                return $this->forbidden(
                    'You cannot reply to an internal note.'
                );
            }
        }

        $requestedInternal = (bool) (
            $validated['isInternal'] ?? false
        );

        $isInternal = $parent?->isInternal
            ?? (
                $this->canCreateInternalNotes(
                    $user,
                    $ticket
                )
                    ? $requestedInternal
                    : false
            );

        $comment = $ticket->comments()->create([
            'userId' => $user->id,
            'comment' => $validated['comment'],
            'isInternal' => $isInternal,
            'parentCommentId' => $parent?->id,
        ]);

        $this->activityService->record(
            $ticket,
            $user,
            $isInternal
                ? 'internal_note_added'
                : (
                    $parent
                        ? 'comment_reply_added'
                        : 'comment_added'
                ),
            $isInternal
                ? 'An internal note was added.'
                : (
                    $parent
                        ? 'A reply was added to a ticket comment.'
                        : 'A public ticket comment was added.'
                ),
            null,
            [
                'commentId' => $comment->id,
                'parentCommentId' =>
                    $comment->parentCommentId,
                'isInternal' => $comment->isInternal,
            ],
            $request->ip()
        );

        $recipientIds = collect([
            $isInternal ? null : $ticket->userId,
            $ticket->assignedUserId,
        ])
            ->filter()
            ->unique()
            ->reject(
                fn (int $recipientId): bool =>
                    (int) $recipientId
                    === (int) $user->id
            );

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->where('isActive', true)
            ->get();

        foreach ($recipients as $recipient) {
            $this->notificationService->commentAdded(
                $ticket,
                $recipient,
                $user
            );
        }

        return response()->json([
            'success' => true,
            'message' => $parent
                ? 'Reply added successfully.'
                : 'Comment added successfully.',
            'data' => $comment->load([
                'user.role',
                'parent.user.role',
                'attachments.uploadedBy.role',
            ]),
        ], 201);
    }

    public function update(
        Request $request,
        Ticket $ticket,
        TicketComment $comment
    ): JsonResponse {
        if (
            (int) $comment->ticketId
            !== (int) $ticket->id
        ) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        $isAdmin =
            $this->roleName($user) === 'Admin';

        if (
            !$isAdmin
            && (
                !$this->canParticipate(
                    $user,
                    $ticket
                )
                || (int) $comment->userId
                    !== (int) $user->id
            )
        ) {
            return $this->forbidden(
                'Only the authorized comment author or an '
                . 'Admin can edit this comment.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return $this->terminalTicketResponse();
        }

        $validated = $request->validate([
            'comment' => [
                'required',
                'string',
                'max:5000',
            ],
            'isInternal' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (
            !$this->canCreateInternalNotes(
                $user,
                $ticket
            )
            || $comment->parent?->isInternal
        ) {
            unset($validated['isInternal']);
        }

        $oldValues = [
            'isInternal' => $comment->isInternal,
        ];

        $comment->update($validated);

        $this->activityService->record(
            $ticket,
            $user,
            'comment_updated',
            'A ticket comment was updated.',
            $oldValues,
            [
                'commentId' => $comment->id,
                'isInternal' => $comment->isInternal,
            ],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Comment updated successfully.',
            'data' => $comment->fresh([
                'user.role',
                'parent.user.role',
                'attachments.uploadedBy.role',
            ]),
        ]);
    }

    public function destroy(
        Request $request,
        Ticket $ticket,
        TicketComment $comment
    ): JsonResponse {
        if (
            (int) $comment->ticketId
            !== (int) $ticket->id
        ) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();

        $isAdmin =
            $this->roleName($user) === 'Admin';

        if (
            !$isAdmin
            && (
                !$this->canParticipate(
                    $user,
                    $ticket
                )
                || (int) $comment->userId
                    !== (int) $user->id
            )
        ) {
            return $this->forbidden(
                'Only the authorized comment author or an '
                . 'Admin can delete this comment.'
            );
        }

        if ($this->isTerminal($ticket)) {
            return $this->terminalTicketResponse();
        }

        $comment->load([
            'attachments',
            'replies.attachments',
        ]);

        $attachmentPaths = $comment->attachments
            ->pluck('filePath')
            ->merge(
                $comment->replies
                    ->flatMap(
                        fn (TicketComment $reply) =>
                            $reply->attachments
                                ->pluck('filePath')
                    )
            )
            ->filter()
            ->unique();

        foreach ($attachmentPaths as $path) {
            Storage::disk('public')->delete($path);
        }

        $commentId = $comment->id;
        $isReply =
            $comment->parentCommentId !== null;

        $comment->delete();

        $this->activityService->record(
            $ticket,
            $user,
            'comment_deleted',
            $isReply
                ? 'A ticket comment reply was deleted.'
                : 'A ticket comment was deleted.',
            [
                'commentId' => $commentId,
                'wasReply' => $isReply,
            ],
            null,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Comment deleted successfully.',
        ]);
    }
}