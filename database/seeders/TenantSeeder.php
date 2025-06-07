<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User; // Assuming same User model works in tenant context
use Spatie\Permission\Models\Role;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Roles specific to tenant context
        $roles = ['Tenant Admin', 'Tenant Staff', 'Tenant Accountant'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web', // or 'tenant' if you're using a custom guard
            ]);
        }

        $users = [
            [
                'name' => 'Tenant Admin',
                'email' => 'admin@tenant.com',
                'password' => 'password',
                'role' => 'Tenant Admin',
            ],
            [
                'name' => 'Tenant Staff',
                'email' => 'staff@tenant.com',
                'password' => 'password',
                'role' => 'Tenant Staff',
            ],
            [
                'name' => 'Tenant Accountant',
                'email' => 'accountant@tenant.com',
                'password' => 'password',
                'role' => 'Tenant Accountant',
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

            $user->syncRoles([$userData['role']]);
        }
    }
}
