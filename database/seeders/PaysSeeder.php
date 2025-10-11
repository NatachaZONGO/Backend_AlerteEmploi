<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pays;

class PaysSeeder extends Seeder
{
    public function run(): void
    {
        $pays = [
            ['nom' => 'Sénégal', 'code_iso' => 'SN', 'indicatif_tel' => '+221'],
            ['nom' => 'Côte d\'Ivoire', 'code_iso' => 'CI', 'indicatif_tel' => '+225'],
            ['nom' => 'Mali', 'code_iso' => 'ML', 'indicatif_tel' => '+223'],
            ['nom' => 'Burkina Faso', 'code_iso' => 'BF', 'indicatif_tel' => '+226'],
            ['nom' => 'Maroc', 'code_iso' => 'MA', 'indicatif_tel' => '+212'],
            ['nom' => 'Tunisie', 'code_iso' => 'TN', 'indicatif_tel' => '+216'],
            ['nom' => 'Algérie', 'code_iso' => 'DZ', 'indicatif_tel' => '+213'],
            ['nom' => 'Niger', 'code_iso' => 'NE', 'indicatif_tel' => '+227'],
            ['nom' => 'Guinée', 'code_iso' => 'GN', 'indicatif_tel' => '+224'],
        ];

        foreach ($pays as $p) {
            Pays::create($p);
        }
    }
}