<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

class TicketNotificationService
{
    public function send(
        User $recipient,
        ?Ticket $ticket,
        string $type,
        string $title,
        string $message
    ): Notification {
        return Notification::query()->create([
            'userId' => $recipient->id,
            'ticketId' => $ticket?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'isRead' => false,
            'readAt' => null,
        ]);
    }

    public function assigned(
        Ticket $ticket,
        User $agent,
        User $assignedBy,
        bool $isReassignment = false
    ): Notification {
        return $this->send(
            $agent,
            $ticket,
            $isReassignment
                ? 'ticket_reassigned'
                : 'ticket_assigned',
            $isReassignment
                ? 'Ticket reassigned to you'
                : 'New ticket assigned to you',
            "{$ticket->ticketNumber}: {$ticket->subject} was "
                . ($isReassignment ? 'reassigned' : 'assigned')
                . " to you by {$assignedBy->firstName} "
                . "{$assignedBy->lastName}."
        );
    }

    public function assignmentRemoved(
        Ticket $ticket,
        User $previousAgent,
        User $changedBy
    ): Notification {
        return $this->send(
            $previousAgent,
            $ticket,
            'ticket_assignment_removed',
            'Ticket reassigned',
            "{$ticket->ticketNumber}: {$ticket->subject} is no "
                . "longer assigned to you. The assignment was changed by "
                . "{$changedBy->firstName} {$changedBy->lastName}."
        );
    }

    public function statusChanged(
        Ticket $ticket,
        User $recipient,
        string $newStatus
    ): Notification {
        return $this->send(
            $recipient,
            $ticket,
            'ticket_status_changed',
            'Ticket status updated',
            "{$ticket->ticketNumber}: {$ticket->subject} is now "
                . "{$newStatus}."
        );
    }

    public function commentAdded(
        Ticket $ticket,
        User $recipient,
        User $author
    ): Notification {
        return $this->send(
            $recipient,
            $ticket,
            'ticket_comment_added',
            'New ticket comment',
            "{$author->firstName} {$author->lastName} added a "
                . "comment to {$ticket->ticketNumber}."
        );
    }

    public function escalatedToManagers(
        Ticket $ticket,
        User $agent,
        string $reason
    ): Collection {
        $managers = User::query()
            ->where('isActive', true)
            ->whereHas('role', function ($query): void {
                $query->whereIn('roleName', [
                    'Manager',
                    'Admin',
                ]);
            })
            ->get();

        return $managers->map(
            fn (User $manager): Notification => $this->send(
                $manager,
                $ticket,
                'ticket_escalated',
                'Ticket requires reassignment',
                "{$agent->firstName} {$agent->lastName} escalated "
                    . "{$ticket->ticketNumber}. Reason: {$reason}"
            )
        );
    }

    public function cancelled(
        Ticket $ticket,
        User $recipient,
        User $cancelledBy,
        string $reason
    ): Notification {
        return $this->send(
            $recipient,
            $ticket,
            'ticket_cancelled',
            'Ticket cancelled',
            "{$ticket->ticketNumber} was cancelled by "
                . "{$cancelledBy->firstName} {$cancelledBy->lastName}. "
                . "Reason: {$reason}"
        );
    }

    public function notifyParticipantsOfStatus(
        Ticket $ticket,
        User $actor,
        string $newStatus
    ): Collection {
        $recipientIds = collect([
            $ticket->userId,
            $ticket->assignedUserId,
        ])
            ->filter()
            ->unique()
            ->reject(
                fn (int $userId): bool =>
                    (int) $userId === (int) $actor->id
            );

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->where('isActive', true)
            ->get();

        return $recipients->map(
            fn (User $recipient): Notification =>
                $this->statusChanged(
                    $ticket,
                    $recipient,
                    $newStatus
                )
        );
    }
}