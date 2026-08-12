<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Raw AI metadata (model output, scores, etc.)
            $table->json('ai_metadata')->nullable()->after('description');

            // If AI suggests a category/priority, store FK to canonical tables
            $table->unsignedBigInteger('ai_category_id')->nullable()->after('ai_metadata');
            $table->unsignedBigInteger('ai_priority_id')->nullable()->after('ai_category_id');

            // Duplicate ticket reference
            $table->unsignedBigInteger('duplicate_of')->nullable()->after('ai_priority_id');

            // Agent request flow: who requested to take this ticket, and status
            $table->unsignedBigInteger('agent_requester_id')->nullable()->after('duplicate_of');
            $table->enum('agent_request_status', ['none','requested','accepted','rejected'])->default('none')->after('agent_requester_id');
            $table->timestamp('agent_requested_at')->nullable()->after('agent_request_status');

            $table->index('ai_category_id');
            $table->index('ai_priority_id');
            $table->index('duplicate_of');
            $table->index('agent_requester_id');

            $table->foreign('ai_category_id')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->foreign('ai_priority_id')
                ->references('id')
                ->on('priorities')
                ->restrictOnDelete();

            $table->foreign('duplicate_of')
                ->references('id')
                ->on('tickets')
                ->nullOnDelete();

            $table->foreign('agent_requester_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['ai_category_id']);
            $table->dropForeign(['ai_priority_id']);
            $table->dropForeign(['duplicate_of']);
            $table->dropForeign(['agent_requester_id']);

            $table->dropIndex(['ai_category_id']);
            $table->dropIndex(['ai_priority_id']);
            $table->dropIndex(['duplicate_of']);
            $table->dropIndex(['agent_requester_id']);

            $table->dropColumn([
                'ai_metadata',
                'ai_category_id',
                'ai_priority_id',
                'duplicate_of',
                'agent_requester_id',
                'agent_request_status',
                'agent_requested_at',
            ]);
        });
    }
};
