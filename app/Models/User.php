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

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'password',
        'photo',
        'statut',
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
        ];
    }

    // ================== ROLES ==================

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('nom', $role)->exists();
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
    /**
     * Notifications MÉTIER (in-app) via table pivot `notification_users`.
     * Remplace la relation `notifications()` créée par le trait Notifiable.
     * Si tu utilises aussi les notifications Laravel par défaut,
     * utilise l'alias `laravelNotifications()` ci-dessous.
     */
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

    /**
     * Alias pour les notifications Laravel par défaut (Database Notifications),
     * afin d’éviter tout conflit de nom avec `notifications()` ci-dessus.
     * Utilisable si tu en as besoin ailleurs dans l’app.
     */
    public function laravelNotifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Notifications créées par cet utilisateur (auteur côté admin/backoffice).
     */
    public function notificationsCreees(): HasMany
    {
        return $this->hasMany(Notification::class, 'auteur_id');
    }
}
