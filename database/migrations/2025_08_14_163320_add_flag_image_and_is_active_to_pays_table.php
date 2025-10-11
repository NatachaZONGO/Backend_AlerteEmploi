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
    Schema::table('pays', function (Blueprint $table) {
        $table->string('flag_image', 2000)->nullable()->after('code_iso'); // longueur augmentée
        $table->boolean('is_active')->default(true)->after('flag_image');
    });
}

public function down(): void
{
    Schema::table('pays', function (Blueprint $table) {
        $table->dropColumn(['flag_image', 'is_active']);
    });
}
};
