<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Carbon\Carbon;


class Offre extends Model
{
    protected $fillable = [
        'titre',
        'description',
        'experience',
        'localisation',
        'type_offre',
        'type_contrat',
        'statut',
        'date_publication',
        'date_expiration',
        'salaire',
        'recruteur_id',
        'categorie_id',
        'motif_rejet',
        'date_validation',
        'validee_par',
        'sponsored_level', 
        'featured_until',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'date_expiration' => 'date',
        'date_validation' => 'datetime',
        'salaire' => 'decimal:2',
    ];

    protected $appends = ['is_featured'];

    // Relations
    public function recruteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruteur_id');
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

   

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    // Scopes
    public function scopePubliee($query)
    {
        return $query->where('statut', 'publiee');
    }

    public function scopeValidee($query)
    {
        return $query->where('statut', 'validee');
    }

    public function scopeEnAttenteValidation($query)
    {
        return $query->where('statut', 'en_attente_validation');
    }

    public function scopeActive($query)
    {
        return $query->where('statut', 'publiee')
                    ->where('date_expiration', '>=', Carbon::today());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type_offre', $type);
    }

    public function scopeExpiree($query)
    {
        return $query->where('statut', 'expiree');
    }

    // Méthodes utilitaires
    public function isExpired(): bool
    {
        return $this->date_expiration < Carbon::today();
    }

    public function isActive(): bool
    {
        return $this->statut === 'publiee' && !$this->isExpired();
    }

    public function soumettreValidation(): void
    {
        $this->update(['statut' => 'en_attente_validation']);
    }

    public function valider($adminId): void
    {
        $this->update([
            'statut' => 'validee',
            'date_validation' => Carbon::now(),
            'validee_par' => $adminId,
            'motif_rejet' => null
        ]);
    }

    public function rejeter($adminId, $motif): void
    {
        $this->update([
            'statut' => 'rejetee',
            'date_validation' => Carbon::now(),
            'validee_par' => $adminId,
            'motif_rejet' => $motif
        ]);
    }

    public function publier(): void
    {
        $this->update([
            'statut' => 'publiee',
            'date_publication' => Carbon::today()
        ]);
    }

    public function fermer(): void
    {
        $this->update(['statut' => 'fermee']);
    }

    public function expirer(): void
    {
        $this->update(['statut' => 'expiree']);
    }

     // Publiée (adapte si tu as un autre champ/état)
    public function scopePublished($q) {
        return $q->where('statut', 'publiee');
    }

    // Vedettes actives
    public function scopeFeatured($q) {
        return $q->where('sponsored_level', '>', 0)
                 ->where(function($w){
                     $w->whereNull('featured_until')
                       ->orWhere('featured_until', '>=', now());
                 });
    }

    // Non vedettes (ou vedettes expirées)
    public function scopeNonFeatured($q) {
        return $q->where(function($w){
            $w->where('sponsored_level', 0)
              ->orWhere(function($x){
                  $x->where('sponsored_level', '>', 0)
                    ->where('featured_until', '<', now());
              });
        });
    }

    // Attribut calculé pour le front
    public function getIsFeaturedAttribute(): bool {
        if (($this->sponsored_level ?? 0) <= 0) return false;
        if (empty($this->featured_until))       return true;
        return $this->featured_until >= now();
    }

    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class, 'offre_id');
    }
    
    
    public function entreprise(): HasOne
    {
        return $this->hasOne(Entreprise::class, 'user_id', 'recruteur_id');
    }
}