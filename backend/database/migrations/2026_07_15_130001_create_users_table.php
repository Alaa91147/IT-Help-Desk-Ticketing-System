<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('roleId');

            $table->string('firstName');
            $table->string('lastName');
            $table->string('email')->unique();
            $table->string('phoneNumber')->nullable();
            $table->timestamp('emailVerifiedAt')->nullable();
            $table->string('password');
            $table->boolean('isActive')->default(true);

            // Laravel's authentication system uses this default column.
            $table->rememberToken();

            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->foreign('roleId')
                ->references('id')
                ->on('roles')
                ->restrictOnDelete();

            $table->index('roleId');
        });

        // Keep Laravel's internal naming for this table.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Keep Laravel's internal naming for this table.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};