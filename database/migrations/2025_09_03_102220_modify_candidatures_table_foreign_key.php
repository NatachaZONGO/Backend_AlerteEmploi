<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('candidatures', function (Blueprint $table) {
        // Supprimer l'ancienne clé étrangère
        $table->dropForeign(['candidat_id']);
        
        // Recréer avec la bonne référence
        $table->foreign('candidat_id')->references('user_id')->on('candidats')->onDelete('cascade');
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
