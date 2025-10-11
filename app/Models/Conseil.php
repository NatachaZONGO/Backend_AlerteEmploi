<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Conseil extends Model
{
    protected $table = 'conseils';

    protected $fillable = [
        'titre',
        'contenu',
        'categorie',
        'type_conseil',
        'niveau',
        'statut',
        'tags',
        'auteur',
        'vues',
        'date_publication',
    ];

    protected $casts = [
        'date_publication' => 'datetime',
        'vues'             => 'integer',
    ];

    protected $attributes = [
        'type_conseil' => 'article',
        'niveau'       => 'debutant',
        'statut'       => 'brouillon',
        'vues'         => 0,
    ];

    /**
     * Hooks Eloquent : auto-remplit date_publication quand on publie
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if ($model->statut === 'publie' && empty($model->date_publication)) {
                $model->date_publication = now();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('statut') && $model->statut === 'publie' && empty($model->date_publication)) {
                $model->date_publication = now();
            }
        });
    }

    /** Conseils publiés (visibles) */
    public function scopePublie($query)
    {
        return $query
            ->where('statut', 'publie')
            ->whereNotNull('date_publication')
            ->where('date_publication', '<=', Carbon::now());
    }

    /** Récents (3 derniers mois par défaut) */
    public function scopeRecent($query, int $months = 3)
    {
        return $query->whereNotNull('date_publication')
                     ->where('date_publication', '>=', Carbon::now()->subMonths($months));
    }

    /** Recherche basique */
    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;

        return $query->where(function ($q) use ($term) {
            $q->where('titre', 'like', "%{$term}%")
              ->orWhere('contenu', 'like', "%{$term}%")
              ->orWhere('tags', 'like', "%{$term}%")
              ->orWhere('auteur', 'like', "%{$term}%");
        });
    }
}
