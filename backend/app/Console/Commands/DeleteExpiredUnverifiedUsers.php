<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DeleteExpiredUnverifiedUsers extends Command
{
    protected $signature = 'users:delete-expired-unverified';

    protected $description =
        'Delete users who did not verify their email within 24 hours';

    public function handle(): int
    {
        $deletedCount = User::query()
            ->whereNull('emailVerifiedAt')
            ->where('createdAt', '<=', now()->subHours(24))
            ->delete();

        $this->info(
            "{$deletedCount} expired unverified user(s) deleted."
        );

        return self::SUCCESS;
    }
}