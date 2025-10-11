<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            // 0 = normale, 1+ = vedette/premium
            $table->unsignedTinyInteger('sponsored_level')
                  ->default(0)
                  ->after('statut')
                  ->index();

            // Optionnel : durée de mise en avant
            $table->timestamp('featured_until')
                  ->nullable()
                  ->after('sponsored_level')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('offres', function (Blueprint $table) {
            $table->dropIndex(['sponsored_level']);
            $table->dropIndex(['featured_until']);
            $table->dropColumn(['sponsored_level', 'featured_until']);
        });
    }
};
