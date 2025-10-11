<?php

// app/Models/Entreprise.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entreprise extends Model
{
    protected $table = 'entreprises';

    // ✅ la table possède une colonne id => on l’utilise
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

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function pays(): BelongsTo { return $this->belongsTo(Pays::class); }

    // 🔁 Si tu reçois parfois siteWeb côté front, on le mappe vers site_web
    public function setSiteWebAttribute($value) { $this->attributes['site_web'] = $value; }
    public function getSiteWebAttribute() { return $this->attributes['site_web'] ?? null; }

    public function getMotifRejetAttribute($value) {
    if ($value === null) return null;
    $v = trim((string)$value);
    return ($v === '' || strtolower($v) === 'null' || strtolower($v) === 'undefined') ? null : $v;
}

 public function entreprise()
{
    return $this->belongsTo(Entreprise::class, 'entreprise_id', 'id');
}

}
