<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            // Vérifier si les colonnes n'existent pas déjà avant de les ajouter
            if (!Schema::hasColumn('entreprises', 'adresse')) {
                $table->string('adresse')->nullable()->after('email');
            }
            if (!Schema::hasColumn('entreprises', 'ville')) {
                $table->string('ville')->nullable()->after('adresse');
            }
            if (!Schema::hasColumn('entreprises', 'taille_entreprise')) {
                $table->string('taille_entreprise')->nullable()->after('secteur_activite');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['adresse', 'ville', 'taille_entreprise']);
        });
    }
};