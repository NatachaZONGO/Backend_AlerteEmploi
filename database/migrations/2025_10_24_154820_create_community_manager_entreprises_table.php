<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_manager_entreprises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            // Un CM ne peut être assigné qu'une seule fois à une entreprise
            $table->unique(['user_id', 'entreprise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_manager_entreprises');
    }
};