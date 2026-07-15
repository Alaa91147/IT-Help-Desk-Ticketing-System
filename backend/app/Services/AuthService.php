<?php

namespace App\Services;

use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthService
{
    public function register(RegisterData $data): array
    {
        return DB::transaction(function () use ($data): array {
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
                'email' => $data->email,
                'phoneNumber' => $data->phoneNumber,
                'password' => Hash::make($data->password),
                'isActive' => true,
            ]);

            $token = $user
                ->createToken('react-frontend')
                ->plainTextToken;

            return [
                'user' => $user->load('role'),
                'token' => $token,
            ];
        });
    }

    public function login(LoginData $data): array
    {
        $user = User::query()
            ->with('role')
            ->where('email', $data->email)
            ->first();

        if (!$user || !Hash::check($data->password, $user->password)) {
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
}