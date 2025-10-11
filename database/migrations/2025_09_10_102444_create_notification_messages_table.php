<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_messages', function (Blueprint $table) {
            $table->id();

            // Conversation (notification) liée
            $table->foreignId('notification_id')
                  ->constrained('notifications')
                  ->cascadeOnDelete();

            // Expéditeur (peut être null pour messages système)
            $table->foreignId('sender_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Type de message
            $table->enum('type', ['text', 'image', 'file', 'system'])->default('text');

            // Contenu + métadonnées (pièces jointes, preview, etc.)
            $table->text('content')->nullable();
            $table->json('meta')->nullable();

            // Réponse/quote vers un autre message
            $table->foreignId('replied_to_id')
                  ->nullable()
                  ->constrained('notification_messages')
                  ->nullOnDelete();

            $table->timestamps();

            // Index utiles
            $table->index(['notification_id', 'created_at']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_messages');
    }
};
