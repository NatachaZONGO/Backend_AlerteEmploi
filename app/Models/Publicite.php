<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Publicite extends Model
{
   protected $fillable = [
        'titre','description',
        'image','video',                     // ✅
        'lien_externe','type',
        'media_request','media_effective',   // ✅
        'dual_unlock_code','payment_status', // ✅
        'statut','duree','prix',
        'date_debut','date_fin',
        'vues','clics','motif_rejet',
        'entreprise_id','validee_par','date_validation',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_validation' => 'datetime',
        'prix' => 'decimal:2',
    ];

    protected $appends = ['image_url','video_url'];


    public function getImageUrlAttribute(): ?string {
        $img = $this->attributes['image'] ?? null;
        if(!$img) return null;
        return preg_match('#^https?://#i',$img) ? $img : asset('storage/'.$img);
    }

    public function getVideoUrlAttribute(): ?string { // ✅
        $vid = $this->attributes['video'] ?? null;
        if(!$vid) return null;
        return preg_match('#^https?://#i',$vid) ? $vid : asset('storage/'.$vid);
    }


    public static $tarifs = [
        '3'=>5000,'7'=>10000,'14'=>18000,'30'=>30000,'60'=>50000,'90'=>70000,
    ];

    public function entreprise(){ return $this->belongsTo(Entreprise::class, 'entreprise_id', 'id'); }
    public function validateur(){ return $this->belongsTo(User::class, 'validee_par'); }


    // Scopes
    public function scopeActive($query)
    {
        return $query->where('statut', 'active')
                    ->where('date_debut', '<=', Carbon::today())
                    ->where('date_fin', '>=', Carbon::today());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    // Méthodes utilitaires
    public function isActive(): bool
    {
        return $this->statut === 'active' 
            && $this->date_debut <= Carbon::today() 
            && $this->date_fin >= Carbon::today();
    }

    public function isExpired(): bool
    {
        return $this->date_fin < Carbon::today();
    }

    public function incrementVues(): void
    {
        $this->increment('vues');
    }

    public function incrementClics(): void
    {
        $this->increment('clics');
    }

    // Calculer prix et date fin selon durée
    public static function calculerPrixEtDateFin($duree, $dateDebut)
    {
        $prix = self::$tarifs[$duree] ?? 0;
        $dateFin = Carbon::parse($dateDebut)->addDays((int) $duree); // Cast en int
        
        return [
            'prix' => $prix,
            'date_fin' => $dateFin
        ];
    }

    

}



