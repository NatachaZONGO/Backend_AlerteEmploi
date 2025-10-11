<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicites', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('image'); // chemin ou URL
            $table->string('lien_externe')->nullable();
            $table->enum('type', ['banniere', 'sidebar', 'footer'])->default('banniere');
            $table->enum('statut', ['brouillon', 'en_attente', 'active', 'expiree', 'rejetee'])->default('brouillon');
            $table->enum('duree', ['3', '7', '14', '30', '60', '90']);
            $table->decimal('prix', 8, 2);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('vues')->default(0);
            $table->integer('clics')->default(0);
            $table->text('motif_rejet')->nullable();

            // ✅ FK correcte vers entreprises.id
            $table->foreignId('entreprise_id')
                  ->constrained('entreprises')   // réf. entreprises.id
                  ->cascadeOnDelete();

            $table->foreignId('validee_par')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('date_validation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicites');
    }
};
