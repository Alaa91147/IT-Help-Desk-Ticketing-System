<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    private const ADMIN = 'Admin';
    private const MANAGER = 'Manager';
    private const AGENT = 'SupportAgent';
    private const EMPLOYEE = 'User';

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

    private function canView(User $user, Ticket $ticket): bool
    {
        return match ($this->roleName($user)) {
            self::ADMIN, self::MANAGER => true,
            self::AGENT => (int) $ticket->assignedUserId === (int) $user->id,
            self::EMPLOYEE => (int) $ticket->userId === (int) $user->id,
            default => false,
        };
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
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

        $tickets = Ticket::query()
            ->with([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
            ]);

        if ($role === self::EMPLOYEE) {
            $tickets->where('userId', $user->id);
        } elseif ($role === self::AGENT) {
            $tickets->where('assignedUserId', $user->id);
        } elseif (!in_array($role, [self::ADMIN, self::MANAGER], true)) {
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
            ->when($filters['category'] ?? null, function (Builder $query, string $category): void {
                $query->whereHas('category', function (Builder $related) use ($category): void {
                    $related->where('categoryName', $category);
                });
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

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden('You cannot view this ticket.');
        }

        $ticket->load([
            'user',
            'assignedUser',
            'category',
            'priority',
            'status',
            'assignments.assignedUser',
            'assignments.assignedByUser',
        ]);

        return response()->json([
            'success' => true,
            'data' => $ticket,
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully.',
            'data' => $ticket->load(['user', 'category', 'priority', 'status']),
        ], 201);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);
        $ticket->loadMissing('status');

        $isAdmin = $role === self::ADMIN;
        $isOwner = $role === self::EMPLOYEE
            && (int) $ticket->userId === (int) $user->id;

        if (!$isAdmin && !$isOwner) {
            return $this->forbidden(
                'Only the ticket owner or an Admin can edit this ticket.'
            );
        }

        if (!$isAdmin && $ticket->status?->statusName !== 'Open') {
            return $this->forbidden(
                'You cannot edit a ticket after work on it has started.'
            );
        }

        $validated = $request->validate([
            'categoryId' => ['required', 'exists:categories,id'],
            'priorityId' => ['required', 'exists:priorities,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $ticket->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
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

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $assigner */
        $assigner = $request->user();
        $role = $this->roleName($assigner);

        if (!in_array($role, [self::ADMIN, self::MANAGER], true)) {
            return $this->forbidden(
                'Only Managers and Admins can assign tickets.'
            );
        }

        $validated = $request->validate([
            'assignedUserId' => ['required', 'integer', 'exists:users,id'],
        ]);

        $agent = User::with('role')->findOrFail($validated['assignedUserId']);

        if ($agent->role?->roleName !== self::AGENT || !$agent->isActive) {
            return response()->json([
                'success' => false,
                'message' => 'Tickets can only be assigned to an active Support Agent.',
            ], 422);
        }

        $ticket->loadMissing('status');
        $currentStatus = $ticket->status?->statusName;

        if (in_array($currentStatus, ['Resolved', 'Closed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Resolved or Closed tickets cannot be assigned.',
            ], 422);
        }

        if ($currentStatus === 'InProgress' && $role !== self::ADMIN) {
            return $this->forbidden(
                'Only an Admin can reassign a ticket that is In Progress.'
            );
        }

        $assignedStatus = Status::where('statusName', 'Assigned')->firstOrFail();

        DB::transaction(function () use (
            $ticket,
            $agent,
            $assigner,
            $assignedStatus
        ): void {
            $ticket->update([
                'assignedUserId' => $agent->id,
                'statusId' => $assignedStatus->id,
                'resolvedAt' => null,
                'closedAt' => null,
            ]);

            TicketAssignment::create([
                'ticketId' => $ticket->id,
                'assignedUserId' => $agent->id,
                'assignedByUserId' => $assigner->id,
                'assignedAt' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
                'assignments.assignedByUser',
            ]),
        ]);
    }

    public function start(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::AGENT) {
            return $this->forbidden(
                'Only Support Agents can start working on tickets.'
            );
        }

        if ((int) $ticket->assignedUserId !== (int) $user->id) {
            return $this->forbidden('This ticket is not assigned to you.');
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'Assigned') {
            return response()->json([
                'success' => false,
                'message' => 'Only an Assigned ticket can be started.',
            ], 422);
        }

        $inProgressStatus = Status::where('statusName', 'InProgress')
            ->firstOrFail();

        $ticket->update(['statusId' => $inProgressStatus->id]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket is now In Progress.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
            ]),
        ]);
    }

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $role = $this->roleName($user);

        if (!in_array($role, [self::AGENT, self::ADMIN], true)) {
            return $this->forbidden(
                'Only the assigned Support Agent or an Admin can resolve tickets.'
            );
        }

        if (
            $role === self::AGENT
            && (int) $ticket->assignedUserId !== (int) $user->id
        ) {
            return $this->forbidden('This ticket is not assigned to you.');
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'InProgress') {
            return response()->json([
                'success' => false,
                'message' => 'Only an In Progress ticket can be resolved.',
            ], 422);
        }

        $resolvedStatus = Status::where('statusName', 'Resolved')
            ->firstOrFail();

        $ticket->update([
            'statusId' => $resolvedStatus->id,
            'resolvedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket resolved successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
            ]),
        ]);
    }

    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->roleName($user) !== self::ADMIN) {
            return $this->forbidden('Only Admins can close tickets.');
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName !== 'Resolved') {
            return response()->json([
                'success' => false,
                'message' => 'Only a Resolved ticket can be closed.',
            ], 422);
        }

        $closedStatus = Status::where('statusName', 'Closed')->firstOrFail();

        $ticket->update([
            'statusId' => $closedStatus->id,
            'closedAt' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully.',
            'data' => $ticket->fresh([
                'user',
                'assignedUser',
                'category',
                'priority',
                'status',
            ]),
        ]);
    }
    public function ticketSummary(Request $request): JsonResponse
{
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
            'message' => 'dateTo must be on or after dateFrom.',
        ], 422);
    }

    $baseQuery = $this->withinDateRange(
        Ticket::query(),
        $filters
    );

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
        ->groupBy('statuses.id', 'statuses.statusName')
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
            'priorities.priorityName AS name, COUNT(*) AS total'
        )
        ->groupBy('priorities.id', 'priorities.priorityName')
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
            'categories.categoryName AS name, COUNT(*) AS total'
        )
        ->groupBy('categories.id', 'categories.categoryName')
        ->orderByDesc('total')
        ->get();

    $agentPerformance = User::query()
        ->where('isActive', true)
        ->whereHas('role', function (Builder $query): void {
            $query->where('roleName', self::AGENT);
        })
        ->withCount([
            'assignedTickets as assignedTicketsCount' =>
                fn (Builder $query) =>
                    $this->withinDateRange($query, $filters),

            'assignedTickets as inProgressTicketsCount' =>
                fn (Builder $query) =>
                    $this->withinDateRange($query, $filters)
                        ->whereHas(
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
                    $this->withinDateRange($query, $filters)
                        ->whereHas(
                            'status',
                            function (Builder $status): void {
                                $status->whereIn(
                                    'statusName',
                                    ['Resolved', 'Closed']
                                );
                            }
                        ),
        ])
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
                'dateFrom' => $filters['dateFrom'] ?? null,
                'dateTo' => $filters['dateTo'] ?? null,
            ],
            'totals' => [
                'tickets' => (clone $baseQuery)->count(),
                'unassigned' => (clone $baseQuery)
                    ->whereNull('assignedUserId')
                    ->count(),
            ],
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'byCategory' => $byCategory,
            'agentPerformance' => $agentPerformance,
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