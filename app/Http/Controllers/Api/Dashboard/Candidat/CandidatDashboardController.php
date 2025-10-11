<?php

namespace App\Http\Controllers\Api\Dashboard\Candidat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidatDashboardController extends Controller
{
    /**
     * Affichage du tableau de bord candidat
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $candidat = $user->candidat;

        // Statistiques du candidat
        $stats = [
            'candidatures_envoyees' => 0, // À implémenter selon la logique
            'entretiens_programmes' => 0,
            'offres_vues' => 0,
            'profil_complete' => $this->isProfileComplete($candidat),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load('roles'),
                'candidat' => $candidat,
                'stats' => $stats,
                'message' => 'Bienvenue sur votre tableau de bord candidat'
            ]
        ]);
    }

    /**
     * Vérifier si le profil candidat est complet
     */
    private function isProfileComplete($candidat)
    {
        return $candidat && 
               $candidat->sexe && 
               $candidat->date_naissance && 
               $candidat->niveau_etude && 
               $candidat->ville;
    }
}