<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketattachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticketId');
            $table->unsignedBigInteger('uploadedByUserId');

            $table->string('fileName');
            $table->string('filePath');
            $table->string('fileType')->nullable();
            $table->unsignedBigInteger('fileSize')->nullable();

            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('ticketId')
                ->references('id')
                ->on('tickets')
                ->cascadeOnDelete();

            $table->foreign('uploadedByUserId')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('ticketId');
            $table->index('uploadedByUserId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticketattachments');
    }
};