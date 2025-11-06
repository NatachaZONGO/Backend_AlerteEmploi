<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /** On expose un rôle "principal" dérivé de la relation roles */
    protected $appends = ['role', 'role_id'];

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'password',
        'photo',
        'statut',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login' => 'datetime',
        ];
    }

    // ================== ROLES ==================

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('nom', 'LIKE', $role)->exists();
    }

    /** Accessor: rôle principal (1er rôle lié) */
    public function getRoleAttribute(): ?string
    {
        $role = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();

        return $role?->nom;
    }

    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('nom', $roleNames)->exists();
    }

    /** Accessor: id du rôle principal (1er rôle lié) */
    public function getRoleIdAttribute(): ?int
    {
        $role = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();

        return $role?->id;
    }

    // ================== PROFILS ==================

    public function candidat(): HasOne
    {
        return $this->hasOne(Candidat::class);
    }

    /**
     * Entreprise du recruteur (propriétaire)
     */
    public function entreprise()
    {
        return $this->hasOne(Entreprise::class, 'user_id');
    }

    /**
     * Un CM peut gérer plusieurs entreprises
     */
    public function entreprisesGerees(): BelongsToMany
    {
        return $this->belongsToMany(
            Entreprise::class,
            'community_manager_entreprises',
            'user_id',
            'entreprise_id'
        )->withTimestamps();
    }

    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'recruteur_id');
    }

    // ================== GESTION DES ENTREPRISES (RECRUTEUR + CM) ==================

    /**
     * ✅ Vérifier si l'utilisateur peut gérer une entreprise donnée
     * 
     * @param int $entrepriseId
     * @return bool
     */
    public function canManageEntreprise(int $entrepriseId): bool
    {
        // Cas 1 : Administrateur peut tout gérer
        if ($this->hasRole('administrateur') || $this->hasRole('Administrateur')) {
            return true;
        }

        // Cas 2 : Recruteur propriétaire de l'entreprise
        if ($this->hasRole('recruteur') || $this->hasRole('Recruteur')) {
            // ✅ CORRECTION : Vérifier via la relation ou requête
            return $this->entreprise()->where('id', $entrepriseId)->exists();
        }

        // Cas 3 : Community Manager assigné à cette entreprise
        if ($this->hasRole('community_manager')) {
            return $this->entreprisesGerees()->where('entreprises.id', $entrepriseId)->exists();
        }

        return false;
    }

    /**
     * ✅ Récupérer toutes les entreprises que l'utilisateur peut gérer
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getManageableEntreprises()
    {
        // Administrateur : toutes les entreprises
        if ($this->hasRole('administrateur') || $this->hasRole('Administrateur')) {
            return Entreprise::all();
        }

        // Recruteur : uniquement sa propre entreprise
        if ($this->hasRole('recruteur') || $this->hasRole('Recruteur')) {
            // ✅ CORRECTION : Charger la relation et retourner une collection
            $entreprise = $this->entreprise()->first();
            return $entreprise ? collect([$entreprise]) : collect();
        }

        // Community Manager : entreprises assignées
        if ($this->hasRole('community_manager')) {
            return $this->entreprisesGerees;
        }

        // Autres rôles : aucune entreprise
        return collect();
    }


    /**
     * ✅ Vérifier si l'utilisateur a au moins une entreprise à gérer
     * 
     * @return bool
     */
    public function hasManageableEntreprises(): bool
    {
        return $this->getManageableEntreprises()->isNotEmpty();
    }
    /**
     * ✅ Récupérer l'entreprise principale de l'utilisateur
     */
    public function getPrimaryEntreprise(): ?Entreprise
    {
        if ($this->hasRole('recruteur') || $this->hasRole('Recruteur')) {
            return $this->entreprise()->first();
        }

        if ($this->hasRole('community_manager')) {
            return $this->entreprisesGerees()->first();
        }

        return null;
    }

    // ================== NOTIFICATIONS ==================

    public function notifications(): BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_users')
            ->withPivot([
                'date_envoi',
                'date_lecture',
                'statut',
                'canal_utilise',
                'appareil',
                'navigateur',
                'ip_address',
                'marque_importante',
                'date_archivage',
                'note_utilisateur',
                'nombre_ouvertures',
                'temps_lecture_seconde',
                'action_effectuee',
            ])
            ->withTimestamps();
    }

    public function laravelNotifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    public function notificationsCreees(): HasMany
    {
        return $this->hasMany(Notification::class, 'auteur_id');
    }
}