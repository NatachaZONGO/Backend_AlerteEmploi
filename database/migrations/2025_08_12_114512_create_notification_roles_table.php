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
        Schema::create('notification_roles', function (Blueprint $table) {
            $table->id();
            
            // Informations de base
            $table->string('nom'); // Nom du rôle (ex: "Admin Notifications", "Gestionnaire Offres")
            $table->text('description')->nullable(); // Description détaillée du rôle
            
            // Configuration du déclenchement
            $table->string('evenement_declencheur'); // 'user_registration', 'job_posted', 'application_submitted', etc.
            $table->json('conditions')->nullable(); // Conditions pour déclencher (ex: type d'utilisateur, catégorie d'offre)
            
            // Template de notification
            $table->json('template_notification'); // Template avec titre, message, canaux, priorité
            
            // Ciblage des destinataires
            $table->json('destinataires_cibles'); // Qui doit recevoir cette notification
            $table->json('criteres_ciblage')->nullable(); // Critères avancés (catégories, localisations, etc.)
            
            // Configuration avancée
            $table->integer('delai_envoi_minutes')->default(0); // Délai avant envoi (0 = immédiat)
            $table->boolean('grouper_notifications')->default(false); // Grouper les notifications similaires
            $table->integer('limite_par_jour')->nullable(); // Limite d'envois par jour pour éviter le spam
            $table->integer('limite_par_utilisateur')->nullable(); // Limite par utilisateur
            
            // Gestion de l'état
            $table->boolean('actif')->default(true); // Le rôle est-il actif ?
            $table->integer('priorite_role')->default(100); // Priorité d'exécution (plus bas = plus prioritaire)
            
            // Métadonnées d'utilisation
            $table->integer('nombre_declenchements')->default(0); // Combien de fois ce rôle a été déclenché
            $table->timestamp('derniere_execution')->nullable(); // Dernière fois que le rôle a été exécuté
            $table->timestamp('date_derniere_modification')->nullable(); // Dernière modification du rôle
            
            // Créateur et modificateur
            $table->unsignedBigInteger('cree_par')->nullable(); // Admin qui a créé le rôle
            $table->unsignedBigInteger('modifie_par')->nullable(); // Admin qui a modifié le rôle
            
            // Timestamps Laravel
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['evenement_declencheur', 'actif']); // Recherche rapide des rôles actifs par événement
            $table->index(['actif', 'priorite_role']); // Tri par priorité des rôles actifs
            $table->index(['nombre_declenchements']); // Statistiques d'utilisation
            $table->index(['derniere_execution']); // Suivi temporel
            $table->index(['cree_par']); // Rôles par créateur
            
            // Clés étrangères
            $table->foreign('cree_par')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null'); // Si l'admin est supprimé, on garde le rôle
                  
            $table->foreign('modifie_par')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_roles');
    }
};