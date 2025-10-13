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

    /** On expose un rôle “principal” dérivé de la relation roles */
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
        // ⚠️ Assure-toi que la table pivot s’appelle bien `role_user`
        // et qu’elle a les colonnes `user_id` et `role_id`.
        // Si ta table est différente (ex: roles_users), adapte les 2e/3e/4e paramètres :
        // return $this->belongsToMany(Role::class, 'roles_users', 'user_id', 'role_id');
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('nom', $role)->exists();
    }

    /** Accessor: rôle principal (1er rôle lié) */
    public function getRoleAttribute(): ?string
    {
        // Si la relation n’est pas chargée, on évite une requête en cascade
        $role = $this->relationLoaded('roles')
            ? $this->roles->first()
            : $this->roles()->first();

        return $role?->nom;
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

    public function entreprise(): HasOne
    {
        return $this->hasOne(Entreprise::class);
    }

    // Offres postées (recruteur)
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'recruteur_id');
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
