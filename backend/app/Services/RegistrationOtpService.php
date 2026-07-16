<?php

namespace App\Services;

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RegistrationOtpService
{
    private const OTP_EXPIRATION_MINUTES = 10;

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function generateAndSend(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'verificationCode' => Hash::make($otp),
            'verificationCodeExpiresAt' => now()->addMinutes(
                self::OTP_EXPIRATION_MINUTES
            ),
            'verificationCodeSentAt' => now(),
        ])->save();

        Mail::to($user->email)->send(
            new RegistrationOtpMail(
                firstName: $user->firstName,
                otp: $otp,
                expiresInMinutes: self::OTP_EXPIRATION_MINUTES,
            )
        );
    }

    public function verify(string $email, string $otp): User
    {
        $user = User::query()
            ->where('email', strtolower($email))
            ->firstOrFail();

        if ($user->emailVerifiedAt !== null) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already verified.'],
            ]);
        }

        if (
            !$user->verificationCode ||
            !$user->verificationCodeExpiresAt
        ) {
            throw ValidationException::withMessages([
                'otp' => ['No active verification code was found.'],
            ]);
        }

        if ($user->verificationCodeExpiresAt->isPast()) {
            throw ValidationException::withMessages([
                'otp' => [
                    'The verification code has expired. Request a new code.',
                ],
            ]);
        }

        if (!Hash::check($otp, $user->verificationCode)) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is incorrect.'],
            ]);
        }

        $user->forceFill([
            'emailVerifiedAt' => now(),
            'verificationCode' => null,
            'verificationCodeExpiresAt' => null,
            'verificationCodeSentAt' => null,
        ])->save();

        return $user->load('role');
    }

    public function resend(string $email): void
    {
        $user = User::query()
            ->where('email', strtolower($email))
            ->firstOrFail();

        if ($user->emailVerifiedAt !== null) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already verified.'],
            ]);
        }

        if (
            $user->verificationCodeSentAt &&
            $user->verificationCodeSentAt
                ->greaterThan(now()->subSeconds(
                    self::RESEND_COOLDOWN_SECONDS
                ))
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'Please wait before requesting another code.',
                ],
            ]);
        }

        $this->generateAndSend($user);
    }
}