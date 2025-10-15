<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Candidat extends Model
{
    

    protected $fillable = [
        'user_id', 
        'sexe', 
        'date_naissance', 
        'categorie_id', 
        'ville', 
        'niveau_etude', 
        'disponibilite', 
        'pays_id'
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    // Relation vers l'utilisateur de base
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // Relation vers les candidatures
    public function candidatures()
    {
        return $this->hasMany(Candidature::class, 'candidat_id');
    }

    // Relation vers la catégorie
    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function pays()
    {
        return $this->belongsTo(Pays::class);
    }
}