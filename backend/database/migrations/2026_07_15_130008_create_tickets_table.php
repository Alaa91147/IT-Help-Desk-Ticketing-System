<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticketNumber')->unique();

            // User who created the ticket.
            $table->unsignedBigInteger('userId');

            // Support agent currently assigned to the ticket.
            $table->unsignedBigInteger('assignedUserId')->nullable();

            $table->unsignedBigInteger('categoryId');
            $table->unsignedBigInteger('priorityId');
            $table->unsignedBigInteger('statusId');

            $table->string('subject');
            $table->text('description');

            $table->timestamp('resolvedAt')->nullable();
            $table->timestamp('closedAt')->nullable();

            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('assignedUserId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('categoryId')
                ->references('id')
                ->on('categories')
                ->restrictOnDelete();

            $table->foreign('priorityId')
                ->references('id')
                ->on('priorities')
                ->restrictOnDelete();

            $table->foreign('statusId')
                ->references('id')
                ->on('statuses')
                ->restrictOnDelete();

            $table->index('userId');
            $table->index('assignedUserId');
            $table->index('categoryId');
            $table->index('priorityId');
            $table->index('statusId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};