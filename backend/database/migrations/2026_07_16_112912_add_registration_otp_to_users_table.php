<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verificationCode', 64)
                ->nullable()
                ->after('emailVerifiedAt');

            $table->timestamp('verificationCodeExpiresAt')
                ->nullable()
                ->after('verificationCode');

            $table->timestamp('verificationCodeSentAt')
                ->nullable()
                ->after('verificationCodeExpiresAt');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verificationCode',
                'verificationCodeExpiresAt',
                'verificationCodeSentAt',
            ]);
        });
    }
};