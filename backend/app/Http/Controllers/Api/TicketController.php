<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Status;
use App\Models\Ticket;
use Illuminate\Support\Str;


class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    $tickets = Ticket::with([
        'user',
        'assignedUser',
        'category',
        'priority',
        'status',
    ])
    ->when($request->search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
            $q->where('ticketNumber', 'like', "%{$search}%")
              ->orWhere('subject', 'like', "%{$search}%");
        });
    })
    ->when($request->status, function ($query, $status) {
        $query->whereHas('status', function ($q) use ($status) {
            $q->where('statusName', $status);
        });
    })
    ->when($request->priority, function ($query, $priority) {
        $query->whereHas('priority', function ($q) use ($priority) {
            $q->where('priorityName', $priority);
        });
    })
    ->when($request->category, function ($query, $category) {
        $query->whereHas('category', function ($q) use ($category) {
            $q->where('categoryName', $category);
        });
    })

    ->when($request->date, function ($query, $date) {
        $query->whereDate('createdAt', $date);
    })

    ->when($request->assignedUserId, function ($query, $assignedUserId) {
        $query->where('assignedUserId', $assignedUserId);
    })
    ->latest('createdAt')
    ->orderBy(
    $request->sortBy ?? 'createdAt',
    $request->sortDirection ?? 'desc'
    )
    ->paginate($request->perPage ?? 10);

    return response()->json([
        'success' => true,
        'data' => $tickets,
    ]);
}


public function show(Ticket $ticket)
{
    $ticket->load([
        'user',
        'assignedUser',
        'category',
        'priority',
        'status',
    ]);

    return response()->json([
        'success' => true,
        'data' => $ticket,
    ]);
}
    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
{
    $request->validate([
        'categoryId' => 'required|exists:categories,id',
        'priorityId' => 'required|exists:priorities,id',
        'subject' => 'required|string|max:255',
        'description' => 'required|string',
    ]);

    $openStatus = Status::where('statusName', 'Open')->firstOrFail();

    $ticket = Ticket::create([
        'ticketNumber' => 'TCK-' . strtoupper(Str::random(8)),
        'userId' => auth()->id(),
        'assignedUserId' => null,
        'categoryId' => $request->categoryId,
        'priorityId' => $request->priorityId,
        'statusId' => $openStatus->id,
        'subject' => $request->subject,
        'description' => $request->description,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Ticket created successfully.',
        'data' => $ticket,
    ], 201);
}


    /**
     * Update the specified resource in storage.
     */
public function update(Request $request, Ticket $ticket)
{
    $user = auth()->user();

    // Only Admin and SupportAgent can update tickets
    if (!in_array($user->role->roleName, ['Admin', 'SupportAgent'])) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }

    $inProgressStatus = Status::where('statusName', 'InProgress')->first();

    // If ticket is InProgress, only Admin or the assigned SupportAgent can edit it
    if (
        $ticket->statusId === $inProgressStatus->id &&
        $user->role->roleName !== 'Admin' &&
        $ticket->assignedUserId !== $user->id
    ) {
        return response()->json([
            'success' => false,
            'message' => 'This ticket is currently being worked on by another Support Agent.',
        ], 403);
    }

    $request->validate([
        'categoryId' => 'required|exists:categories,id',
        'priorityId' => 'required|exists:priorities,id',
        'subject' => 'required|string|max:255',
        'description' => 'required|string',
    ]);

    $ticket->update([
        'categoryId' => $request->categoryId,
        'priorityId' => $request->priorityId,
        'subject' => $request->subject,
        'description' => $request->description,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Ticket updated successfully.',
        'data' => $ticket->fresh(),
    ]);
}

    /**
     * Remove the specified resource from storage.
     */
public function destroy(Ticket $ticket)
{
    $user = auth()->user();

    // Only Admin or SupportAgent can delete tickets
    if (!in_array($user->role->roleName, ['Admin', 'SupportAgent'])) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }

    $inProgressStatus = Status::where('statusName', 'InProgress')->first();

    // If ticket is InProgress, only Admin or assigned SupportAgent can delete
    if (
        $ticket->statusId === $inProgressStatus->id &&
        $user->role->roleName !== 'Admin' &&
        $ticket->assignedUserId !== $user->id
    ) {
        return response()->json([
            'success' => false,
            'message' => 'This ticket is currently being worked on by another Support Agent.',
        ], 403);
    }

    $ticket->delete();

    return response()->json([
        'success' => true,
        'message' => 'Ticket deleted successfully.',
    ]);
}

public function assign(Request $request, Ticket $ticket)
{
    $request->validate([
        'assignedUserId' => 'required|exists:users,id',
    ]);

    if (auth()->user()->role->roleName !== 'Admin') {
        return response()->json([
            'success' => false,
            'message' => 'Only Admin can assign tickets.',
        ], 403);
    }

    $assignedStatus = Status::where('statusName', 'Assigned')->firstOrFail();

    $ticket->update([
        'assignedUserId' => $request->assignedUserId,
        'statusId' => $assignedStatus->id,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Ticket assigned successfully.',
        'data' => $ticket->fresh([
            'user',
            'assignedUser',
            'category',
            'priority',
            'status',
        ]),
    ]);
}

public function start(Ticket $ticket)
{
    if (!auth()->user()->role || auth()->user()->role->roleName !== 'SupportAgent') {
        return response()->json([
            'success' => false,
            'message' => 'Only Support Agents can start working on tickets.',
        ], 403);
    }

    if ($ticket->assignedUserId !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => 'This ticket is not assigned to you.',
        ], 403);
    }

    $inProgressStatus = Status::where('statusName', 'InProgress')->firstOrFail();

    $ticket->update([
        'statusId' => $inProgressStatus->id,
    ]);

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

public function resolve(Ticket $ticket)
{
    if (!auth()->user()->role || auth()->user()->role->roleName !== 'SupportAgent') {
        return response()->json([
            'success' => false,
            'message' => 'Only Support Agents can resolve tickets.',
        ], 403);
    }

    if ($ticket->assignedUserId !== auth()->id()) {
        return response()->json([
            'success' => false,
            'message' => 'This ticket is not assigned to you.',
        ], 403);
    }

    $resolvedStatus = Status::where('statusName', 'Resolved')->firstOrFail();

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

public function close(Ticket $ticket)
{
    if (!auth()->user()->role || auth()->user()->role->roleName !== 'Admin') {
        return response()->json([
            'success' => false,
            'message' => 'Only Admin can close tickets.',
        ], 403);
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

}
