<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class NotificationRole extends Model
{
    protected $fillable = [
        'nom',
        'description',
        'evenement_declencheur',
        'conditions',
        'template_notification',
        'destinataires_cibles',
        'criteres_ciblage',
        'delai_envoi_minutes',
        'grouper_notifications',
        'limite_par_jour',
        'limite_par_utilisateur',
        'actif',
        'priorite_role',
        'nombre_declenchements',
        'derniere_execution',
        'date_derniere_modification',
        'cree_par',
        'modifie_par'
    ];

    protected $casts = [
        'conditions' => 'array',
        'template_notification' => 'array',
        'destinataires_cibles' => 'array',
        'criteres_ciblage' => 'array',
        'actif' => 'boolean',
        'grouper_notifications' => 'boolean',
        'nombre_declenchements' => 'integer',
        'delai_envoi_minutes' => 'integer',
        'limite_par_jour' => 'integer',
        'limite_par_utilisateur' => 'integer',
        'priorite_role' => 'integer',
        'derniere_execution' => 'datetime',
        'date_derniere_modification' => 'datetime'
    ];

    // =================== RELATIONS ===================

    /**
     * Relation avec l'utilisateur qui a créé le rôle
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    /**
     * Relation avec l'utilisateur qui a modifié le rôle
     */
    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    // =================== SCOPES ===================

    /**
     * Scope pour les rôles actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour les rôles inactifs
     */
    public function scopeInactifs($query)
    {
        return $query->where('actif', false);
    }

    /**
     * Scope pour les rôles d'un événement spécifique
     */
    public function scopePourEvenement($query, string $evenement)
    {
        return $query->where('evenement_declencheur', $evenement);
    }

    /**
     * Scope pour trier par priorité
     */
    public function scopeParPriorite($query)
    {
        return $query->orderBy('priorite_role', 'asc');
    }

    /**
     * Scope pour les rôles les plus utilisés
     */
    public function scopePlusUtilises($query, int $limite = 10)
    {
        return $query->orderBy('nombre_declenchements', 'desc')->limit($limite);
    }

    /**
     * Scope pour les rôles récemment modifiés
     */
    public function scopeRecentesModifications($query, int $jours = 7)
    {
        return $query->where('date_derniere_modification', '>=', now()->subDays($jours));
    }

    // =================== MÉTHODES UTILITAIRES ===================

    /**
     * Vérifie si le rôle est actif
     */
    public function estActif(): bool
    {
        return $this->actif;
    }

    /**
     * Vérifie si le rôle peut être déclenché (en tenant compte des limites)
     */
    public function peutEtreDeclenche(): bool
    {
        if (!$this->estActif()) {
            return false;
        }

        // Vérifier la limite par jour
        if ($this->limite_par_jour && $this->getNombreExecutionsAujourdhui() >= $this->limite_par_jour) {
            return false;
        }

        return true;
    }

    /**
     * Obtient le nombre d'exécutions aujourd'hui
     */
    public function getNombreExecutionsAujourdhui(): int
    {
        return Notification::where('declencheur', $this->evenement_declencheur)
                          ->whereDate('created_at', today())
                          ->count();
    }

    /**
     * Vérifie si les conditions sont remplies pour des données spécifiques
     */
    public function conditionsSontRemplies(array $donnees): bool
    {
        if (!$this->conditions || empty($this->conditions)) {
            return true; // Pas de conditions = toujours déclencher
        }

        foreach ($this->conditions as $cle => $valeurAttendue) {
            $valeurDonnee = data_get($donnees, $cle);
            
            if ($valeurDonnee !== $valeurAttendue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Génère une notification basée sur ce rôle
     */
    public function genererNotification(array $donnees): array
    {
        $template = $this->template_notification;
        
        return [
            'titre' => $this->remplacerVariables($template['titre_template'] ?? '', $donnees),
            'message' => $this->remplacerVariables($template['message_template'] ?? '', $donnees),
            'type' => $this->determinerType(),
            'mode' => 'automatique',
            'destinataire' => $this->determinerDestinataire(),
            'destinataire_id' => $this->extraireDestinataireId($donnees),
            'priorite' => $template['priorite_defaut'] ?? 'normale',
            'canaux' => $template['canaux_defaut'] ?? ['app'],
            'statut' => $this->delai_envoi_minutes > 0 ? 'programmee' : 'envoyee',
            'declencheur' => $this->evenement_declencheur,
            'declencheur_id' => $donnees['id'] ?? null,
            'donnees_contexte' => $donnees,
            'criteres_ciblage' => $this->criteres_ciblage,
            'date_programmee' => $this->delai_envoi_minutes > 0 ? 
                now()->addMinutes($this->delai_envoi_minutes) : null
        ];
    }

    /**
     * Remplace les variables dans un template
     */
    private function remplacerVariables(string $template, array $donnees): string
    {
        $resultat = $template;
        
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);
        
        foreach ($matches[1] as $variable) {
            $valeur = data_get($donnees, trim($variable), '');
            $resultat = str_replace('{{' . $variable . '}}', $valeur, $resultat);
        }
        
        return $resultat;
    }

    /**
     * Détermine le type de notification selon l'événement
     */
    private function determinerType(): string
    {
        $mapping = [
            'user_registration' => 'inscription_candidat',
            'recruiter_registration' => 'inscription_recruteur',
            'job_posted' => 'nouvelle_offre',
            'application_submitted' => 'candidature_recue',
            'application_accepted' => 'candidature_acceptee',
            'application_rejected' => 'candidature_rejetee',
            'job_expired' => 'offre_expiree',
            'suspicious_login' => 'connexion_suspecte',
            'profile_updated' => 'profil_maj'
        ];

        return $mapping[$this->evenement_declencheur] ?? 'notification_generale';
    }

    /**
     * Détermine le destinataire selon la configuration
     */
    private function determinerDestinataire(): string
    {
        if (empty($this->destinataires_cibles)) {
            return 'tous';
        }

        if (count($this->destinataires_cibles) === 1) {
            return $this->destinataires_cibles[0];
        }

        $mapping = [
            'user_registration' => 'admins',
            'recruiter_registration' => 'admins',
            'job_posted' => 'candidats_categorie',
            'application_submitted' => 'utilisateur_specifique',
            'application_accepted' => 'utilisateur_specifique',
            'application_rejected' => 'utilisateur_specifique',
            'job_expired' => 'recruteurs',
            'suspicious_login' => 'admins'
        ];

        return $mapping[$this->evenement_declencheur] ?? 'tous';
    }

    /**
     * Extrait l'ID du destinataire spécifique depuis les données
     */
    private function extraireDestinataireId(array $donnees): ?int
    {
        switch ($this->evenement_declencheur) {
            case 'application_submitted':
            case 'application_accepted':
            case 'application_rejected':
                return $donnees['recruteur_id'] ?? null;
            
            default:
                return null;
        }
    }

    /**
     * Marque le rôle comme exécuté
     */
    public function marquerExecute(): void
    {
        $this->increment('nombre_declenchements');
        $this->update([
            'derniere_execution' => now()
        ]);
    }

    /**
     * Active le rôle
     */
    public function activer(): void
    {
        $this->update([
            'actif' => true,
            'date_derniere_modification' => now(),
            'modifie_par' => auth()->id()
        ]);
    }

    /**
     * Désactive le rôle
     */
    public function desactiver(): void
    {
        $this->update([
            'actif' => false,
            'date_derniere_modification' => now(),
            'modifie_par' => auth()->id()
        ]);
    }

    /**
     * Met à jour le rôle avec un nouvel utilisateur modificateur
     */
    public function mettreAJour(array $donnees): bool
    {
        $donnees['date_derniere_modification'] = now();
        $donnees['modifie_par'] = auth()->id();
        
        return $this->update($donnees);
    }

    /**
     * Obtient le taux de réussite du rôle
     */
    public function getTauxReussite(): float
    {
        if ($this->nombre_declenchements === 0) {
            return 0;
        }

        $notificationsReussies = Notification::where('declencheur', $this->evenement_declencheur)
                                            ->where('statut', 'envoyee')
                                            ->count();

        return round(($notificationsReussies / $this->nombre_declenchements) * 100, 2);
    }

    /**
     * Obtient le label de l'événement déclencheur
     */
    public function getEvenementLabelAttribute(): string
    {
        $labels = [
            'user_registration' => '👤 Inscription utilisateur',
            'recruiter_registration' => '🏢 Inscription recruteur',
            'job_posted' => '💼 Publication offre',
            'application_submitted' => '📝 Soumission candidature',
            'application_accepted' => '✅ Candidature acceptée',
            'application_rejected' => '❌ Candidature rejetée',
            'job_expired' => '⏰ Expiration offre',
            'suspicious_login' => '🔒 Connexion suspecte',
            'profile_updated' => '🔄 Modification profil'
        ];

        return $labels[$this->evenement_declencheur] ?? $this->evenement_declencheur;
    }

    /**
     * Obtient le statut formaté
     */
    public function getStatutFormateAttribute(): string
    {
        return $this->actif ? '✅ Actif' : '❌ Inactif';
    }

    /**
     * Obtient la description des destinataires
     */
    public function getDestinataireDescriptionAttribute(): string
    {
        if (empty($this->destinataires_cibles)) {
            return 'Aucun destinataire configuré';
        }

        $labels = [
            'tous' => '👥 Tous les utilisateurs',
            'candidats' => '👤 Candidats uniquement',
            'recruteurs' => '🏢 Recruteurs uniquement',
            'admins' => '👑 Administrateurs uniquement',
            'candidats_categorie' => '🎯 Candidats par catégorie',
            'utilisateur_specifique' => '👤 Utilisateur spécifique'
        ];

        $descriptions = array_map(function($dest) use ($labels) {
            return $labels[$dest] ?? $dest;
        }, $this->destinataires_cibles);

        return implode(', ', $descriptions);
    }

    // =================== ÉVÉNEMENTS DU MODÈLE ===================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($role) {
            $role->cree_par = auth()->id();
            $role->date_derniere_modification = now();
            
            if (!$role->priorite_role) {
                $role->priorite_role = 100;
            }
        });

        static::updating(function ($role) {
            $role->date_derniere_modification = now();
            
            if (!$role->modifie_par) {
                $role->modifie_par = auth()->id();
            }
        });
    }
}