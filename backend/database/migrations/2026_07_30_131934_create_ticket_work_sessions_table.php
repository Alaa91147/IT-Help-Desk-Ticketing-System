<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'ticketworksessions',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('ticketId');
                $table->unsignedBigInteger('userId');

                $table->timestamp('startedAt');
                $table->timestamp('endedAt')->nullable();
                $table->unsignedBigInteger('durationSeconds')
                    ->default(0);
                $table->string('stopReason', 50)->nullable();

                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')
                    ->useCurrent()
                    ->useCurrentOnUpdate();

                $table->foreign('ticketId')
                    ->references('id')
                    ->on('tickets')
                    ->cascadeOnDelete();

                $table->foreign('userId')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->index(['ticketId', 'endedAt']);
                $table->index(['userId', 'startedAt']);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketworksessions');
    }
};
