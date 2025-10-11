<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conseils', function (Blueprint $table) {
            // Champs métiers manquants
            $table->string('categorie', 100)->nullable()->after('contenu');

            $table->enum('type_conseil', [
                'article', 'conseil_rapide', 'liste', 'video', 'infographie', 'checklist', 'template'
            ])->default('article')->after('categorie');

            $table->enum('niveau', [
                'debutant', 'intermediaire', 'avance', 'expert'
            ])->default('debutant')->after('type_conseil');

            $table->enum('statut', [
                'brouillon', 'en_revision', 'programme', 'publie', 'archive', 'suspendu'
            ])->default('brouillon')->after('niveau');

            $table->string('tags')->nullable()->after('statut');
            $table->string('auteur', 120)->nullable()->after('tags');

            $table->unsignedInteger('vues')->default(0)->after('auteur');

            // Index utiles (accélèrent les filtres courants)
            $table->index(['statut', 'date_publication']);
            $table->index(['categorie', 'type_conseil']);
        });
    }

    public function down(): void
    {
        Schema::table('conseils', function (Blueprint $table) {
            // Supprimer les index d'abord
            $table->dropIndex(['statut', 'date_publication']);
            $table->dropIndex(['categorie', 'type_conseil']);

            // Puis les colonnes
            $table->dropColumn([
                'categorie',
                'type_conseil',
                'niveau',
                'statut',
                'tags',
                'auteur',
                'vues',
            ]);
        });
    }
};
