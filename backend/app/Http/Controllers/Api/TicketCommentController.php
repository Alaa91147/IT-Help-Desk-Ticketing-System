<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    private function roleName(User $user): ?string
    {
        return $user->loadMissing('role')->role?->roleName;
    }

    private function canView(User $user, Ticket $ticket): bool
    {
        return match ($this->roleName($user)) {
            'Admin', 'Manager' => true,
            'SupportAgent' => (int) $ticket->assignedUserId === (int) $user->id,
            'User' => (int) $ticket->userId === (int) $user->id,
            default => false,
        };
    }

    private function canParticipate(User $user, Ticket $ticket): bool
    {
        return match ($this->roleName($user)) {
            'Admin' => true,
            'SupportAgent' => (int) $ticket->assignedUserId === (int) $user->id,
            'User' => (int) $ticket->userId === (int) $user->id,
            default => false,
        };
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canView($user, $ticket)) {
            return $this->forbidden('You cannot view comments on this ticket.');
        }

        $comments = $ticket->comments()
            ->with('user.role')
            ->when(
                $this->roleName($user) === 'User',
                fn ($query) => $query->where('isInternal', false)
            )
            ->orderBy('createdAt')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$this->canParticipate($user, $ticket)) {
            return $this->forbidden('You cannot comment on this ticket.');
        }

        $ticket->loadMissing('status');

        if ($ticket->status?->statusName === 'Closed') {
            return response()->json([
                'success' => false,
                'message' => 'Comments cannot be added to a Closed ticket.',
            ], 422);
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'isInternal' => ['sometimes', 'boolean'],
        ]);

        $canCreateInternal = in_array(
            $this->roleName($user),
            ['Admin', 'SupportAgent'],
            true
        );

        $comment = $ticket->comments()->create([
            'userId' => $user->id,
            'comment' => $validated['comment'],
            'isInternal' => $canCreateInternal
                ? ($validated['isInternal'] ?? false)
                : false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => $comment->load('user.role'),
        ], 201);
    }

    public function update(
        Request $request,
        Ticket $ticket,
        TicketComment $comment
    ): JsonResponse {
        if ((int) $comment->ticketId !== (int) $ticket->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $isAdmin = $this->roleName($user) === 'Admin';

        if (!$isAdmin && (int) $comment->userId !== (int) $user->id) {
            return $this->forbidden(
                'Only the comment author or an Admin can edit this comment.'
            );
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'isInternal' => ['sometimes', 'boolean'],
        ]);

        if (!in_array($this->roleName($user), ['Admin', 'SupportAgent'], true)) {
            unset($validated['isInternal']);
        }

        $comment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'data' => $comment->fresh('user.role'),
        ]);
    }

    public function destroy(
        Request $request,
        Ticket $ticket,
        TicketComment $comment
    ): JsonResponse {
        if ((int) $comment->ticketId !== (int) $ticket->id) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $isAdmin = $this->roleName($user) === 'Admin';

        if (!$isAdmin && (int) $comment->userId !== (int) $user->id) {
            return $this->forbidden(
                'Only the comment author or an Admin can delete this comment.'
            );
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }
}