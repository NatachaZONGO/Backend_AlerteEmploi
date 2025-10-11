<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Illuminate\Support\Collection; 

class Notification extends Model
{
    protected $fillable = [
        'titre',
        'message',
        'type',
        'mode',
        'destinataire',
        'destinataire_id',
        'priorite',
        'canaux',
        'statut',
        'declencheur',
        'declencheur_id',
        'donnees_contexte',
        'auteur_id',
        'nombre_destinataires',
        'nombre_lues',
        'criteres_ciblage',
        'tentatives_envoi',
        'erreur_envoi',
        'date_programmee',
        'date_envoi'
    ];

    protected $casts = [
        'canaux' => 'array',
        'donnees_contexte' => 'array',
        'criteres_ciblage' => 'array',
        'date_programmee' => 'datetime',
        'date_envoi' => 'datetime',
        'nombre_destinataires' => 'integer',
        'nombre_lues' => 'integer',
        'tentatives_envoi' => 'integer'
    ];

    // =================== RELATIONS ===================

    /**
     * Relation avec l'auteur (admin qui a créé la notification)
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /**
     * Relation avec les utilisateurs destinataires (via table pivot)
     */
    public function destinataires(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_users')
                    ->withPivot([
                        'date_envoi',
                        'date_lecture', 
                        'statut',
                        'canal_utilise',
                        'appareil'
                    ])
                    ->withTimestamps();
    }

