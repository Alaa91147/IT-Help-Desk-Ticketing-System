<?php

namespace App\Services;

use App\Events\NotificationCreated;
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
        $notification = Notification::query()->create([
            'userId' => $recipient->id,
            'ticketId' => $ticket?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'isRead' => false,
            'readAt' => null,
        ]);

        NotificationCreated::dispatch($notification);

        return $notification;
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
                . "{$cancelledBy->firstName} "
                . "{$cancelledBy->lastName}. "
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
    private function managementRecipients(
    ?User $excludedUser = null
): Collection {
    return User::query()
        ->where('isActive', true)
        ->whereHas('role', function ($query): void {
            $query->whereIn('roleName', [
                'Admin',
                'Manager',
            ]);
        })
        ->when(
            $excludedUser,
            fn ($query) =>
                $query->where(
                    'id',
                    '!=',
                    $excludedUser->id
                )
        )
        ->get();
}

public function ticketCreatedForManagement(
    Ticket $ticket,
    User $createdBy
): Collection {
    $createdAt = $ticket->createdAt
        ?->format('d/m/Y H:i:s');

    return $this->managementRecipients($createdBy)
        ->map(
            fn (User $recipient): Notification =>
                $this->send(
                    $recipient,
                    $ticket,
                    'ticket_created',
                    'New ticket created',
                    "{$ticket->ticketNumber}: "
                        . "{$ticket->subject} was created by "
                        . "{$createdBy->firstName} "
                        . "{$createdBy->lastName} at "
                        . "{$createdAt}."
                )
        );
}

public function ticketClosedForManagement(
    Ticket $ticket,
    User $closedBy
): Collection {
    $closedAt = $ticket->closedAt
        ?->format('d/m/Y H:i:s');

    return $this->managementRecipients($closedBy)
        ->map(
            fn (User $recipient): Notification =>
                $this->send(
                    $recipient,
                    $ticket,
                    'ticket_closed',
                    'Ticket closed',
                    "{$ticket->ticketNumber}: "
                        . "{$ticket->subject} was closed by "
                        . "{$closedBy->firstName} "
                        . "{$closedBy->lastName} at "
                        . "{$closedAt}."
                )
        );
}

public function ticketCancelledForManagement(
    Ticket $ticket,
    User $cancelledBy,
    string $reason
): Collection {
    $cancelledAt = $ticket->cancelledAt
        ?->format('d/m/Y H:i:s');

    return $this->managementRecipients($cancelledBy)
        ->map(
            fn (User $recipient): Notification =>
                $this->send(
                    $recipient,
                    $ticket,
                    'ticket_cancelled_management',
                    'Ticket cancelled',
                    "{$ticket->ticketNumber}: "
                        . "{$ticket->subject} was cancelled by "
                        . "{$cancelledBy->firstName} "
                        . "{$cancelledBy->lastName} at "
                        . "{$cancelledAt}. Reason: {$reason}"
                )
        );
}
}