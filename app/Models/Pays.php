<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Pays extends Model
{
    protected $fillable = [
        'nom',
        'code_iso',
        'indicatif_tel',
        'flag_image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'id'        => 'integer',
    ];

    // 👉 On expose UNIQUEMENT ces alias côté API
    protected $appends = ['code','flagImage','isActive'];

    // 👉 On cache les colonnes SQL brutes pour éviter le “2 2” côté front
    protected $hidden = ['code_iso','flag_image','is_active','created_at','updated_at'];

    /* -----------------------
       Accessors (lecture API)
       ----------------------- */

    // code => mappe code_iso
    public function getCodeAttribute(): string
    {
        return strtoupper((string)($this->attributes['code_iso'] ?? ''));
    }

    // flagImage => URL absolue si on stocke dans storage, sinon retourne l’URL telle quelle
    public function getFlagImageAttribute(): ?string
    {
        $val = $this->attributes['flag_image'] ?? null;
        if (!$val) return null;
        if (preg_match('#^https?://#i', $val)) return $val;      // déjà URL externe
        return asset('storage/'.$val);                           // => http://127.0.0.1:8000/storage/flags/xxx.jpg
    }

    // isActive => mappe is_active
    public function getIsActiveAttribute(): bool
    {
        return (bool)($this->attributes['is_active'] ?? false);
    }

    /* -----------------------
       Mutators (écriture API)
       ----------------------- */

    // Permet de recevoir "code" côté front mais d’écrire "code_iso" en base
    public function setCodeAttribute($value): void
    {
        $this->attributes['code_iso'] = strtoupper((string)$value);
    }

    // Permet de recevoir "flagImage" côté front mais d’écrire "flag_image" en base
    // (NB: l’upload fichier est géré dans le contrôleur, ici on mappe juste une string)
    public function setFlagImageAttribute($value): void
    {
        $this->attributes['flag_image'] = $value;
    }

    // Permet de recevoir "isActive" côté front mais d’écrire "is_active" en base
    public function setIsActiveAttribute($value): void
    {
        $this->attributes['is_active'] = (bool)$value;
    }

    // Relations (si besoin)
    public function candidatProfiles()
    {
        return $this->hasMany(Candidat::class);
    }

    public function entreprises()
    {
        return $this->hasMany(Entreprise::class);
    }

     
}
