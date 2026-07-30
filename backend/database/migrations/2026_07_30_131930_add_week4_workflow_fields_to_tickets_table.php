<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('startedAt')
                ->nullable()
                ->after('dueAt');
            $table->timestamp('escalatedAt')
                ->nullable()
                ->after('startedAt');
            $table->timestamp('cancelledAt')
                ->nullable()
                ->after('escalatedAt');

            $table->text('resolutionNote')
                ->nullable()
                ->after('cancelledAt');
            $table->text('escalationReason')
                ->nullable()
                ->after('resolutionNote');
            $table->text('cancellationReason')
                ->nullable()
                ->after('escalationReason');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn([
                'startedAt',
                'escalatedAt',
                'cancelledAt',
                'resolutionNote',
                'escalationReason',
                'cancellationReason',
            ]);
        });
    }
};