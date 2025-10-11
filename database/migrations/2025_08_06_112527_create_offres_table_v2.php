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
        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description');
            $table->string('experience'); // Ex: "1-3 ans", "Débutant", "Senior"
            $table->string('localisation');
            $table->enum('type_offre', ['emploi', 'stage']);
            $table->string('type_contrat'); // Ex: "CDI", "CDD", "Freelance", "Stage"
            $table->enum('statut', ['brouillon', 'publiee', 'expiree', 'fermee'])->default('brouillon');
            $table->date('date_publication')->nullable();
            $table->date('date_expiration');
            $table->decimal('salaire', 10, 2)->nullable(); // Salaire optionnel
            
            // Relations
            $table->foreignId('recruteur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('categorie_id')->constrained('categories')->onDelete('cascade');
            
            $table->timestamps();
            
            // Index pour améliorer les performances de recherche
            $table->index(['statut', 'date_publication']);
            $table->index(['type_offre', 'localisation']);
            $table->index(['categorie_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offres');
    }
};
