<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categorie;

class CategorieSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nom' => 'Informatique', 'description' => 'Développement, IT, Systèmes'],
            ['nom' => 'Marketing', 'description' => 'Marketing digital, Communication'],
            ['nom' => 'Finance', 'description' => 'Comptabilité, Banque, Assurance'],
            ['nom' => 'Ressources Humaines', 'description' => 'RH, Recrutement'],
            ['nom' => 'Commercial', 'description' => 'Vente, Business Development'],
            ['nom' => 'Santé', 'description' => 'Médecine, Pharmacie, Soins'],
            ['nom' => 'Éducation', 'description' => 'Enseignement, Formation'],
            ['nom' => 'Ingénierie', 'description' => 'Génie civil, Mécanique, Électrique'],
        ];

        foreach ($categories as $categorie) {
            Categorie::create($categorie);
        }
    }
}