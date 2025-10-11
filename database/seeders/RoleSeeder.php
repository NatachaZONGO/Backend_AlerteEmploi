<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nom' => 'admin',
                'description' => 'Administrateur du système'
            ],
            [
                'nom' => 'candidat',
                'description' => 'Candidat à la recherche d\'emploi'
            ],
            [
                'nom' => 'recruteur',
                'description' => 'Recruteur qui publie des offres pour son entreprise'
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['nom' => $role['nom']],
                ['description' => $role['description']]
            );
        }
    }
}