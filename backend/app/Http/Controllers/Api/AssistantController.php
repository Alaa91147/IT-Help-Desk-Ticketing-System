<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\OllamaAssistantService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AssistantController extends Controller
{
    public function chat(
        Request $request,
        OllamaAssistantService $assistant
    ): JsonResponse {
        $validated = $request->validate([
            'messages' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],
            'messages.*.role' => [
                'required',
                'in:user,assistant',
            ],
            'messages.*.content' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $lastUserMessage = collect(
            $validated['messages']
        )
            ->reverse()
            ->firstWhere('role', 'user');

        $question = trim(
            (string) (
                $lastUserMessage['content'] ?? ''
            )
        );

        if ($question === '') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Please enter a question.',
            ], 422);
        }

        $relatedTickets =
            $this->findRelatedResolvedTickets(
                $question
            );

        $ticketContext =
            $this->buildTicketContext($request);

        try {
            $answer = $assistant->chat(
                $validated['messages'],
                $relatedTickets->all(),
                $ticketContext
            );
        } catch (ConnectionException $exception) {
            Log::warning(
                'Ollama connection failed.',
                [
                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'The local AI assistant is not running. '
                    .'Please start Ollama and try again.',
            ], 503);
        } catch (Throwable $exception) {
            Log::error(
                'Ollama assistant failed.',
                [
                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'The AI assistant could not generate '
                    .'a response. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $answer,

                'relatedTickets' =>
                    $relatedTickets,

                'source' =>
                    $relatedTickets->isNotEmpty()
                        ? 'ollama-ticket-data-and-history'
                        : 'ollama-and-live-ticket-data',

                'model' => config(
                    'services.ollama.model',
                    'llama3.2:3b'
                ),
            ],
        ]);
    }

    private function findRelatedResolvedTickets(
        string $question
    ): Collection {
        $questionTokens =
            $this->tokens($question);

        if ($questionTokens === []) {
            return collect();
        }

        return Ticket::query()
            ->with([
                'category:id,categoryName',
                'status:id,statusName',
            ])
            ->whereNotNull('resolutionNote')
            ->where('resolutionNote', '!=', '')
            ->whereHas(
                'status',
                function ($query): void {
                    $query->whereIn(
                        'statusName',
                        [
                            'Resolved',
                            'Closed',
                        ]
                    );
                }
            )
            ->latest('resolvedAt')
            ->limit(100)
            ->get()
            ->map(function (
                Ticket $ticket
            ) use ($questionTokens): array {
                $ticketTokens =
                    $this->tokens(
                        $ticket->subject.' '.
                        $ticket->description
                    );

                $overlap = array_intersect(
                    $questionTokens,
                    $ticketTokens
                );

                $score =
                    count($overlap) /
                    max(
                        1,
                        count($questionTokens)
                    );

                return [
                    'score' => $score,

                    'ticketNumber' =>
                        $ticket->ticketNumber,

                    'subject' =>
                        $ticket->subject,

                    'category' =>
                        $ticket->category
                            ?->categoryName,

                    'resolution' =>
                        Str::limit(
                            $ticket->resolutionNote,
                            500
                        ),
                ];
            })
            ->filter(
                fn (array $ticket): bool =>
                    $ticket['score'] >= 0.2
            )
            ->sortByDesc('score')
            ->take(3)
            ->values()
            ->map(
                function (
                    array $ticket
                ): array {
                    $ticket['similarity'] =
                        round(
                            $ticket['score'] * 100,
                            1
                        );

                    unset($ticket['score']);

                    return $ticket;
                }
            );
    }

    private function buildTicketContext(
        Request $request
    ): array {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $role =
            $user->role?->roleName ??
            $user->roleName ??
            '';

        $terminalStatuses = [
            'Resolved',
            'Closed',
            'Cancelled',
        ];

        if ($role === 'SupportAgent') {
            return $this->supportAgentContext(
                $user->id,
                $terminalStatuses
            );
        }

        if (
            in_array(
                $role,
                ['Admin', 'Manager'],
                true
            )
        ) {
            return $this->managementContext(
                $role,
                $terminalStatuses
            );
        }

        return [];
    }

    private function supportAgentContext(
        int $userId,
        array $terminalStatuses
    ): array {
        $tickets = Ticket::query()
            ->with([
                'category:id,categoryName',
                'priority:id,priorityName',
                'status:id,statusName',
            ])
            ->where(
                'assignedUserId',
                $userId
            )
            ->whereHas(
                'status',
                function (
                    $query
                ) use (
                    $terminalStatuses
                ): void {
                    $query->whereNotIn(
                        'statusName',
                        $terminalStatuses
                    );
                }
            )
            ->orderByRaw(
                'CASE WHEN dueAt IS NULL '
                .'THEN 1 ELSE 0 END'
            )
            ->orderBy('dueAt')
            ->get();

        return [
            'userRole' =>
                'SupportAgent',

            'scope' =>
                'Active tickets assigned to the '
                .'logged-in support agent.',

            'activeAssignedTicketCount' =>
                $tickets->count(),

            'overdueAssignedTicketCount' =>
                $tickets
                    ->filter(
                        fn (Ticket $ticket): bool =>
                            $this->isOverdue($ticket)
                    )
                    ->count(),

            'tickets' =>
                $tickets
                    ->take(20)
                    ->map(
                        fn (
                            Ticket $ticket
                        ): array =>
                            $this->ticketData(
                                $ticket
                            )
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function managementContext(
        string $role,
        array $terminalStatuses
    ): array {
        $tickets = Ticket::query()
            ->with([
                'category:id,categoryName',
                'priority:id,priorityName',
                'status:id,statusName',
                'assignedUser:id,firstName,lastName,email',
            ])
            ->whereHas(
                'status',
                function (
                    $query
                ) use (
                    $terminalStatuses
                ): void {
                    $query->whereNotIn(
                        'statusName',
                        $terminalStatuses
                    );
                }
            )
            ->orderByRaw(
                'CASE WHEN dueAt IS NULL '
                .'THEN 1 ELSE 0 END'
            )
            ->orderBy('dueAt')
            ->get();

        $statusCounts = $tickets
            ->groupBy(
                fn (Ticket $ticket): string =>
                    $ticket->status
                        ?->statusName ??
                    'Unknown'
            )
            ->map(
                fn (Collection $group): int =>
                    $group->count()
            )
            ->all();

        $priorityCounts = $tickets
            ->groupBy(
                fn (Ticket $ticket): string =>
                    $ticket->priority
                        ?->priorityName ??
                    'Unknown'
            )
            ->map(
                fn (Collection $group): int =>
                    $group->count()
            )
            ->all();

        return [
            'userRole' => $role,

            'scope' =>
                'All active help-desk tickets '
                .'visible to management.',

            'activeTicketCount' =>
                $tickets->count(),

            'overdueTicketCount' =>
                $tickets
                    ->filter(
                        fn (Ticket $ticket): bool =>
                            $this->isOverdue($ticket)
                    )
                    ->count(),

            'statusCounts' =>
                $statusCounts,

            'priorityCounts' =>
                $priorityCounts,

            'tickets' =>
                $tickets
                    ->take(20)
                    ->map(
                        function (
                            Ticket $ticket
                        ): array {
                            $data =
                                $this->ticketData(
                                    $ticket
                                );

                            $data['assignedTo'] =
                                $this->userName(
                                    $ticket
                                        ->assignedUser
                                );

                            return $data;
                        }
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function ticketData(
        Ticket $ticket
    ): array {
        return [
            'ticketNumber' =>
                $ticket->ticketNumber,

            'subject' =>
                $ticket->subject,

            'category' =>
                $ticket->category
                    ?->categoryName,

            'priority' =>
                $ticket->priority
                    ?->priorityName,

            'status' =>
                $ticket->status
                    ?->statusName,

            'createdAt' =>
                $ticket->createdAt?->format(
                    'Y-m-d H:i:s'
                ),

            'dueAt' =>
                $ticket->dueAt?->format(
                    'Y-m-d H:i:s'
                ),

            'isOverdue' =>
                $this->isOverdue($ticket),
        ];
    }

    private function isOverdue(
        Ticket $ticket
    ): bool {
        return
            $ticket->dueAt !== null &&
            $ticket->dueAt->isPast();
    }

    private function userName(
        $user
    ): string {
        if (! $user) {
            return 'Unassigned';
        }

        $name = trim(
            ($user->firstName ?? '').
            ' '.
            ($user->lastName ?? '')
        );

        return $name !== ''
            ? $name
            : ($user->email ?? 'Unknown');
    }

    private function tokens(
        string $text
    ): array {
        $stopWords = [
            'a',
            'an',
            'and',
            'are',
            'at',
            'be',
            'but',
            'for',
            'from',
            'has',
            'have',
            'i',
            'in',
            'is',
            'it',
            'my',
            'of',
            'on',
            'or',
            'the',
            'this',
            'to',
            'was',
            'with',
            'not',
            'can',
        ];

        $words = preg_split(
            '/[^a-z0-9]+/',
            Str::lower(
                Str::ascii($text)
            ),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return array_values(
            array_unique(
                array_filter(
                    $words ?: [],
                    fn (
                        string $word
                    ): bool =>
                        strlen($word) >= 3 &&
                        ! in_array(
                            $word,
                            $stopWords,
                            true
                        )
                )
            )
        );
    }
}