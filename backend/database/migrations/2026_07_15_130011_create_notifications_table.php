<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('ticketId')->nullable();

            $table->string('title');
            $table->text('message');
            $table->boolean('isRead')->default(false);
            $table->timestamp('readAt')->nullable();

            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('ticketId')
                ->references('id')
                ->on('tickets')
                ->nullOnDelete();

            $table->index(['userId', 'isRead']);
            $table->index('ticketId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};