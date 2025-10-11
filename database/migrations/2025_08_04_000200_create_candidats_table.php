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
        Schema::create('candidats', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->enum('sexe', ['Homme', 'Femme']);
            $table->date('date_naissance');
            $table->foreignId('categorie_id')->constrained()->onDelete('cascade');
            $table->string('ville');
            $table->string('niveau_etude');
            $table->string('disponibilite');
            $table->foreignId('pays_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidats');
    }
};
