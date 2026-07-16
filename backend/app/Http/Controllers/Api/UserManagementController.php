<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('role')
            ->orderByDesc('createdAt')
            ->get();

        return ApiResponse::success(
            UserResource::collection($users),
            'Users retrieved successfully.'
        );
    }

    public function activate(
        Request $request,
        User $user
    ): JsonResponse {
        $user->forceFill([
            'isActive' => true,
        ])->save();

        return ApiResponse::success(
            new UserResource($user->load('role')),
            'User activated successfully.'
        );
    }

    public function deactivate(
        Request $request,
        User $user
    ): JsonResponse {
        if ($request->user()->id === $user->id) {
            return ApiResponse::error(
                'You cannot deactivate your own account.',
                null,
                422
            );
        }

        $user->forceFill([
            'isActive' => false,
        ])->save();

        // Immediately revoke all active Sanctum tokens.
        $user->tokens()->delete();

        return ApiResponse::success(
            new UserResource($user->load('role')),
            'User deactivated successfully.'
        );
    }
}