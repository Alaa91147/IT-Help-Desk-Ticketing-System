<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ticketattachments',
            function (Blueprint $table): void {
                $table->foreignId('commentId')
                    ->nullable()
                    ->after('ticketId')
                    ->constrained('ticketcomments')
                    ->cascadeOnDelete();

                $table->index([
                    'ticketId',
                    'commentId',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ticketattachments',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'ticketId',
                    'commentId',
                ]);

                $table->dropConstrainedForeignId(
                    'commentId'
                );
            }
        );
    }
};