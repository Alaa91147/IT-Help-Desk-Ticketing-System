<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ticketassignments',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('previousAssignedUserId')
                    ->nullable()
                    ->after('ticketId');
                $table->string('assignmentType', 30)
                    ->default('assigned')
                    ->after('assignedByUserId');
                $table->text('reason')
                    ->nullable()
                    ->after('assignmentType');

                $table->foreign('previousAssignedUserId')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('previousAssignedUserId');
                $table->index('assignmentType');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ticketassignments',
            function (Blueprint $table): void {
                $table->dropForeign([
                    'previousAssignedUserId',
                ]);
                $table->dropIndex([
                    'previousAssignedUserId',
                ]);
                $table->dropIndex([
                    'assignmentType',
                ]);
                $table->dropColumn([
                    'previousAssignedUserId',
                    'assignmentType',
                    'reason',
                ]);
            }
        );
    }
};