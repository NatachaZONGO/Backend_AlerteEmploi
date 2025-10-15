<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Models\Offre;
use App\Models\User;

class Candidature extends Model
{
    protected $fillable = [
        'code',
        'offre_id',
        'candidat_id',
        'lettre_motivation',
        'lettre_motivation_fichier',
        'cv',
        'statut'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Génère un code unique pour la candidature
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = 'CAND-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Boot method pour générer automatiquement le code
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($candidature) {
            if (empty($candidature->code)) {
                $candidature->code = self::generateUniqueCode();
            }
        });
    }

    /**
     * Relations
     */
    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }

    /**
     * ✅ CORRECTION : candidat_id pointe vers users.id
     * Donc la relation doit être vers User, pas Candidat
     */
    public function candidat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'candidat_id', 'id');
    }

    /**
     * Accesseurs pour le frontend
     * Maintenant candidat() retourne directement un User
     */
    public function getFullNameAttribute(): ?string
    {
        $user = $this->candidat; // ✅ Directement un User
        if ($user) {
            return trim(($user->prenom ?? '') . ' ' . ($user->nom ?? ''));
        }
        return null;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->candidat?->email; // ✅ Directement user->email
    }

    public function getTelephoneAttribute(): ?string
    {
        return $this->candidat?->telephone; // ✅ Directement user->telephone
    }

    public function getOffreTitreAttribute(): ?string
    {
        return $this->offre?->titre;
    }

    public function getMotivationTextAttribute(): ?string
    {
        if (!empty($this->lettre_motivation) && !Str::startsWith($this->lettre_motivation, '[file]')) {
            return $this->lettre_motivation;
        }
        return null;
    }

    public function getCvDlAttribute(): ?string
    {
        return $this->cv ? url("api/candidatures/{$this->id}/download/cv") : null;
    }

    public function getLmDlAttribute(): ?string
    {
        $hasFile = false;
        
        if (!empty($this->lettre_motivation_fichier)) {
            $hasFile = true;
        } elseif (!empty($this->lettre_motivation) && Str::startsWith($this->lettre_motivation, '[file]')) {
            $hasFile = true;
        }

        return $hasFile ? url("api/candidatures/{$this->id}/download/lm") : null;
    }
}