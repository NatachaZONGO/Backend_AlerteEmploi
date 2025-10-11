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
            // Modifier l'enum statut pour inclure les nouveaux statuts
            $table->dropColumn('statut');
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->enum('statut', [
                'brouillon',
                'en_attente_validation',
                'validee',
                'publiee',
                'rejetee',
                'expiree',
                'fermee'
            ])->default('brouillon');

            // Ajouter des champs pour la validation admin
            $table->text('motif_rejet')->nullable();
            $table->timestamp('date_validation')->nullable();
            $table->foreignId('validee_par')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            $table->dropColumn(['motif_rejet', 'date_validation', 'validee_par']);
            $table->dropColumn('statut');
        });

        Schema::table('offres', function (Blueprint $table) {
            $table->enum('statut', ['brouillon', 'publiee', 'expiree', 'fermee'])->default('brouillon');
        });
    }
};
