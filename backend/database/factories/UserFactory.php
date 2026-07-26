<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        $userRole = Role::query()->firstOrCreate(
            ['roleName' => 'User'],
            [
                'description' => 'Creates and tracks support tickets',
                'isActive' => true,
            ]
        );

        return [
            'roleId' => $userRole->id,
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phoneNumber' => fake()->optional()->phoneNumber(),
            'emailVerifiedAt' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'isActive' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'emailVerifiedAt' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'isActive' => false,
        ]);
    }

    public function withRole(string $roleName): static
    {
        return $this->state(function () use ($roleName): array {
            $role = Role::query()
                ->where('roleName', $roleName)
                ->firstOrFail();

            return [
                'roleId' => $role->id,
            ];
        });
    }
}