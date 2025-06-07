<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class RoleAndUserSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Create roles if they don't exist
        $roles = ['Admin', 'Frontliner', 'Accountant'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'admin',
            ]);
        }

        // Step 2: Seed users and assign roles
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => 'password', // please hash responsibly
                'role' => 'Admin',
            ],
            [
                'name' => 'Frontliner User',
                'email' => 'frontliner@example.com',
                'password' => 'password',
                'role' => 'Frontliner',
            ],
            [
                'name' => 'Accountant User',
                'email' => 'accountant@example.com',
                'password' => 'password',
                'role' => 'Accountant',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                ]
            );

            $user->assignRole($userData['role']);
        }
    }
}
