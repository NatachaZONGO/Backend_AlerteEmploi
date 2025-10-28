<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'taille_entreprise',
        'adresse',
        'ville',
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

    /**
     * ✅ Les Community Managers assignés à cette entreprise
     */
    public function communityManagers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'community_manager_entreprises',
            'entreprise_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * ✅ Les offres de cette entreprise
     * Via le recruteur (user_id de l'entreprise = recruteur_id des offres)
     */
    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class, 'recruteur_id', 'user_id');
    }

    /**
     * ✅ Vérifier si un utilisateur peut gérer cette entreprise
     */
    public function canBeManagedBy(User $user): bool
    {
        return $user->canManageEntreprise($this->id);
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