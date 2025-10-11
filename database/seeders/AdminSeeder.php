<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@africarriere.com'],
            [
                'nom' => 'Admin',
                'prenom' => 'System',
                'telephone' => '+22656754833',
                'password' => Hash::make('password'),
                'statut' => 'actif',
            ]
        );

        $adminRole = Role::where('nom', 'admin')->first();
        if ($adminRole && !$admin->hasRole('admin')) {
            $admin->roles()->attach($adminRole);
        }
    }
}