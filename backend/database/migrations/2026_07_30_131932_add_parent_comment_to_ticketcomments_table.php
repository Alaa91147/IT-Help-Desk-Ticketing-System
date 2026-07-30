<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ticketcomments',
            function (Blueprint $table): void {
                $table->unsignedBigInteger('parentCommentId')
                    ->nullable()
                    ->after('userId');

                $table->foreign('parentCommentId')
                    ->references('id')
                    ->on('ticketcomments')
                    ->nullOnDelete();

                $table->index('parentCommentId');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ticketcomments',
            function (Blueprint $table): void {
                $table->dropForeign(['parentCommentId']);
                $table->dropIndex(['parentCommentId']);
                $table->dropColumn('parentCommentId');
            }
        );
    }
};