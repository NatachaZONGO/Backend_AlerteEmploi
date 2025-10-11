<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_video_dual_media_to_publicites.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            $table->string('video')->nullable()->after('image'); // chemin ou URL
            $table->enum('media_request', ['image','video','both'])->default('image')->after('type');
            $table->enum('media_effective', ['image','video','both'])->default('image')->after('media_request');

            $table->string('dual_unlock_code')->nullable()->after('media_effective');
            $table->timestamp('dual_unlocked_at')->nullable()->after('dual_unlock_code');

            // si tu veux lier à un paiement:
            $table->enum('payment_status', ['unpaid','paid','refunded'])->default('unpaid')->after('prix');
        });
    }

    public function down(): void
    {
        Schema::table('publicites', function (Blueprint $table) {
            $table->dropColumn([
                'video','media_request','media_effective',
                'dual_unlock_code','dual_unlocked_at','payment_status'
            ]);
        });
    }
};
