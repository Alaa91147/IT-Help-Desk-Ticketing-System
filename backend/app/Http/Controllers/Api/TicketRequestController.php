<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\TicketActivityService;
use App\Services\TicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketRequestController extends Controller
{
    public function __construct(
        private readonly TicketActivityService $activityService,
        private readonly TicketNotificationService $notificationService
    ) {}

    public function index(): JsonResponse
    {
        $requests = Ticket::query()
            ->with([
                'requester:id,firstName,lastName,email',
                'user:id,firstName,lastName,email',
                'category:id,categoryName',
                'priority:id,priorityName',
                'status:id,statusName',
            ])
            ->where('agent_request_status', 'requested')
            ->orderByDesc('agent_requested_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function request(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('role');

        if ($user->role?->roleName !== 'SupportAgent') {
            return response()->json([
                'success' => false,
                'message' => 'Only Support Agents can request tickets.',
            ], 403);
        }

        $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->loadMissing('status');

        if ($ticket->assignedUserId) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is already assigned.',
            ], 409);
        }

        if (in_array(
            $ticket->status?->statusName,
            ['Resolved', 'Closed', 'Cancelled'],
            true
        )) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is no longer available.',
            ], 409);
        }

        if ($ticket->agent_request_status === 'requested') {
            return response()->json([
                'success' => false,
                'message' => 'This ticket already has a pending request.',
            ], 409);
        }

        $ticket->update([
            'agent_requester_id' => $user->id,
            'agent_request_status' => 'requested',
            'agent_requested_at' => now(),
        ]);

        User::query()
            ->where('isActive', true)
            ->whereHas('role', fn ($query) =>
                $query->where('roleName', 'Admin'))
            ->get()
            ->each(fn (User $admin) =>
                $this->notificationService->send(
                    $admin,
                    $ticket,
                    'agent_ticket_requested',
                    'Agent requested a ticket',
                    trim($user->firstName.' '.$user->lastName)
                        ." requested {$ticket->ticketNumber}."
                ));

        return response()->json([
            'success' => true,
            'message' => 'Ticket request submitted successfully.',
        ], 201);
    }

    public function accept(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->loadMissing(['requester.role', 'status']);
        $agent = $ticket->requester;

        if ($ticket->agent_request_status !== 'requested' || ! $agent) {
            return response()->json([
                'success' => false,
                'message' => 'This request is no longer pending.',
            ], 409);
        }

        if ($ticket->assignedUserId) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has already been assigned.',
            ], 409);
        }

        if (! $agent->isActive || $agent->role?->roleName !== 'SupportAgent') {
            return response()->json([
                'success' => false,
                'message' => 'The requesting Support Agent is not active.',
            ], 422);
        }

        $admin = $request->user();
        $assignedStatus = Status::query()
            ->where('statusName', 'Assigned')
            ->firstOrFail();

        DB::transaction(function () use (
            $request,
            $ticket,
            $agent,
            $admin,
            $assignedStatus
        ): void {
            $ticket->update([
                'assignedUserId' => $agent->id,
                'statusId' => $assignedStatus->id,
                'agent_request_status' => 'accepted',
            ]);

            TicketAssignment::query()->create([
                'ticketId' => $ticket->id,
                'assignedUserId' => $agent->id,
                'previousAssignedUserId' => null,
                'assignedByUserId' => $admin->id,
                'assignmentType' => 'assigned',
                'reason' => 'Agent ticket request approved.',
                'assignedAt' => now(),
            ]);

            $this->activityService->assigned(
                $ticket,
                $admin,
                $agent,
                null,
                'Agent ticket request approved.',
                $request->ip()
            );

            $this->notificationService->assigned(
                $ticket,
                $agent,
                $admin
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Request accepted and ticket assigned.',
        ]);
    }

    public function reject(Request $request, Ticket $ticket): JsonResponse
    {
        $ticket->loadMissing('requester');
        $agent = $ticket->requester;

        if ($ticket->agent_request_status !== 'requested') {
            return response()->json([
                'success' => false,
                'message' => 'This request is no longer pending.',
            ], 409);
        }

        if ($agent) {
            $this->notificationService->send(
                $agent,
                $ticket,
                'agent_ticket_request_rejected',
                'Ticket request rejected',
                "Your request for {$ticket->ticketNumber} was rejected."
            );
        }

        $ticket->update([
            'agent_request_status' => 'rejected',
            'agent_requester_id' => null,
            'agent_requested_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
        ]);
    }
}