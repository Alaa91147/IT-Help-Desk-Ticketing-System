<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('roleName', 'Admin')->firstOrFail();
        $agentRole = Role::where('roleName', 'SupportAgent')->firstOrFail();
        $userRole = Role::where('roleName', 'User')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'roleId' => $adminRole->id,
                'firstName' => 'Admin',
                'lastName' => 'User',
                'phoneNumber' => null,
                'password' => Hash::make('Password123'),
                'isActive' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'agent@test.com'],
            [
                'roleId' => $agentRole->id,
                'firstName' => 'Support',
                'lastName' => 'Agent',
                'phoneNumber' => null,
                'password' => Hash::make('Password123'),
                'isActive' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@test.com'],
            [
                'roleId' => $userRole->id,
                'firstName' => 'Normal',
                'lastName' => 'User',
                'phoneNumber' => null,
                'password' => Hash::make('Password123'),
                'isActive' => true,
            ]
        );
    }
}