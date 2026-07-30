<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * Return active Support Agents for ticket assignment.
     *
     * Only Managers and Admins can access this endpoint.
     */
    public function supportAgents(Request $request): JsonResponse
    {
        $currentUser = $request->user()->loadMissing('role');

        if (!in_array(
            $currentUser->role?->roleName,
            ['Manager', 'Admin'],
            true
        )) {
            return ApiResponse::error(
                'Only Managers and Admins can view Support Agents.',
                null,
                403
            );
        }

        $agents = User::query()
            ->with('role')
            ->where('isActive', true)
            ->whereHas('role', function ($query): void {
                $query->where('roleName', 'SupportAgent');
            })
            ->orderBy('firstName')
            ->orderBy('lastName')
            ->get();

        return ApiResponse::success(
            UserResource::collection($agents),
            'Active Support Agents retrieved successfully.'
        );
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($user->load('role')),
            'User retrieved successfully.'
        );
    }

    public function updateRole(
    Request $request,
    User $user
): JsonResponse {
    if ($request->user()->id === $user->id) {
        return ApiResponse::error(
            'You cannot change your own role.',
            null,
            422
        );
    }

    $validated = $request->validate([
        'roleName' => [
            'required',
            'string',
            'in:Admin,Manager,SupportAgent,User',
        ],
    ]);

    $role = Role::query()
        ->where('roleName', $validated['roleName'])
        ->where('isActive', true)
        ->first();

    if (!$role) {
        return ApiResponse::error(
            'The selected role is unavailable.',
            null,
            422
        );
    }

    $user->forceFill([
        'roleId' => $role->id,
    ])->save();

    // Force the user to log in again with the new permissions.
    $user->tokens()->delete();

    return ApiResponse::success(
        new UserResource($user->load('role')),
        'User role updated successfully.'
    );
}
public function update(Request $request, User $user): JsonResponse
{

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
    $user->email = $validated['email'];
    $user->phoneNumber = $validated['phoneNumber'] ?? null;

if (!empty($validated['newPassword'])) {

    if ($request->user()->id !== $user->id) {
        throw ValidationException::withMessages([
            'password' => [
                'You cannot change another user\'s password.',
            ],
        ]);
    }

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

    return ApiResponse::success(
        new UserResource($user->fresh()->load('role')),
        'User updated successfully.'
    );
}
}