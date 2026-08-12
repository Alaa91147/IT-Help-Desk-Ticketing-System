<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TicketReportService
{
    public function query(Request $request, User $user): Builder
    {
        $filters = $request->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'statusId' => ['nullable', 'integer', 'exists:statuses,id'],
            'priorityId' => ['nullable', 'integer', 'exists:priorities,id'],
            'categoryId' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $query = Ticket::query()
            ->with([
                'user:id,firstName,lastName,email',
                'assignedUser:id,firstName,lastName,email',
                'category',
                'priority',
                'status',
            ])
            ->when(
                $filters['dateFrom'] ?? null,
                fn (Builder $query, string $date) =>
                    $query->whereDate('createdAt', '>=', $date)
            )
            ->when(
                $filters['dateTo'] ?? null,
                fn (Builder $query, string $date) =>
                    $query->whereDate('createdAt', '<=', $date)
            )
            ->when(
                $filters['statusId'] ?? null,
                fn (Builder $query, int $id) =>
                    $query->where('statusId', $id)
            )
            ->when(
                $filters['priorityId'] ?? null,
                fn (Builder $query, int $id) =>
                    $query->where('priorityId', $id)
            )
            ->when(
                $filters['categoryId'] ?? null,
                fn (Builder $query, int $id) =>
                    $query->where('categoryId', $id)
            );

        // Support agents can only export their own assigned tickets.
        if ($user->role?->roleName === 'SupportAgent') {
            $query->where('assignedUserId', $user->id);
        }

        return $query->orderByDesc('createdAt');
    }
}