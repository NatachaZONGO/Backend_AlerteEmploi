<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('candidats', function (Blueprint $table) {
            // 1. D'abord supprimer la clé primaire existante
            $table->dropPrimary(['user_id']);
        });
        
        // 2. Ensuite ajouter la nouvelle colonne id dans une deuxième opération
        Schema::table('candidats', function (Blueprint $table) {
            $table->id()->first();
            $table->unique('user_id'); // Garder user_id unique
        });
    }

    public function down()
    {
        Schema::table('candidats', function (Blueprint $table) {
            // Retirer l'index unique de user_id
            $table->dropUnique(['user_id']);
            // Supprimer la colonne id
            $table->dropColumn('id');
        });
        
        // Remettre user_id comme clé primaire
        Schema::table('candidats', function (Blueprint $table) {
            $table->primary('user_id');
        });
    }
};