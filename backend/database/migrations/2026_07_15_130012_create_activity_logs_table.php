<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activitylogs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('userId')->nullable();
            $table->unsignedBigInteger('ticketId')->nullable();

            $table->string('action');
            $table->text('description');
            $table->json('oldValues')->nullable();
            $table->json('newValues')->nullable();
            $table->string('ipAddress', 45)->nullable();

            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('userId')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('ticketId')
                ->references('id')
                ->on('tickets')
                ->nullOnDelete();

            $table->index('userId');
            $table->index('ticketId');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitylogs');
    }
};