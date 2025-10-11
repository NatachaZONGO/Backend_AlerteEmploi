<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('candidatures', function (Blueprint $table) {
            // Supprimer toutes les contraintes existantes
            $table->dropForeign(['candidat_id']);
            
            // Recréer la contrainte pour référencer candidats.id
            $table->foreign('candidat_id')->references('id')->on('candidats')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('candidatures', function (Blueprint $table) {
            $table->dropForeign(['candidat_id']);
            $table->foreign('candidat_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};