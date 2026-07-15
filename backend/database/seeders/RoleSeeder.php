<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(
            ['roleName' => 'Admin'],
            [
                'description' => 'Full system access',
                'isActive' => true,
            ]
        );

        Role::updateOrCreate(
            ['roleName' => 'SupportAgent'],
            [
                'description' => 'Handles and manages support tickets',
                'isActive' => true,
            ]
        );

        Role::updateOrCreate(
            ['roleName' => 'User'],
            [
                'description' => 'Creates and tracks support tickets',
                'isActive' => true,
            ]
        );
    }
}