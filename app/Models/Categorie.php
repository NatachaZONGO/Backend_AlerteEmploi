<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categorie extends Model
{
    protected $fillable = ['nom', 'description'];

    // Relation supprimée car CandidatProfile n'existe pas

    public function candidats()
    {
        return $this->hasMany(Candidat::class);
    }

    public function offres()
    {
        return $this->hasMany(Offre::class);
    }
}
