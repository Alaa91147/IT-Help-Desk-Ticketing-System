<?php

namespace App\Services;

use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class AuthService
{
    public function __construct(
        private readonly RegistrationOtpService $otpService
    ) {
    }

    public function register(RegisterData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $userRole = Role::query()
                ->where('roleName', 'User')
                ->where('isActive', true)
                ->first();

            if (!$userRole) {
                throw new RuntimeException(
                    'The default User role was not found.'
                );
            }

            $user = User::create([
                'roleId' => $userRole->id,
                'firstName' => $data->firstName,
                'lastName' => $data->lastName,
                'email' => strtolower($data->email),
                'phoneNumber' => $data->phoneNumber,
                'password' => Hash::make($data->password),

                // The account exists but cannot log in until OTP verification.
                'isActive' => true,
                'emailVerifiedAt' => null,
            ]);

            $this->otpService->generateAndSend($user);

            return $user->load('role');
        });
    }

    public function verifyRegistrationOtp(
        string $email,
        string $otp
    ): User {
        return $this->otpService->verify(
            strtolower($email),
            $otp
        );
    }

    public function resendRegistrationOtp(string $email): void
    {
        $this->otpService->resend(strtolower($email));
    }

    public function login(LoginData $data): array
    {
        $user = User::query()
            ->with('role')
            ->where('email', strtolower($data->email))
            ->first();

        if (
            !$user ||
            !Hash::check($data->password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided email or password is incorrect.',
                ],
            ]);
        }

        if (!$user->isActive) {
            throw ValidationException::withMessages([
                'email' => [
                    'This account is inactive.',
                ],
            ]);
        }

        if ($user->emailVerifiedAt === null) {
            throw ValidationException::withMessages([
                'email' => [
                    'Please verify your email before logging in.',
                ],
            ]);
        }

        if (!$user->role || !$user->role->isActive) {
            throw ValidationException::withMessages([
                'email' => [
                    'This account does not have an active role.',
                ],
            ]);
        }

        $token = $user
            ->createToken($data->deviceName)
            ->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }
    public function sendPasswordResetLink(string $email): void
{
    $status = Password::sendResetLink([
        'email' => strtolower($email),
    ]);

    /*
     * Do not reveal whether the email exists.
     * This prevents account-enumeration attacks.
     */
    if (
        $status !== Password::RESET_LINK_SENT &&
        $status !== Password::INVALID_USER
    ) {
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}

public function resetPassword(
    string $email,
    string $token,
    string $password
): void {
    $status = Password::reset(
        [
            'email' => strtolower($email),
            'password' => $password,
            'password_confirmation' => $password,
            'token' => $token,
        ],
        function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            /*
             * Revoke all existing Sanctum tokens after a password reset.
             */
            $user->tokens()->delete();

            event(new PasswordReset($user));
        }
    );

    if ($status !== Password::PASSWORD_RESET) {
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}
}