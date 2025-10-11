<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lettre_motivation' => $this->lettre_motivation,
            'cv' => $this->cv,
            'statut' => $this->statut,
            'date_postulation' => $this->created_at->format('Y-m-d H:i:s'),

            'candidat' => [
                'nom' => $this->candidat->user->nom ?? null,
                'prenom' => $this->candidat->user->prenom ?? null,
                'email' => $this->candidat->user->email ?? null,
                'telephone' => $this->candidat->user->telephone ?? null,
                'sexe' => $this->candidat->sexe ?? null,
                'date_naissance' => $this->candidat->date_naissance ?? null,
                'ville' => $this->candidat->ville ?? null,
                'niveau_etude' => $this->candidat->niveau_etude ?? null,
                'disponibilite' => $this->candidat->disponibilite ?? null,
            ],

            'offre' => [
                'id' => $this->offre->id,
                'titre' => $this->offre->titre,
                'description' => $this->offre->description,
                'experience' => $this->offre->experience,
                'localisation' => $this->offre->localisation,
                'type_offre' => $this->offre->type_offre,
                'type_contrat' => $this->offre->type_contrat,
                'date_publication' => $this->offre->date_publication,
                'date_expiration' => $this->offre->date_expiration,
                'salaire' => $this->offre->salaire,
            ]
        ];
    }
}
