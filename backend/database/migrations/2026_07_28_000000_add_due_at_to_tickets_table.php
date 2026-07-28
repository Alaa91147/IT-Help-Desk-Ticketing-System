<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dateTime('dueAt')
                ->nullable()
                ->after('description');
        });

        $priorityNames = DB::table('priorities')
            ->pluck('priorityName', 'id');

        DB::table('tickets')
            ->select(['id', 'priorityId', 'createdAt'])
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($priorityNames): void {
                foreach ($tickets as $ticket) {
                    $priorityName = strtolower(
                        (string) ($priorityNames[$ticket->priorityId] ?? '')
                    );

                    $dueAt = Carbon::parse($ticket->createdAt);

                    match ($priorityName) {
                        'critical' => $dueAt->addHours(4),
                        'high' => $dueAt->addDay(),
                        'medium' => $dueAt->addDays(3),
                        'low' => $dueAt->addDays(5),
                        default => $dueAt->addDays(3),
                    };

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update(['dueAt' => $dueAt]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('dueAt');
        });
    }
};