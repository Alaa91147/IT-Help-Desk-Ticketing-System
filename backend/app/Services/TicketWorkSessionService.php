<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketWorkSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketWorkSessionService
{
    public function start(
        Ticket $ticket,
        User $agent
    ): TicketWorkSession {
        return DB::transaction(function () use (
            $ticket,
            $agent
        ): TicketWorkSession {
            $activeSession = TicketWorkSession::query()
                ->where('ticketId', $ticket->id)
                ->whereNull('endedAt')
                ->lockForUpdate()
                ->first();

            if ($activeSession) {
                throw ValidationException::withMessages([
                    'workSession' => [
                        'This ticket already has an active work session.',
                    ],
                ]);
            }

            return TicketWorkSession::query()->create([
                'ticketId' => $ticket->id,
                'userId' => $agent->id,
                'startedAt' => now(),
                'endedAt' => null,
                'durationSeconds' => 0,
                'stopReason' => null,
            ]);
        });
    }

    public function stop(
        Ticket $ticket,
        User $agent,
        string $reason
    ): TicketWorkSession {
        return DB::transaction(function () use (
            $ticket,
            $agent,
            $reason
        ): TicketWorkSession {
            $session = TicketWorkSession::query()
                ->where('ticketId', $ticket->id)
                ->where('userId', $agent->id)
                ->whereNull('endedAt')
                ->lockForUpdate()
                ->latest('startedAt')
                ->first();

            if (!$session) {
                throw ValidationException::withMessages([
                    'workSession' => [
                        'There is no active work session for this agent.',
                    ],
                ]);
            }

            $endedAt = now();

            $durationSeconds = max(
                0,
                $session->startedAt->diffInSeconds(
                    $endedAt,
                    true
                )
            );

            $session->forceFill([
                'endedAt' => $endedAt,
                'durationSeconds' => $durationSeconds,
                'stopReason' => $reason,
            ])->save();

            return $session->fresh([
                'user',
                'ticket',
            ]);
        });
    }

    public function stopIfActive(
        Ticket $ticket,
        User $agent,
        string $reason
    ): ?TicketWorkSession {
        $hasActiveSession = TicketWorkSession::query()
            ->where('ticketId', $ticket->id)
            ->where('userId', $agent->id)
            ->whereNull('endedAt')
            ->exists();

        if (!$hasActiveSession) {
            return null;
        }

        return $this->stop(
            $ticket,
            $agent,
            $reason
        );
    }

    public function activeSession(
        Ticket $ticket
    ): ?TicketWorkSession {
        return TicketWorkSession::query()
            ->with('user.role')
            ->where('ticketId', $ticket->id)
            ->whereNull('endedAt')
            ->latest('startedAt')
            ->first();
    }

    public function totalEffectiveSeconds(
        Ticket $ticket
    ): int {
        $completedSeconds = (int) TicketWorkSession::query()
            ->where('ticketId', $ticket->id)
            ->sum('durationSeconds');

        $activeSession = TicketWorkSession::query()
            ->where('ticketId', $ticket->id)
            ->whereNull('endedAt')
            ->latest('startedAt')
            ->first();

        if (!$activeSession) {
            return $completedSeconds;
        }

        $activeSeconds = max(
            0,
            $activeSession->startedAt->diffInSeconds(
                now(),
                true
            )
        );

        return $completedSeconds + $activeSeconds;
    }

    public function distinctAgentCount(
        Ticket $ticket
    ): int {
        return TicketWorkSession::query()
            ->where('ticketId', $ticket->id)
            ->distinct()
            ->count('userId');
    }
}