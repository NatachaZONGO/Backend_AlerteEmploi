<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            // si la colonne existe déjà non nullable :
            $table->unsignedBigInteger('recruteur_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            $table->unsignedBigInteger('recruteur_id')->nullable(false)->change();
        });
    }
};
