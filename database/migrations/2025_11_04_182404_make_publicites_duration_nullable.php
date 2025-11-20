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
        Schema::table('publicites', function (Blueprint $table) {
            // Rendre ces champs nullable (accepter NULL)
            $table->integer('duree')->nullable()->change();
            $table->decimal('prix', 10, 2)->nullable()->change();
            $table->date('date_debut')->nullable()->change();
            $table->date('date_fin')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            // Revenir à NOT NULL si besoin de rollback
            $table->integer('duree')->nullable(false)->change();
            $table->decimal('prix', 10, 2)->nullable(false)->change();
            $table->date('date_debut')->nullable(false)->change();
            $table->date('date_fin')->nullable(false)->change();
        });
    }
};