    /**
     * Relation avec l'offre (si la notification est liée à une offre)
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class, 'declencheur_id')
                    ->where('declencheur', 'job_posted');
    }

    /**
     * Relation avec l'utilisateur cible spécifique
     */
    public function utilisateurCible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinataire_id');
    }

    // =================== SCOPES ===================

    /**
     * Scope pour les notifications automatiques
     */
    public function scopeAutomatiques($query)
    {
        return $query->where('mode', 'automatique');
    }

    /**
     * Scope pour les notifications manuelles
     */
    public function scopeManuelles($query)
    {
        return $query->where('mode', 'manuel');
    }

    /**
     * Scope pour les notifications programmées
     */
    public function scopeProgrammees($query)
    {
        return $query->where('mode', 'programmee');
    }

    /**
     * Scope pour les notifications envoyées
     */
    public function scopeEnvoyees($query)
    {
        return $query->where('statut', 'envoyee');
    }

    /**
     * Scope pour les notifications en brouillon
     */
    public function scopeBrouillons($query)
    {
        return $query->where('statut', 'brouillon');
    }

    /**
     * Scope pour les notifications récentes
     */
    public function scopeRecentes($query, $heures = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($heures));
    }

    /**
     * Scope pour les notifications par type
     */
    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour les notifications par déclencheur
     */
    public function scopeParDeclencheur($query, $declencheur)
    {
        return $query->where('declencheur', $declencheur);
    }

    /**
     * Scope pour les notifications par priorité
     */
    public function scopeParPriorite($query, $priorite)
    {
        return $query->where('priorite', $priorite);
    }

    /**
     * Scope pour les notifications prêtes à être envoyées (programmées)
     */
    public function scopePretesEnvoi($query)
    {
        return $query->where('statut', 'programmee')
                    ->where('date_programmee', '<=', now());
    }

    // =================== MÉTHODES UTILITAIRES ===================

    /**
     * Vérifie si la notification est automatique
     */
    public function estAutomatique(): bool
    {
        return $this->mode === 'automatique';
    }

    /**
     * Vérifie si la notification est manuelle
     */
    public function estManuelle(): bool
    {
        return $this->mode === 'manuel';
    }

    /**
     * Vérifie si la notification est programmée
     */
    public function estProgrammee(): bool
    {
        return $this->mode === 'programmee';
    }

    /**
     * Vérifie si la notification a été envoyée
     */
    public function estEnvoyee(): bool
    {
        return $this->statut === 'envoyee';
    }

    /**
     * Vérifie si la notification peut être envoyée
     */
    public function peutEtreEnvoyee(): bool
    {
        return in_array($this->statut, ['brouillon', 'programmee']);
    }

    /**
     * Vérifie si la notification peut être modifiée
     */
    public function peutEtreModifiee(): bool
    {
        return in_array($this->statut, ['brouillon', 'programmee']);
    }

    /**
     * Vérifie si la notification est récente (moins de 5 minutes)
     */
    public function estRecente(): bool
    {
        return $this->created_at >= now()->subMinutes(5);
    }

    /**
     * Obtient le taux de lecture
     */
    public function getTauxLecture(): float
    {
        if ($this->nombre_destinataires === 0) {
            return 0;
        }

        return round(($this->nombre_lues / $this->nombre_destinataires) * 100, 2);
    }

    /**
     * Marque la notification comme envoyée
     */
    public function marquerEnvoyee(): void
    {
        $this->update([
            'statut' => 'envoyee',
            'date_envoi' => now()
        ]);
    }

    /**
     * Marque la notification comme échouée
     */
    public function marquerEchouee(string $erreur): void
    {
        $this->update([
            'statut' => 'echec',
            'erreur_envoi' => $erreur,
            'tentatives_envoi' => $this->tentatives_envoi + 1
        ]);
    }

    /**
     * Incrémente le compteur de lectures
     */
    public function incrementerLectures(): void
    {
        $this->increment('nombre_lues');
    }

    /**
     * Met à jour le nombre de destinataires
     */
    public function mettreAJourNombreDestinataires(int $nombre): void
    {
        $this->update(['nombre_destinataires' => $nombre]);
    }

    /**
     * Obtient le label du type de notification
     */
    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'annonce_generale' => '📢 Annonce générale',
            'promotion' => '🎉 Promotion',
            'rapport' => '📊 Rapport',
            'maintenance' => '⚠️ Maintenance programmée',
            'inscription_recruteur' => '🏢 Nouvelle inscription recruteur',
            'inscription_candidat' => '👤 Nouvelle inscription candidat',
            'nouvelle_offre' => '💼 Nouvelle offre publiée',
            'candidature_recue' => '📝 Candidature reçue',
            'candidature_acceptee' => '✅ Candidature acceptée',
            'candidature_rejetee' => '❌ Candidature rejetée',
            'offre_expiree' => '⏰ Offre expirée',
            'conseil_publie' => '📰 Nouveau conseil publié',
            'connexion_suspecte' => '🔒 Tentative de connexion suspecte',
            'profil_maj' => '🔄 Mise à jour profil',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Obtient le label de la priorité
     */
    public function getPrioriteLabelAttribute(): string
    {
        $labels = [
            'urgente' => '🔴 Urgente',
            'normale' => '🟡 Normale',
            'basse' => '🔵 Basse'
        ];

        return $labels[$this->priorite] ?? $this->priorite;
    }

    /**
     * Obtient le label du statut
     */
    public function getStatutLabelAttribute(): string
    {
        $labels = [
            'brouillon' => '✏️ Brouillon',
            'programmee' => '⏰ Programmée',
            'envoyee' => '📤 Envoyée',
            'lue' => '✅ Lue',
            'echec' => '❌ Échec'
        ];

        return $labels[$this->statut] ?? $this->statut;
    }

    /**
     * Formate les canaux pour l'affichage
     */
    public function getCanauxFormatesAttribute(): string
    {
        if (!$this->canaux || empty($this->canaux)) {
            return '-';
        }

        $labels = [
            'app' => '🔔 App',
            'email' => '📧 Email',
            'sms' => '📱 SMS',
            'push' => '📲 Push'
        ];

        $canauxLabels = array_map(function($canal) use ($labels) {
            return $labels[$canal] ?? $canal;
        }, $this->canaux);

        return implode(', ', $canauxLabels);
    }

    /**
     * Obtient un résumé du message (pour les listes)
     */
    public function getResumeMessageAttribute(): string
    {
        if (strlen($this->message) <= 100) {
            return $this->message;
        }

        return substr($this->message, 0, 100) . '...';
    }

    /**
     * Vérifie si la notification contient des données de contexte spécifiques
     */
    public function aContexte(string $cle): bool
    {
        return isset($this->donnees_contexte[$cle]);
    }

    /**
     * Obtient une valeur du contexte
     */
    public function getContexte(string $cle, $defaut = null)
    {
        return $this->donnees_contexte[$cle] ?? $defaut;
    }

    // =================== ÉVÉNEMENTS DU MODÈLE ===================

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Événement lors de la création
        static::creating(function ($notification) {
            // Définir des valeurs par défaut si nécessaires
            if (!$notification->statut) {
                $notification->statut = 'brouillon';
            }
            
            if (!$notification->priorite) {
                $notification->priorite = 'normale';
            }
            
            if (!$notification->canaux) {
                $notification->canaux = ['app'];
            }
            
            if (!$notification->mode) {
                $notification->mode = 'manuel';
            }
        });
    }

    public function messages(){ return $this->hasMany(NotificationMessage::class); }
    public function lastMessage(){ return $this->hasOne(NotificationMessage::class)->latestOfMany(); }

    public static function pushToUsers(Collection|array $users, string $titre, string $message): self
    {
        $users = $users instanceof Collection ? $users : collect($users);

        // Valeurs par défaut pour rester compatible avec tes colonnes NOT NULL
        $notif = self::create([
            'titre'                 => $titre,
            'message'               => $message,
            'type'                  => 'info',            // valeur neutre
            'mode'                  => 'automatique',
            'destinataire'          => 'utilisateurs',
            'priorite'              => 'normale',
            'canaux'                => ['app'],
            'statut'                => 'envoyee',
            'date_envoi'            => now(),
            'date_programmee'       => null,
            'nombre_destinataires'  => $users->count(),
            'nombre_lues'           => 0,
        ]);

        if ($users->isNotEmpty()) {
            $attach = $users->pluck('id')->mapWithKeys(fn ($id) => [
                $id => [
                    'statut'     => 'envoyee',
                    'date_envoi' => now(),
                    'canal_utilise' => 'app',
                ],
            ])->all();

            $notif->destinataires()->attach($attach);
        }

        return $notif;
    }
}
