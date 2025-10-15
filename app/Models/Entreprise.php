<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entreprise extends Model
{
    protected $table = 'entreprises';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'nom_entreprise',
        'description',
        'site_web',
        'telephone',
        'email',
        'secteur_activite',
        'taille_entreprise',      // ✅ AJOUTÉ
        'adresse',                // ✅ AJOUTÉ
        'ville',                  // ✅ AJOUTÉ
        'logo',
        'pays_id',
        'statut',
        'motif_rejet',
    ];

    protected $casts = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'pays_id' => 'integer',
    ];

    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function pays(): BelongsTo 
    { 
        return $this->belongsTo(Pays::class); 
    }

    // Accesseurs pour compatibilité
    public function setSiteWebAttribute($value) 
    { 
        $this->attributes['site_web'] = $value; 
    }
    
    public function getSiteWebAttribute() 
    { 
        return $this->attributes['site_web'] ?? null; 
    }

    public function getMotifRejetAttribute($value) 
    {
        if ($value === null) return null;
        $v = trim((string)$value);
        return ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'undefined') ? null : $v;
    }
}