<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Nodal officers from the client (27 Oct):
 *   Nodal officer 1: Subhayan Dasgupta  -> all ONHP blocks
 *   Nodal officer 2: Bhabagrahi Sahu    -> all UDWHP blocks
 * More nodal officers are added from the portal: Admin -> Users -> "Add Nodal Officer".
 *
 * Run: php artisan db:seed --class=NodalOfficerSeeder
 * Safe to run again: an existing account (also the earlier placeholder account) is updated,
 * its password is never reset, and its blocks are re-synced to every ONHP / UDWHP block.
 */
class NodalOfficerSeeder extends Seeder
{
    public const OFFICERS = [
        'ONHP' => [
            'name' => 'Subhayan Dasgupta',
            'email' => 'subhayan_dasgupta@oilindia.in',
            'phone' => '7002907403',
            'placeholder_email' => 'nodal.onhp@oil.local',
            'initial_password' => 'Onhp@2026',
        ],
        'UDWHP' => [
            'name' => 'Bhabagrahi Sahu',
            'email' => 'bhabagrahi.sahu@oilindia.in',
            'phone' => '9692987680',
            'placeholder_email' => 'nodal.udwhp@oil.local',
            'initial_password' => 'Udwhp@2026',
        ],
    ];

    public function run(): void
    {
        $role = Role::where('slug', 'company-representative')->firstOrFail();

        foreach (self::OFFICERS as $type => $officer) {
            $user = User::where('email', $officer['email'])->first()
                ?? User::where('email', $officer['placeholder_email'])->first()
                ?? new User(['password' => $officer['initial_password'], 'role_id' => $role->id, 'is_active' => true]);

            $user->fill([
                'name' => $officer['name'],
                'email' => $officer['email'],
                'phone' => $officer['phone'],
                'designation' => "Nodal Officer ({$type} Blocks)",
                'is_nodal_officer' => true,
            ])->save();

            $blocks = Block::where('code', 'like', "%-{$type}-%")->pluck('id');
            $user->blocks()->sync($blocks);

            $this->command?->info("{$user->name} <{$user->email}>: {$blocks->count()} {$type} blocks");
        }
    }
}
