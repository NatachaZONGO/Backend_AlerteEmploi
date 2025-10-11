<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('entreprises', 'motif_rejet')) {
            return; // la colonne existe déjà, on ne fait rien
        }

        Schema::table('entreprises', function (Blueprint $table) {
            $table->text('motif_rejet')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('entreprises', 'motif_rejet')) {
            Schema::table('entreprises', function (Blueprint $table) {
                $table->dropColumn('motif_rejet');
            });
        }
    }
};
