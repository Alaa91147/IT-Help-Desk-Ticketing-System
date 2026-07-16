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
}