<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketassignments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticketId');
            $table->unsignedBigInteger('assignedUserId');
            $table->unsignedBigInteger('assignedByUserId');

            $table->timestamp('assignedAt')->useCurrent();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('ticketId')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('assignedUserId')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('assignedByUserId')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index('ticketId');
            $table->index('assignedUserId');
            $table->index('assignedByUserId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketassignments');
    }
};