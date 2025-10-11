<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
            // Format: CAND-YEAR-XXXXXX (ex: CAND-2025-A1B2C3)
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
    public function offre()
    {
        return $this->belongsTo(Offre::class);
    }

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'candidat_id', 'id');
    }

    /**
     * Accesseurs pour le frontend
     */
    public function getFullNameAttribute()
    {
        $user = $this->candidat?->user;
        if ($user) {
            return trim($user->prenom . ' ' . $user->nom);
        }
        return null;
    }

    public function getEmailAttribute()
    {
        return $this->candidat?->user?->email;
    }

    public function getTelephoneAttribute()
    {
        return $this->candidat?->user?->telephone;
    }

    public function getOffreTitreAttribute()
    {
        return $this->offre?->titre;
    }

    public function getMotivationTextAttribute()
    {
        if (!empty($this->lettre_motivation) && !Str::startsWith($this->lettre_motivation, '[file]')) {
            return $this->lettre_motivation;
        }
        return null;
    }

    public function getCvDlAttribute()
    {
        return $this->cv ? url("api/candidatures/{$this->id}/download/cv") : null;
    }

    public function getLmDlAttribute()
    {
        // Vérifie s'il y a un fichier de lettre de motivation
        $hasFile = false;
        
        if (!empty($this->lettre_motivation_fichier)) {
            $hasFile = true;
        } elseif (!empty($this->lettre_motivation) && Str::startsWith($this->lettre_motivation, '[file]')) {
            $hasFile = true;
        }

        return $hasFile ? url("api/candidatures/{$this->id}/download/lm") : null;
    }
}