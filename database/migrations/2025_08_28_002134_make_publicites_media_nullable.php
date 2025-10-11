<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            // rendre les deux colonnes NULLables
            $table->string('image')->nullable()->change();
            $table->string('video')->nullable()->change();

            // si besoin, on s’assure aussi que lien_externe est nullable
            // $table->string('lien_externe')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            $table->string('image')->nullable(false)->change();
            $table->string('video')->nullable(false)->change();
            // $table->string('lien_externe')->nullable(false)->change();
        });
    }
};
