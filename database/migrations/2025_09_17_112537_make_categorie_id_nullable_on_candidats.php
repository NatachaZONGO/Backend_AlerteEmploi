<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ⚠️ require doctrine/dbal pour ->change()
        Schema::table('candidats', function (Blueprint $table) {
            $table->unsignedBigInteger('categorie_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('candidats', function (Blueprint $table) {
            $table->unsignedBigInteger('categorie_id')->nullable(false)->change();
        });
    }
};
