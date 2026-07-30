<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'unreadOnly' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'max:100'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $query = $user->appNotifications()
            ->with([
                'ticket:id,ticketNumber,subject,statusId',
                'ticket.status:id,statusName',
            ])
            ->when(
                $filters['unreadOnly'] ?? false,
                fn ($builder) => $builder->where('isRead', false)
            )
            ->when(
                $filters['type'] ?? null,
                fn ($builder, string $type) =>
                    $builder->where('type', $type)
            )
            ->latest('createdAt');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($filters['perPage'] ?? 15),
            'unreadCount' => $user->appNotifications()
                ->where('isRead', false)
                ->count(),
        ]);
    }

    public function markAsRead(
        Request $request,
        Notification $notification
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ((int) $notification->userId !== (int) $user->id) {
            return $this->forbidden();
        }

        if (!$notification->isRead) {
            $notification->forceFill([
                'isRead' => true,
                'readAt' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification->fresh('ticket'),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatedCount = $user->appNotifications()
            ->where('isRead', false)
            ->update([
                'isRead' => true,
                'readAt' => now(),
                'updatedAt' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => [
                'updatedCount' => $updatedCount,
                'unreadCount' => 0,
            ],
        ]);
    }

    public function destroy(
        Request $request,
        Notification $notification
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ((int) $notification->userId !== (int) $user->id) {
            return $this->forbidden();
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You cannot access this notification.',
        ], 403);
    }
}