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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Informations principales
            $table->string('titre');
            $table->text('message');
            $table->string('type'); // 'inscription_recruteur', 'nouvelle_offre', etc.
            
            // Mode et destinataire
            $table->enum('mode', ['manuel', 'automatique', 'programmee'])->default('manuel');
            $table->string('destinataire'); // 'tous', 'candidats', 'recruteurs', 'admins', etc.
            $table->unsignedBigInteger('destinataire_id')->nullable(); // Pour destinataire spécifique
            
            // Propriétés de la notification
            $table->enum('priorite', ['urgente', 'normale', 'basse'])->default('normale');
            $table->json('canaux'); // ['app', 'email', 'sms', 'push']
            $table->enum('statut', ['brouillon', 'programmee', 'envoyee', 'lue', 'echec'])->default('brouillon');
            
            // Champs pour notifications automatiques
            $table->string('declencheur')->nullable(); // 'user_registration', 'job_posted', etc.
            $table->string('declencheur_id')->nullable(); // ID de l'entité déclencheuse (offre_id, user_id, etc.)
            $table->json('donnees_contexte')->nullable(); // Données additionnelles pour personnaliser
            
            // Métadonnées et gestion
            $table->unsignedBigInteger('auteur_id')->nullable(); // Admin qui a créé (pour notifications manuelles)
            $table->integer('nombre_destinataires')->default(0); // Nombre total de destinataires
            $table->integer('nombre_lues')->default(0); // Nombre de personnes qui ont lu
            $table->json('criteres_ciblage')->nullable(); // Critères de ciblage avancés (catégories, localisations, etc.)
            
            // Gestion des erreurs et tentatives
            $table->integer('tentatives_envoi')->default(0);
            $table->text('erreur_envoi')->nullable(); // Message d'erreur en cas d'échec
            
            // Dates importantes
            $table->timestamp('date_programmee')->nullable(); // Pour les notifications programmées
            $table->timestamp('date_envoi')->nullable(); // Quand la notification a été envoyée
            
            // Timestamps Laravel (created_at, updated_at)
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index(['type', 'mode']); // Recherche par type et mode
            $table->index(['statut', 'created_at']); // Recherche par statut et date
            $table->index(['declencheur']); // Recherche par déclencheur pour stats
            $table->index(['destinataire']); // Recherche par type de destinataire
            $table->index(['priorite']); // Recherche par priorité
            $table->index(['date_programmee']); // Pour les notifications à envoyer
            $table->index(['created_at']); // Pour les requêtes chronologiques
            
            // Clé étrangère vers la table users (auteur)
            $table->foreign('auteur_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null'); // Si l'admin est supprimé, on garde la notification
            
            // Clé étrangère vers la table users (destinataire spécifique)
            $table->foreign('destinataire_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade'); // Si l'utilisateur est supprimé, on supprime ses notifications spécifiques
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};