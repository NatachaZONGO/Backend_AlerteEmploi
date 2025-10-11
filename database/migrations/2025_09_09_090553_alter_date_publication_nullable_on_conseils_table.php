<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conseils', function (Blueprint $table) {
            $table->dateTime('date_publication')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('conseils', function (Blueprint $table) {
            // si tu veux revenir à NOT NULL (à adapter selon ton ancienne définition)
            // $table->dateTime('date_publication')->nullable(false)->change();
        });
    }
};
