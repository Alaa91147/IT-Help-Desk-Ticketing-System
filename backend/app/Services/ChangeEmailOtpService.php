<?php

namespace App\Services;

use App\Mail\ChangeEmailOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ChangeEmailOtpService
{
    private const CACHE_MINUTES = 10;

    public function generateAndSend(User $user, string $newEmail): void
    {
        $otp = (string) random_int(100000, 999999);

        Cache::put(
            "change-email-{$user->id}",
            [
                'email' => $newEmail,
                'otp' => Hash::make($otp),
            ],
            now()->addMinutes(self::CACHE_MINUTES)
        );

        Mail::to($newEmail)->send(
            new ChangeEmailOtpMail($otp)
        );
    }

    public function verify(User $user, string $otp): string
    {
        $cache = Cache::get("change-email-{$user->id}");

        if (!$cache) {
            throw ValidationException::withMessages([
                'otp' => ['OTP expired. Please request a new one.'],
            ]);
        }

        if (!Hash::check($otp, $cache['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        }

        Cache::forget("change-email-{$user->id}");

        return $cache['email'];
    }
}