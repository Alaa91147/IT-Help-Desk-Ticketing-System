<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $urgentPriorityIds = DB::table('priorities')
            ->whereIn(
                DB::raw('LOWER(priorityName)'),
                ['urgent', 'critical']
            )
            ->pluck('id');

        if ($urgentPriorityIds->isEmpty()) {
            return;
        }

        DB::table('tickets')
            ->whereIn('priorityId', $urgentPriorityIds)
            ->select(['id', 'createdAt'])
            ->orderBy('id')
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update([
                            'dueAt' => Carbon::parse(
                                $ticket->createdAt
                            )->addHours(4),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // This is a data correction and is intentionally not reversed.
    }
};