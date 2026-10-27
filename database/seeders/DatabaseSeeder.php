<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Permissions, built-in roles and the two starter accounts.
     * Change these passwords after first login.
     */
    public function run(): void
    {
        Permissions::sync();

        // Starter accounts only on a fresh install, so re-seeding never brings back
        // default-password accounts after the real users have been set up.
        if (User::exists()) {
            return;
        }

        $superAdmin = Role::where('slug', Role::SUPER_ADMIN)->firstOrFail();
        $representative = Role::where('slug', 'company-representative')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@oilrsc.local'],
            [
                'name' => 'Portal Admin',
                'password' => 'Admin@123',
                'role_id' => $superAdmin->id,
                'designation' => 'E&P RSC',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'rep@oilrsc.local'],
            [
                'name' => 'Company Representative',
                'password' => 'Rep@123',
                'role_id' => $representative->id,
                'designation' => 'Block Operations',
                'is_active' => true,
            ]
        );
    }
}
