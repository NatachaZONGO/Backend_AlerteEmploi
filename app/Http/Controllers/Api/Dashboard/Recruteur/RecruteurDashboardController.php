<?php

namespace App\Http\Controllers\Api\Dashboard\Recruteur;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecruteurDashboardController extends Controller
{
    /**
     * Affichage du tableau de bord recruteur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $entreprise = $user->entreprise;

        // Vérification du statut de l'entreprise
        if ($entreprise->statut !== 'valide') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte entreprise est en attente de validation',
                'data' => [
                    'statut' => $entreprise->statut,
                    'entreprise' => $entreprise
                ]
            ], 403);
        }

        // Statistiques du recruteur
        $stats = [
            'offres_publiees' => $user->offres()->where('statut', 'publiee')->count(),
            'candidatures_recues' => 0, // À implémenter avec le système de candidatures
            'entretiens_programmes' => 0, // À implémenter
            'offres_actives' => $user->offres()->where('statut', 'publiee')
                ->where('date_expiration', '>=', now())->count(),
            'total_offres' => $user->offres()->count(),
            'offres_brouillon' => $user->offres()->where('statut', 'brouillon')->count(),
            'offres_en_attente_validation' => $user->offres()->where('statut', 'en_attente_validation')->count(),
            'offres_validees' => $user->offres()->where('statut', 'validee')->count(),
            'offres_rejetees' => $user->offres()->where('statut', 'rejetee')->count(),
        ];

        // Dernières offres créées
        $dernieres_offres = $user->offres()
            ->with(['categorie'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load('roles'),
                'entreprise' => $entreprise,
                'stats' => $stats,
                'dernieres_offres' => $dernieres_offres,
                'message' => 'Bienvenue sur votre tableau de bord recruteur'
            ]
        ]);
    }
}