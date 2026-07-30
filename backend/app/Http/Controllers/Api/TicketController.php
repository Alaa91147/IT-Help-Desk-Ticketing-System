<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\TicketActivityService;
use App\Services\TicketNotificationService;
use App\Services\TicketWorkSessionService;

class TicketController extends Controller
{
    private const ADMIN = 'Admin';
    private const MANAGER = 'Manager';
    private const AGENT = 'SupportAgent';
    private const EMPLOYEE = 'User';
        public function __construct(
        private readonly TicketActivityService $activityService,
        private readonly TicketNotificationService $notificationService,
        private readonly TicketWorkSessionService $workSessionService
    ) {
    }

    private function calculateDueAt(
        Priority $priority,
        mixed $startingAt = null
    ): mixed {
        $dueAt = $startingAt
            ? $startingAt->copy()
            : now();
        return match (strtolower($priority->priorityName)) {
            'urgent', 'critical' => $dueAt->addHours(4),
            'high' => $dueAt->addDay(),
            'medium' => $dueAt->addDays(3),
            'low' => $dueAt->addDays(5),
            default => $dueAt->addDays(3),
        };
    }

    private function roleName(User $user): ?string
    {
        $user->loadMissing('role');

        return $user->role?->roleName;
    }

    private function forbidden(string $message = 'Unauthorized.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    private function canView(
        User $user,
        Ticket $ticket
    ): bool {
        return match ($this->roleName($user)) {
            self::ADMIN,
            self::MANAGER,
            self::AGENT => true,

            self::EMPLOYEE =>
                (int) $ticket->userId === (int) $user->id,

            default => false,
        };
    }
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'date' => ['nullable', 'date'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'assignedUserId' => ['nullable', 'integer', 'exists:users,id'],
            'sortBy' => [
                'nullable',
                'in:createdAt,updatedAt,ticketNumber,subject,resolvedAt,closedAt',
            ],
            'sortDirection' => ['nullable', 'in:asc,desc'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        if (
            isset($filters['dateFrom'], $filters['dateTo'])
            && $filters['dateTo'] < $filters['dateFrom']
        ) {
            return response()->json([
                'success' => false,
                'message' => 'dateTo must be on or after dateFrom.',
            ], 422);
        }

        $tickets = Ticket::query()
            ->with([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
            ])
            ->withMax(
                'assignments as assignedAt',
                'assignedAt'
            );

        if ($role === self::EMPLOYEE) {
            $tickets->where('userId', $user->id);
        } elseif (!in_array(
            $role,
            [
                self::ADMIN,
                self::MANAGER,
                self::AGENT,
            ],
            true
        )) {
            return $this->forbidden();
        }

        $tickets
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('ticketNumber', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, function (Builder $query, string $status): void {
                $query->whereHas('status', function (Builder $related) use ($status): void {
                    $related->where('statusName', $status);
                });
            })
            ->when($filters['priority'] ?? null, function (Builder $query, string $priority): void {
                $query->whereHas('priority', function (Builder $related) use ($priority): void {
                    $related->where('priorityName', $priority);
                });
            })
            ->when($filters['category'] ?? null, function (Builder $query, int $categoryId): void {
                $query->where('categoryId', $categoryId);
            })
            ->when($filters['date'] ?? null, function (Builder $query, string $date): void {
                $query->whereDate('createdAt', $date);
            })
            ->when($filters['dateFrom'] ?? null, function (Builder $query, string $date): void {
                $query->whereDate('createdAt', '>=', $date);
            })
            ->when($filters['dateTo'] ?? null, function (Builder $query, string $date): void {
                $query->whereDate('createdAt', '<=', $date);
            })
            ->when(
                $filters['assignedUserId'] ?? null,
                fn (Builder $query, int $assignedUserId) =>
                    $query->where('assignedUserId', $assignedUserId)
            )
            ->orderBy(
                $filters['sortBy'] ?? 'createdAt',
                $filters['sortDirection'] ?? 'desc'
            );

        return response()->json([
            'success' => true,
            'data' => $tickets->paginate($filters['perPage'] ?? 10),
        ]);
    }
    public function show(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden(
                'You cannot view this ticket.'
            );
        }

        $ticket->load([
            'user.role',
            'assignedUser.role',
            'category',
            'priority',
            'status',

            'assignments' => function ($query): void {
                $query->orderBy('assignedAt');
            },
            'assignments.assignedUser.role',
            'assignments.previousAssignedUser.role',
            'assignments.assignedByUser.role',

            'workSessions' => function ($query): void {
                $query->orderBy('startedAt');
            },
            'workSessions.user.role',

            'activityLogs' => function ($query): void {
                $query->orderBy('createdAt');
            },
            'activityLogs.user.role',
        ]);

        $ticket->loadMax(
            'assignments as assignedAt',
            'assignedAt'
        );

        $effectiveWorkSeconds = $this->workSessionService
            ->totalEffectiveSeconds($ticket);

        $activeSession = $this->workSessionService
            ->activeSession($ticket);

        $timelineEnd = $ticket->closedAt
            ?? $ticket->cancelledAt
            ?? $ticket->resolvedAt
            ?? now();

        $calendarDurationSeconds = (int) $ticket->createdAt
            ->diffInSeconds(
                $timelineEnd,
                true
            );

        $assignmentCount = $ticket->assignments->count();

        $reassignmentCount = $ticket->assignments
            ->where('assignmentType', 'reassigned')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $ticket,
            'metrics' => [
                'calendarDurationSeconds' =>
                    $calendarDurationSeconds,
                'effectiveWorkSeconds' =>
                    $effectiveWorkSeconds,
                'agentsInvolved' =>
                    $this->workSessionService
                        ->distinctAgentCount($ticket),
                'assignmentCount' => $assignmentCount,
                'reassignmentCount' => $reassignmentCount,
                'hasActiveWorkSession' =>
                    $activeSession !== null,
                'activeWorkSession' => $activeSession,
            ],
            'permissions' => [
                'canView' => true,
                'isCurrentAssignee' =>
                    $this->roleName($user) === self::AGENT
                    && (int) $ticket->assignedUserId
                        === (int) $user->id,
                'isHistoricalAgent' =>
                    $this->roleName($user) === self::AGENT
                    && (int) $ticket->assignedUserId
                        !== (int) $user->id
                    && (
                        $ticket->assignments->contains(
                            'assignedUserId',
                            $user->id
                        )
                        || $ticket->workSessions->contains(
                            'userId',
                            $user->id
                        )
                    ),
                'isReadOnlyAgent' =>
                    $this->roleName($user) === self::AGENT
                    && (int) $ticket->assignedUserId
                        !== (int) $user->id,
                'canManageAssignment' => in_array(
                    $this->roleName($user),
                    [self::ADMIN, self::MANAGER],
                    true
                ),
            ],
        ]);
    }
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!in_array($this->roleName($user), [self::EMPLOYEE, self::ADMIN], true)) {
            return $this->forbidden('Only Employees and Admins can create tickets.');
        }

        $validated = $request->validate([
            'categoryId' => ['required', 'exists:categories,id'],
            'priorityId' => ['required', 'exists:priorities,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $openStatus = Status::where('statusName', 'Open')->firstOrFail();
        $priority = Priority::findOrFail($validated['priorityId']);

        do {
            $ticketNumber = 'TCK-' . strtoupper(Str::random(8));
        } while (Ticket::where('ticketNumber', $ticketNumber)->exists());

        $ticket = Ticket::create([
            'ticketNumber' => $ticketNumber,
            'userId' => $user->id,
            'assignedUserId' => null,
            'categoryId' => $validated['categoryId'],
            'priorityId' => $validated['priorityId'],
            'statusId' => $openStatus->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'dueAt' => $this->calculateDueAt($priority),
        ]);
                $this->activityService->ticketCreated(
            $ticket,
            $user,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data' => $ticket->load(['user', 'category', 'priority', 'status']),
        ], 201);
    }

       public function update(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        $ticket->loadMissing('status');

        $isAdmin = $role === self::ADMIN;

        $isOwner = $role === self::EMPLOYEE
            && (int) $ticket->userId === (int) $user->id;

        if (!$isAdmin && !$isOwner) {
            return $this->forbidden(
                'Only the ticket owner or an Admin can update '
                . 'ticket classification.'
            );
        }

        if (
            !$isAdmin
            && $ticket->status?->statusName !== 'Open'
        ) {
            return $this->forbidden(
                'You cannot update a ticket after work on it '
                . 'has started.'
            );
        }

        $validated = $request->validate([
            'categoryId' => [
                'required',
                'integer',
                'exists:categories,id',
            ],
            'priorityId' => [
                'required',
                'integer',
                'exists:priorities,id',
            ],

            /*
             * Accepted only so that older frontend forms can send
             * the complete ticket. Changed values are rejected.
             */
            'subject' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'string',
            ],
        ]);

        if (
            array_key_exists('subject', $validated)
            && $validated['subject'] !== $ticket->subject
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The ticket subject cannot be changed after '
                    . 'creation.',
                'errors' => [
                    'subject' => [
                        'The ticket subject is immutable.',
                    ],
                ],
            ], 422);
        }

        if (
            array_key_exists('description', $validated)
            && $validated['description'] !== $ticket->description
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The ticket description cannot be changed '
                    . 'after creation.',
                'errors' => [
                    'description' => [
                        'The ticket description is immutable.',
                    ],
                ],
            ], 422);
        }

        unset(
            $validated['subject'],
            $validated['description']
        );

        $oldValues = [
            'categoryId' => $ticket->categoryId,
            'priorityId' => $ticket->priorityId,
            'dueAt' => $ticket->dueAt?->toISOString(),
        ];

        if (
            (int) $ticket->priorityId
            !== (int) $validated['priorityId']
        ) {
            $priority = Priority::query()
                ->findOrFail($validated['priorityId']);

            $validated['dueAt'] = $this->calculateDueAt(
                $priority,
                $ticket->createdAt
            );
        }

        $hasChanges =
            (int) $ticket->categoryId
                !== (int) $validated['categoryId']
            || (int) $ticket->priorityId
                !== (int) $validated['priorityId'];

        if ($hasChanges) {
            DB::transaction(function () use (
                $request,
                $ticket,
                $user,
                $validated,
                $oldValues
            ): void {
                $ticket->update($validated);
                $ticket->refresh();

                $this->activityService->record(
                    $ticket,
                    $user,
                    'ticket_classification_updated',
                    'Ticket category or priority was updated.',
                    $oldValues,
                    [
                        'categoryId' => $ticket->categoryId,
                        'priorityId' => $ticket->priorityId,
                        'dueAt' => $ticket->dueAt?->toISOString(),
                    ],
                    $request->ip()
                );
            });
        }

        return response()->json([
            'success' => true,
            'message' => $hasChanges
                ? 'Ticket classification updated successfully.'
                : 'No ticket changes were required.',
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
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);
        $ticket->loadMissing('status');

        $isAdmin = $role === self::ADMIN;
        $isOwnerOfOpenTicket = $role === self::EMPLOYEE
            && (int) $ticket->userId === (int) $user->id
            && $ticket->status?->statusName === 'Open';

        if (!$isAdmin && !$isOwnerOfOpenTicket) {
            return $this->forbidden(
                'Only an Admin, or the owner of an Open ticket, can delete it.'
            );
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully.',
        ]);
    }

       public function assign(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $assigner */
        $assigner = $request->user();
        $role = $this->roleName($assigner);

        if (!in_array(
            $role,
            [self::ADMIN, self::MANAGER],
            true
        )) {
            return $this->forbidden(
                'Only Managers and Admins can assign tickets.'
            );
        }

        $validated = $request->validate([
            'assignedUserId' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $agent = User::query()
            ->with('role')
            ->findOrFail($validated['assignedUserId']);

        if (
            $agent->role?->roleName !== self::AGENT
            || !$agent->isActive
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Tickets can only be assigned to an active '
                    . 'Support Agent.',
            ], 422);
        }

        $ticket->loadMissing([
            'status',
            'assignedUser.role',
        ]);

        $currentStatus = $ticket->status?->statusName;
        $previousAgent = $ticket->assignedUser;

        if (in_array(
            $currentStatus,
            ['Resolved', 'Closed', 'Cancelled'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Resolved, Closed, or Cancelled tickets '
                    . 'cannot be assigned.',
            ], 422);
        }

        if (
            $previousAgent
            && (int) $previousAgent->id === (int) $agent->id
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This ticket is already assigned to this agent.',
            ], 422);
        }

        $isReassignment = $previousAgent !== null;

        if (
            $isReassignment
            && blank($validated['reason'] ?? null)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'A reason is required when reassigning a ticket.',
                'errors' => [
                    'reason' => [
                        'A reassignment reason is required.',
                    ],
                ],
            ], 422);
        }

        $assignedStatus = Status::query()
            ->where('statusName', 'Assigned')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $agent,
            $assigner,
            $previousAgent,
            $isReassignment,
            $assignedStatus,
            $validated
        ): void {
            if ($previousAgent) {
                $this->workSessionService->stopIfActive(
                    $ticket,
                    $previousAgent,
                    'reassigned'
                );
            }

            $ticket->update([
                'assignedUserId' => $agent->id,
                'statusId' => $assignedStatus->id,
                'resolvedAt' => null,
                'closedAt' => null,
            ]);

            TicketAssignment::query()->create([
                'ticketId' => $ticket->id,
                'assignedUserId' => $agent->id,
                'previousAssignedUserId' =>
                    $previousAgent?->id,
                'assignedByUserId' => $assigner->id,
                'assignmentType' => $isReassignment
                    ? 'reassigned'
                    : 'assigned',
                'reason' => $validated['reason'] ?? null,
                'assignedAt' => now(),
            ]);

            $this->activityService->assigned(
                $ticket,
                $assigner,
                $agent,
                $previousAgent,
                $validated['reason'] ?? null,
                $request->ip()
            );

            $this->notificationService->assigned(
                $ticket,
                $agent,
                $assigner,
                $isReassignment
            );

            if ($previousAgent) {
                $this->notificationService->assignmentRemoved(
                    $ticket,
                    $previousAgent,
                    $assigner
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => $isReassignment
                ? 'Ticket reassigned successfully.'
                : 'Ticket assigned successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'assignments.assignedUser',
                'assignments.previousAssignedUser',
                'assignments.assignedByUser',
            ]),
        ]);
    }

       public function start(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::AGENT) {
            return $this->forbidden(
                'Only Support Agents can start working on tickets.'
            );
        }

        if (
            (int) $ticket->assignedUserId
            !== (int) $user->id
        ) {
            return $this->forbidden(
                'This ticket is not assigned to you.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'Assigned') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only an Assigned ticket can be started.',
            ], 422);
        }

        $inProgressStatus = Status::query()
            ->where('statusName', 'InProgress')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $inProgressStatus
        ): void {
            $this->workSessionService->start(
                $ticket,
                $user
            );

            $ticket->update([
                'statusId' => $inProgressStatus->id,
                'startedAt' => $ticket->startedAt ?? now(),
            ]);

            $this->activityService->statusChanged(
                $ticket,
                $user,
                'Assigned',
                'InProgress',
                'The assigned agent started working on the ticket.',
                $request->ip()
            );

            $this->activityService->workStarted(
                $ticket,
                $user,
                'work_started',
                $request->ip()
            );

            $this->notificationService
                ->notifyParticipantsOfStatus(
                    $ticket,
                    $user,
                    'InProgress'
                );
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket is now In Progress.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'workSessions.user',
            ]),
        ]);
    }
       public function resolve(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        if (!in_array(
            $role,
            [self::AGENT, self::ADMIN],
            true
        )) {
            return $this->forbidden(
                'Only the assigned Support Agent or an Admin '
                . 'can resolve tickets.'
            );
        }

        if (
            $role === self::AGENT
            && (int) $ticket->assignedUserId
                !== (int) $user->id
        ) {
            return $this->forbidden(
                'This ticket is not assigned to you.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'InProgress') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only an In Progress ticket can be resolved.',
            ], 422);
        }

        $validated = $request->validate([
            'resolutionNote' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $resolvedStatus = Status::query()
            ->where('statusName', 'Resolved')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $resolvedStatus,
            $validated
        ): void {
            $activeSession = $this->workSessionService
                ->activeSession($ticket);

            if ($activeSession) {
                $sessionAgent = $activeSession->user;

                $stoppedSession = $this->workSessionService->stop(
                    $ticket,
                    $sessionAgent,
                    'resolved'
                );

                $this->activityService->workStopped(
                    $ticket,
                    $user,
                    'ticket_resolved',
                    $stoppedSession->durationSeconds,
                    $validated['resolutionNote'],
                    $request->ip()
                );
            }

            $ticket->update([
                'statusId' => $resolvedStatus->id,
                'resolutionNote' =>
                    $validated['resolutionNote'],
                'resolvedAt' => now(),
            ]);

            $this->activityService->statusChanged(
                $ticket,
                $user,
                'InProgress',
                'Resolved',
                'Ticket resolved: '
                    . $validated['resolutionNote'],
                $request->ip()
            );

            $this->notificationService
                ->notifyParticipantsOfStatus(
                    $ticket,
                    $user,
                    'Resolved'
                );
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket resolved successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'workSessions.user',
                'activityLogs.user',
            ]),
        ]);
    }
    public function escalate(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::AGENT) {
            return $this->forbidden(
                'Only Support Agents can escalate tickets.'
            );
        }

        if (
            (int) $ticket->assignedUserId
            !== (int) $user->id
        ) {
            return $this->forbidden(
                'This ticket is not assigned to you.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'InProgress') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only an In Progress ticket can be escalated.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $escalatedStatus = Status::query()
            ->where('statusName', 'Escalated')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $validated,
            $escalatedStatus
        ): void {
            $activeSession = $this->workSessionService
                ->activeSession($ticket);

            if ($activeSession) {
                $stoppedSession = $this->workSessionService->stop(
                    $ticket,
                    $user,
                    'escalated'
                );

                $this->activityService->workStopped(
                    $ticket,
                    $user,
                    'ticket_escalated',
                    $stoppedSession->durationSeconds,
                    $validated['reason'],
                    $request->ip()
                );
            }

            $ticket->update([
                'statusId' => $escalatedStatus->id,
                'escalatedAt' => now(),
                'escalationReason' => $validated['reason'],
            ]);

            $this->activityService->statusChanged(
                $ticket,
                $user,
                'InProgress',
                'Escalated',
                'Ticket escalated: ' . $validated['reason'],
                $request->ip()
            );

            $this->notificationService->escalatedToManagers(
                $ticket,
                $user,
                $validated['reason']
            );

            $this->notificationService
                ->notifyParticipantsOfStatus(
                    $ticket,
                    $user,
                    'Escalated'
                );
        });

        return response()->json([
            'success' => true,
            'message' =>
                'Ticket escalated successfully. A Manager must '
                . 'review and reassign it.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'workSessions.user',
                'activityLogs.user',
            ]),
        ]);
    }
        public function cancel(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        $ticket->loadMissing([
            'status',
            'assignedUser',
            'user',
        ]);

        $currentStatus = $ticket->status?->statusName;

        $isOwnerCancellingOpenTicket =
            $role === self::EMPLOYEE
            && (int) $ticket->userId === (int) $user->id
            && $currentStatus === 'Open';

        $isManagement = in_array(
            $role,
            [self::ADMIN, self::MANAGER],
            true
        );

        if (
            !$isOwnerCancellingOpenTicket
            && !$isManagement
        ) {
            return $this->forbidden(
                'Only Managers, Admins, or the owner of an Open '
                . 'ticket can cancel it.'
            );
        }

        if (in_array(
            $currentStatus,
            ['Resolved', 'Closed', 'Cancelled'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Resolved, Closed, or Cancelled tickets '
                    . 'cannot be cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $cancelledStatus = Status::query()
            ->where('statusName', 'Cancelled')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $currentStatus,
            $cancelledStatus,
            $validated
        ): void {
            $activeSession = $this->workSessionService
                ->activeSession($ticket);

            if ($activeSession) {
                $sessionAgent = $activeSession->user;

                $stoppedSession = $this->workSessionService->stop(
                    $ticket,
                    $sessionAgent,
                    'cancelled'
                );

                $this->activityService->workStopped(
                    $ticket,
                    $user,
                    'ticket_cancelled',
                    $stoppedSession->durationSeconds,
                    $validated['reason'],
                    $request->ip()
                );
            }

            $ticket->update([
                'statusId' => $cancelledStatus->id,
                'cancelledAt' => now(),
                'cancellationReason' => $validated['reason'],
            ]);

            $this->activityService->statusChanged(
                $ticket,
                $user,
                $currentStatus,
                'Cancelled',
                'Ticket cancelled: ' . $validated['reason'],
                $request->ip()
            );

            $recipientIds = collect([
                $ticket->userId,
                $ticket->assignedUserId,
            ])
                ->filter()
                ->unique()
                ->reject(
                    fn (int $recipientId): bool =>
                        (int) $recipientId === (int) $user->id
                );

            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->where('isActive', true)
                ->get();

            foreach ($recipients as $recipient) {
                $this->notificationService->cancelled(
                    $ticket,
                    $recipient,
                    $user,
                    $validated['reason']
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket cancelled successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'workSessions.user',
                'activityLogs.user',
            ]),
        ]);
    }
        public function close(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        if (!in_array(
            $role,
            [self::ADMIN, self::MANAGER],
            true
        )) {
            return $this->forbidden(
                'Only Managers and Admins can close tickets.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'Resolved') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only a Resolved ticket can be closed.',
            ], 422);
        }

        $validated = $request->validate([
            'closingNote' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $closedStatus = Status::query()
            ->where('statusName', 'Closed')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $user,
            $closedStatus,
            $validated
        ): void {
            /*
             * A Resolved ticket normally has no active session.
             * This safely stops one if inconsistent data exists.
             */
            $activeSession = $this->workSessionService
                ->activeSession($ticket);

            if ($activeSession) {
                $sessionAgent = $activeSession->user;

                $this->workSessionService->stop(
                    $ticket,
                    $sessionAgent,
                    'closed'
                );
            }

            $ticket->update([
                'statusId' => $closedStatus->id,
                'closedAt' => now(),
            ]);

            $description = filled(
                $validated['closingNote'] ?? null
            )
                ? 'Ticket closed: '
                    . $validated['closingNote']
                : 'Ticket closed after resolution.';

            $this->activityService->statusChanged(
                $ticket,
                $user,
                'Resolved',
                'Closed',
                $description,
                $request->ip()
            );

            $this->notificationService
                ->notifyParticipantsOfStatus(
                    $ticket,
                    $user,
                    'Closed'
                );
        });

        $ticket->refresh();

        $effectiveSeconds = $this->workSessionService
            ->totalEffectiveSeconds($ticket);

        $calendarDurationSeconds = $ticket->createdAt
            ->diffInSeconds(
                $ticket->closedAt,
                true
            );

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully.',
            'data' => $ticket->load([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'assignments.assignedUser',
                'assignments.previousAssignedUser',
                'assignments.assignedByUser',
                'workSessions.user',
                'activityLogs.user',
            ]),
            'metrics' => [
                'calendarDurationSeconds' =>
                    $calendarDurationSeconds,
                'effectiveWorkSeconds' =>
                    $effectiveSeconds,
                'agentsInvolved' =>
                    $this->workSessionService
                        ->distinctAgentCount($ticket),
            ],
        ]);
    }
       public function ticketSummary(
        Request $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user()->loadMissing('role');

        if (!in_array(
            $user->role?->roleName,
            [self::MANAGER, self::ADMIN],
            true
        )) {
            return $this->forbidden(
                'Only Managers and Admins can view reports.'
            );
        }

        $filters = $request->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
        ]);

        if (
            isset($filters['dateFrom'], $filters['dateTo'])
            && $filters['dateTo'] < $filters['dateFrom']
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'dateTo must be on or after dateFrom.',
            ], 422);
        }

        $baseQuery = $this->withinDateRange(
            Ticket::query(),
            $filters
        );

        $reportTickets = (clone $baseQuery)
            ->with([
                'status',
                'priority',
                'category',
                'assignedUser',
            ])
            ->withSum(
                'workSessions as effectiveWorkSeconds',
                'durationSeconds'
            )
            ->withCount([
                'assignments as assignmentCount',
                'assignments as reassignmentCount' =>
                    fn (Builder $query) =>
                        $query->where(
                            'assignmentType',
                            'reassigned'
                        ),
            ])
            ->get();

        $byStatus = (clone $baseQuery)
            ->join(
                'statuses',
                'tickets.statusId',
                '=',
                'statuses.id'
            )
            ->selectRaw(
                'statuses.statusName AS name, COUNT(*) AS total'
            )
            ->groupBy(
                'statuses.id',
                'statuses.statusName'
            )
            ->orderBy('statuses.statusName')
            ->get();

        $byPriority = (clone $baseQuery)
            ->join(
                'priorities',
                'tickets.priorityId',
                '=',
                'priorities.id'
            )
            ->selectRaw(
                'priorities.priorityName AS name, '
                . 'COUNT(*) AS total'
            )
            ->groupBy(
                'priorities.id',
                'priorities.priorityName'
            )
            ->orderBy('priorities.priorityName')
            ->get();

        $byCategory = (clone $baseQuery)
            ->join(
                'categories',
                'tickets.categoryId',
                '=',
                'categories.id'
            )
            ->selectRaw(
                'categories.categoryName AS name, '
                . 'COUNT(*) AS total'
            )
            ->groupBy(
                'categories.id',
                'categories.categoryName'
            )
            ->orderByDesc('total')
            ->get();

        $completedResolutionDurations = $reportTickets
            ->filter(
                fn (Ticket $ticket): bool =>
                    $ticket->resolvedAt !== null
            )
            ->map(
                fn (Ticket $ticket): int =>
                    (int) $ticket->createdAt->diffInSeconds(
                        $ticket->resolvedAt,
                        true
                    )
            );

        $completedClosureDurations = $reportTickets
            ->filter(
                fn (Ticket $ticket): bool =>
                    $ticket->closedAt !== null
            )
            ->map(
                fn (Ticket $ticket): int =>
                    (int) $ticket->createdAt->diffInSeconds(
                        $ticket->closedAt,
                        true
                    )
            );

        $overdueCount = (clone $baseQuery)
            ->whereNotNull('dueAt')
            ->where('dueAt', '<', now())
            ->whereHas(
                'status',
                function (Builder $status): void {
                    $status->whereNotIn(
                        'statusName',
                        [
                            'Resolved',
                            'Closed',
                            'Cancelled',
                        ]
                    );
                }
            )
            ->count();

        $agentPerformance = User::query()
            ->where('isActive', true)
            ->whereHas(
                'role',
                function (Builder $query): void {
                    $query->where(
                        'roleName',
                        self::AGENT
                    );
                }
            )
            ->withCount([
                'assignedTickets as currentAssignedTicketsCount' =>
                    fn (Builder $query) =>
                        $this->withinDateRange(
                            $query,
                            $filters
                        ),

                'assignedTickets as inProgressTicketsCount' =>
                    fn (Builder $query) =>
                        $this->withinDateRange(
                            $query,
                            $filters
                        )->whereHas(
                            'status',
                            function (Builder $status): void {
                                $status->where(
                                    'statusName',
                                    'InProgress'
                                );
                            }
                        ),

                'assignedTickets as resolvedTicketsCount' =>
                    fn (Builder $query) =>
                        $this->withinDateRange(
                            $query,
                            $filters
                        )->whereHas(
                            'status',
                            function (Builder $status): void {
                                $status->whereIn(
                                    'statusName',
                                    [
                                        'Resolved',
                                        'Closed',
                                    ]
                                );
                            }
                        ),

                'receivedAssignments as assignmentHistoryCount' =>
                    function (Builder $query) use (
                        $filters
                    ): void {
                        $query->whereHas(
                            'ticket',
                            fn (Builder $ticketQuery) =>
                                $this->withinDateRange(
                                    $ticketQuery,
                                    $filters
                                )
                        );
                    },

                'ticketWorkSessions as workSessionCount' =>
                    function (Builder $query) use (
                        $filters
                    ): void {
                        $query->whereHas(
                            'ticket',
                            fn (Builder $ticketQuery) =>
                                $this->withinDateRange(
                                    $ticketQuery,
                                    $filters
                                )
                        );
                    },
            ])
            ->withSum(
                [
                    'ticketWorkSessions as effectiveWorkSeconds' =>
                        function (Builder $query) use (
                            $filters
                        ): void {
                            $query->whereHas(
                                'ticket',
                                fn (Builder $ticketQuery) =>
                                    $this->withinDateRange(
                                        $ticketQuery,
                                        $filters
                                    )
                            );
                        },
                ],
                'durationSeconds'
            )
            ->get([
                'id',
                'firstName',
                'lastName',
                'email',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'dateFrom' =>
                        $filters['dateFrom'] ?? null,
                    'dateTo' =>
                        $filters['dateTo'] ?? null,
                ],

                'totals' => [
                    'tickets' => $reportTickets->count(),

                    'unassigned' => $reportTickets
                        ->whereNull('assignedUserId')
                        ->count(),

                    'overdue' => $overdueCount,

                    'escalated' => $reportTickets
                        ->filter(
                            fn (Ticket $ticket): bool =>
                                $ticket->status?->statusName
                                    === 'Escalated'
                        )
                        ->count(),

                    'cancelled' => $reportTickets
                        ->filter(
                            fn (Ticket $ticket): bool =>
                                $ticket->status?->statusName
                                    === 'Cancelled'
                        )
                        ->count(),

                    'resolved' => $reportTickets
                        ->filter(
                            fn (Ticket $ticket): bool =>
                                $ticket->status?->statusName
                                    === 'Resolved'
                        )
                        ->count(),

                    'closed' => $reportTickets
                        ->filter(
                            fn (Ticket $ticket): bool =>
                                $ticket->status?->statusName
                                    === 'Closed'
                        )
                        ->count(),

                    'assignments' => (int) $reportTickets
                        ->sum('assignmentCount'),

                    'reassignments' => (int) $reportTickets
                        ->sum('reassignmentCount'),

                    'effectiveWorkSeconds' =>
                        (int) $reportTickets->sum(
                            'effectiveWorkSeconds'
                        ),

                    'averageResolutionSeconds' =>
                        $completedResolutionDurations->isEmpty()
                            ? 0
                            : (int) round(
                                $completedResolutionDurations
                                    ->average()
                            ),

                    'averageClosureSeconds' =>
                        $completedClosureDurations->isEmpty()
                            ? 0
                            : (int) round(
                                $completedClosureDurations
                                    ->average()
                            ),
                ],

                'byStatus' => $byStatus,
                'byPriority' => $byPriority,
                'byCategory' => $byCategory,
                'agentPerformance' => $agentPerformance,
            ],
        ]);
    }
    public function pause(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::AGENT) {
            return $this->forbidden(
                'Only Support Agents can pause ticket work.'
            );
        }

        if (
            (int) $ticket->assignedUserId
            !== (int) $user->id
        ) {
            return $this->forbidden(
                'This ticket is not assigned to you.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'InProgress') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only an In Progress ticket can be paused.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $session = $this->workSessionService->stop(
            $ticket,
            $user,
            $validated['reason'] ?? 'paused'
        );

        $this->activityService->workStopped(
            $ticket,
            $user,
            'work_paused',
            $session->durationSeconds,
            $validated['reason'] ?? null,
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Work timer paused successfully.',
            'data' => [
                'ticket' => $ticket->fresh([
                    'assignedUser',
                    'status',
                    'workSessions.user',
                ]),
                'stoppedSession' => $session,
                'totalEffectiveSeconds' =>
                    $this->workSessionService
                        ->totalEffectiveSeconds($ticket),
            ],
        ]);
    }
        public function resume(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::AGENT) {
            return $this->forbidden(
                'Only Support Agents can resume ticket work.'
            );
        }

        if (
            (int) $ticket->assignedUserId
            !== (int) $user->id
        ) {
            return $this->forbidden(
                'This ticket is not assigned to you.'
            );
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'InProgress') {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only an In Progress ticket can be resumed.',
            ], 422);
        }

        $session = $this->workSessionService->start(
            $ticket,
            $user
        );

        $this->activityService->workStarted(
            $ticket,
            $user,
            'work_resumed',
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Work timer resumed successfully.',
            'data' => [
                'ticket' => $ticket->fresh([
                    'assignedUser',
                    'status',
                    'workSessions.user',
                ]),
                'activeSession' => $session,
                'totalEffectiveSeconds' =>
                    $this->workSessionService
                        ->totalEffectiveSeconds($ticket),
            ],
        ]);
    }

private function withinDateRange(
    Builder $query,
    array $filters
): Builder {
    return $query
        ->when(
            $filters['dateFrom'] ?? null,
            fn (Builder $builder, string $date) =>
                $builder->whereDate(
                    'tickets.createdAt',
                    '>=',
                    $date
                )
        )
        ->when(
            $filters['dateTo'] ?? null,
            fn (Builder $builder, string $date) =>
                $builder->whereDate(
                    'tickets.createdAt',
                    '<=',
                    $date
                )
        );
}
}