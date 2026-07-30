<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;

class TicketActivityService
{
    public function record(
        Ticket $ticket,
        User $actor,
        string $action,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null
    ): ActivityLog {
        return ActivityLog::query()->create([
            'userId' => $actor->id,
            'ticketId' => $ticket->id,
            'action' => $action,
            'description' => $description,
            'oldValues' => $oldValues,
            'newValues' => $newValues,
            'ipAddress' => $ipAddress,
        ]);
    }

    public function ticketCreated(
        Ticket $ticket,
        User $actor,
        ?string $ipAddress = null
    ): ActivityLog {
        return $this->record(
            $ticket,
            $actor,
            'ticket_created',
            "Ticket {$ticket->ticketNumber} was created.",
            null,
            [
                'statusId' => $ticket->statusId,
                'categoryId' => $ticket->categoryId,
                'priorityId' => $ticket->priorityId,
                'dueAt' => $ticket->dueAt?->toISOString(),
            ],
            $ipAddress
        );
    }

    public function statusChanged(
        Ticket $ticket,
        User $actor,
        ?string $oldStatus,
        string $newStatus,
        ?string $description = null,
        ?string $ipAddress = null
    ): ActivityLog {
        return $this->record(
            $ticket,
            $actor,
            'status_changed',
            $description
                ?? "Ticket status changed from {$oldStatus} to {$newStatus}.",
            ['status' => $oldStatus],
            ['status' => $newStatus],
            $ipAddress
        );
    }

    public function assigned(
        Ticket $ticket,
        User $actor,
        User $newAgent,
        ?User $previousAgent = null,
        ?string $reason = null,
        ?string $ipAddress = null
    ): ActivityLog {
        $isReassignment = $previousAgent !== null;

        return $this->record(
            $ticket,
            $actor,
            $isReassignment
                ? 'ticket_reassigned'
                : 'ticket_assigned',
            $isReassignment
                ? "Ticket reassigned from {$previousAgent->firstName} "
                    . "{$previousAgent->lastName} to {$newAgent->firstName} "
                    . "{$newAgent->lastName}."
                : "Ticket assigned to {$newAgent->firstName} "
                    . "{$newAgent->lastName}.",
            [
                'assignedUserId' => $previousAgent?->id,
                'assignedUserName' => $previousAgent
                    ? trim(
                        $previousAgent->firstName
                        . ' '
                        . $previousAgent->lastName
                    )
                    : null,
            ],
            [
                'assignedUserId' => $newAgent->id,
                'assignedUserName' => trim(
                    $newAgent->firstName
                    . ' '
                    . $newAgent->lastName
                ),
                'reason' => $reason,
            ],
            $ipAddress
        );
    }

    public function workStarted(
        Ticket $ticket,
        User $actor,
        string $action = 'work_started',
        ?string $ipAddress = null
    ): ActivityLog {
        return $this->record(
            $ticket,
            $actor,
            $action,
            $action === 'work_resumed'
                ? 'Work on the ticket was resumed.'
                : 'Work on the ticket was started.',
            null,
            ['startedAt' => now()->toISOString()],
            $ipAddress
        );
    }

    public function workStopped(
        Ticket $ticket,
        User $actor,
        string $action,
        int $durationSeconds,
        ?string $reason = null,
        ?string $ipAddress = null
    ): ActivityLog {
        return $this->record(
            $ticket,
            $actor,
            $action,
            match ($action) {
                'work_paused' => 'Work on the ticket was paused.',
                'ticket_resolved' => 'The ticket was resolved.',
                'ticket_escalated' => 'The ticket was escalated.',
                'ticket_cancelled' => 'The ticket was cancelled.',
                default => 'The active work session was stopped.',
            },
            null,
            [
                'sessionDurationSeconds' => $durationSeconds,
                'reason' => $reason,
            ],
            $ipAddress
        );
    }
}