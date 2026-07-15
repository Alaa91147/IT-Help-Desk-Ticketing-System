<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register(
                RegisterData::fromRequest($request)
            );

            return ApiResponse::success(
                [
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                    'tokenType' => 'Bearer',
                ],
                'Registration successful.',
                201
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                'Registration failed.',
                null,
                500
            );
        }
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
}