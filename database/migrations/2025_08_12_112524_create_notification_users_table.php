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
        Schema::create('notification_users', function (Blueprint $table) {
            $table->id();
            
            // Relations principales (clés étrangères)
            $table->unsignedBigInteger('notification_id');
            $table->unsignedBigInteger('user_id');
            
            // Informations d'envoi
            $table->timestamp('date_envoi')->nullable(); // Quand la notification a été envoyée à cet utilisateur
            $table->timestamp('date_lecture')->nullable(); // Quand l'utilisateur a lu la notification
            
            // Statut pour cet utilisateur spécifique
            $table->enum('statut', ['envoyee', 'lue', 'archivee'])->default('envoyee');
            
            // Métadonnées d'envoi
            $table->string('canal_utilise')->nullable(); // Canal par lequel elle a été envoyée ('app', 'email', 'sms', 'push')
            $table->string('appareil')->nullable(); // Type d'appareil (mobile, desktop, email, etc.)
            $table->string('navigateur')->nullable(); // Informations du navigateur/app
            $table->ipAddress('ip_address')->nullable(); // IP de l'utilisateur lors de la lecture
            
            // Métadonnées supplémentaires
            $table->boolean('marque_importante')->default(false); // L'utilisateur a marqué comme important
            $table->timestamp('date_archivage')->nullable(); // Quand archivée par l'utilisateur
            $table->text('note_utilisateur')->nullable(); // Note personnelle de l'utilisateur
            
            // Suivi d'engagement
            $table->integer('nombre_ouvertures')->default(0); // Combien de fois ouverte
            $table->integer('temps_lecture_seconde')->nullable(); // Temps passé à lire (en secondes)
            $table->boolean('action_effectuee')->default(false); // L'utilisateur a effectué une action suite à la notification
            
            // Timestamps Laravel
            $table->timestamps();
            
            // Contraintes et index
            
            // Contrainte unique : un utilisateur ne peut avoir qu'une seule relation avec une notification
            $table->unique(['notification_id', 'user_id'], 'notification_user_unique');
            
            // Index pour les performances
            $table->index(['user_id', 'statut']); // Notifications d'un utilisateur par statut
            $table->index(['user_id', 'date_lecture']); // Notifications lues par utilisateur
            $table->index(['notification_id', 'statut']); // Statut des destinataires d'une notification
            $table->index(['date_envoi']); // Tri chronologique des envois
            $table->index(['date_lecture']); // Tri chronologique des lectures
            $table->index(['canal_utilise']); // Statistiques par canal
            $table->index(['appareil']); // Statistiques par appareil
            $table->index(['marque_importante']); // Notifications importantes pour l'utilisateur
            
            // Clés étrangères
            $table->foreign('notification_id')
                  ->references('id')
                  ->on('notifications')
                  ->onDelete('cascade'); // Si la notification est supprimée, supprimer toutes les relations
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); // Si l'utilisateur est supprimé, supprimer toutes ses notifications
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_users');
    }
};