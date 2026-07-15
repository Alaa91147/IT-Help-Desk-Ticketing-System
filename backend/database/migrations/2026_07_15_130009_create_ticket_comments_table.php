<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketcomments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticketId');
            $table->unsignedBigInteger('userId');

            $table->text('comment');
            $table->boolean('isInternal')->default(false);

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
                ->cascadeOnDelete();

            $table->index('ticketId');
            $table->index('userId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketcomments');
    }
};