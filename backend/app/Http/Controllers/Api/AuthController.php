<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendRegistrationOtpRequest;
use App\Http\Requests\VerifyRegistrationOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function register(
        RegisterRequest $request
    ): JsonResponse {
        $user = $this->authService->register(
            RegisterData::fromRequest($request)
        );

        return ApiResponse::success(
            [
                'user' => new UserResource($user),
                'requiresEmailVerification' => true,
            ],
            'Registration successful. A verification code was sent to your email.',
            201
        );
    }

    public function verifyOtp(
        VerifyRegistrationOtpRequest $request
    ): JsonResponse {
        $user = $this->authService->verifyRegistrationOtp(
            $request->string('email')->toString(),
            $request->string('otp')->toString()
        );

        return ApiResponse::success(
            [
                'user' => new UserResource($user),
            ],
            'Email verified successfully. You can now log in.'
        );
    }

    public function resendOtp(
        ResendRegistrationOtpRequest $request
    ): JsonResponse {
        $this->authService->resendRegistrationOtp(
            $request->string('email')->toString()
        );

        return ApiResponse::success(
            null,
            'A new verification code was sent to your email.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            LoginData::fromRequest($request)
        );

        return ApiResponse::success(
            [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'tokenType' => 'Bearer',
            ],
            'Login successful.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return ApiResponse::success(
            new UserResource($user),
            'Authenticated user retrieved successfully.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(
            null,
            'Logout successful.'
        );
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user());

        return ApiResponse::success(
            null,
            'Logged out from all devices successfully.'
        );
    }
    public function forgotPassword(
    ForgotPasswordRequest $request
): JsonResponse {
    $this->authService->sendPasswordResetLink(
        $request->string('email')->toString()
    );

    return ApiResponse::success(
        null,
        'If an account exists for this email, a password-reset link has been sent.'
    );
}

public function resetPassword(
    ResetPasswordRequest $request
): JsonResponse {
    $this->authService->resetPassword(
        $request->string('email')->toString(),
        $request->string('token')->toString(),
        $request->string('password')->toString()
    );

    return ApiResponse::success(
        null,
        'Password reset successfully. You can now log in.'
    );
}
public function updateProfile(Request $request): JsonResponse
{
    $user = $request->user();

    $validated = $request->validate([
        'firstName' => ['required', 'string', 'max:255'],
        'lastName' => ['required', 'string', 'max:255'],

        'email' => [
            'required',
            'email',
            Rule::unique('users', 'email')->ignore($user->id),
        ],

        'phoneNumber' => ['nullable', 'string', 'max:30'],

        'currentPassword' => ['nullable', 'string'],

        'newPassword' => ['nullable', 'string', 'min:8'],

        'confirmPassword' => ['nullable', 'same:newPassword'],
    ]);

$user->firstName = $validated['firstName'];
$user->lastName = $validated['lastName'];
$user->phoneNumber = $validated['phoneNumber'] ?? null;

$emailChanged = $validated['email'] !== $user->email;

if (!$emailChanged) {
    $user->email = $validated['email'];
}

if (!empty($validated['newPassword'])) {

    if (
        !Hash::check(
            $validated['currentPassword'] ?? '',
            $user->password
        )
    ) {
        throw ValidationException::withMessages([
            'currentPassword' => [
                'Current password is incorrect.',
            ],
        ]);
    }

    $user->password = Hash::make($validated['newPassword']);
}

$user->save();

if ($emailChanged) {

    app(\App\Services\ChangeEmailOtpService::class)
        ->generateAndSend(
            $user,
            $validated['email']
        );

    return ApiResponse::success(
        [
            'user' => new UserResource($user->fresh()->load('role')),
            'requiresEmailOtp' => true,
        ],
        'Profile updated. Verify your new email to complete the email change.'
    );
}

return ApiResponse::success(
    new UserResource($user->fresh()->load('role')),
    'Profile updated successfully.'
);
}

public function verifyEmailChange(Request $request): JsonResponse
{
    $request->validate([
        'otp' => ['required', 'digits:6'],
    ]);

    $user = $request->user();

    $newEmail = app(\App\Services\ChangeEmailOtpService::class)
        ->verify(
            $user,
            $request->string('otp')->toString()
        );

    $user->email = $newEmail;
    $user->emailVerifiedAt = now();
    $user->save();

    return ApiResponse::success(
        new UserResource($user->fresh()->load('role')),
        'Email updated successfully.'
    );
}
}