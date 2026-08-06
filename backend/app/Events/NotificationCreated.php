<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Notification $notification
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'App.Models.User.'.$this->notification->userId
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'userId' => $this->notification->userId,
                'ticketId' => $this->notification->ticketId,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'isRead' => $this->notification->isRead,
                'readAt' => $this->notification->readAt,
                'createdAt' =>
                    $this->notification->createdAt?->toISOString(),
            ],
        ];
    }
}