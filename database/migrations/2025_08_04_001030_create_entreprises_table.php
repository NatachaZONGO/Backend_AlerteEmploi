<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id(); 
            $table->unsignedBigInteger('user_id');      // ✅ plus de primary ici
            $table->string('nom_entreprise');
            $table->text('description')->nullable();
            $table->string('site_web')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('secteur_activite')->nullable();
            $table->string('logo')->nullable();
            $table->foreignId('pays_id')->constrained()->onDelete('cascade');
            $table->enum('statut', ['en attente', 'valide', 'refuse'])->default('en attente');
            $table->text('motif_rejet')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
