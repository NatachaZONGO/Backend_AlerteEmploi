<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            // Supprimer l'ancienne colonne statut
            $table->dropColumn('statut');
        });
        
        Schema::table('offres', function (Blueprint $table) {
            // Ajouter la nouvelle colonne statut avec les valeurs étendues
            $table->enum('statut', [
                'brouillon', 
                'en_attente_validation', 
                'validee', 
                'rejetee', 
                'publiee', 
                'expiree', 
                'fermee'
            ])->default('brouillon');
            
            // Ajouter seulement les index manquants
            $table->index(['recruteur_id', 'statut']);
            $table->index(['date_expiration', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            $table->dropIndex(['recruteur_id', 'statut']);
            $table->dropIndex(['date_expiration', 'statut']);
            $table->dropColumn('statut');
        });
        
        Schema::table('offres', function (Blueprint $table) {
            $table->enum('statut', ['brouillon', 'publiee', 'expiree', 'fermee'])->default('brouillon');
        });
    }
};