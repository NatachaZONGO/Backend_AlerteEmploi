<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Entreprise;
use App\Models\Offre;
use App\Models\Candidature;
use App\Models\Publicite;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        try {
            $user = $request->user();

            // Rôle principal via accessor "role" (ou fallback via relation)
            $roleName = $user->role ?? optional($user->roles()->first())->nom;

            if (strcasecmp($roleName, 'Recruteur') === 0) {
                return $this->recruteurStats($user);
            }

            if (strcasecmp($roleName, 'Candidat') === 0) {
                return $this->candidatStats($user);
            }

            // Par défaut: Administrateur
            return $this->adminStats();

        } catch (\Throwable $e) {
            \Log::error('Dashboard stats error', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne',
            ], 500);
        }
    }

    /**
     * Statistiques ADMIN
     */
    private function adminStats()
    {
        // users_online: ne calcule que si la colonne existe
        $usersOnline = 0;
        if (Schema::hasColumn('users', 'last_activity')) {
            $usersOnline = User::where('last_activity', '>=', Carbon::now()->subMinutes(5))->count();
        }

        $stats = [
            'total_users'          => User::count(),
            'users_online'         => $usersOnline,
            'total_entreprises'    => Entreprise::count(),

            'total_offres'         => Offre::count(),
            'offres_publiees'      => Offre::where('statut', 'publiee')->count(),
            'offres_en_attente'    => Offre::where('statut', 'en_attente_validation')->count(),
            'offres_brouillon'     => Offre::where('statut', 'brouillon')->count(),

            'total_candidatures'   => Candidature::count(),
            'candidatures_en_cours'=> Candidature::whereIn('statut', ['en_attente', 'en_cours'])->count(),
            'candidatures_acceptees'=> Candidature::where('statut', 'acceptee')->count(),
            'candidatures_refusees'=> Candidature::where('statut', 'refusee')->count(),

            'recent_offres' => Offre::with('entreprise')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($offre) {
                    return [
                        'titre'             => $offre->titre,
                        'entreprise'        => optional($offre->entreprise)->nom_entreprise ?? 'N/A',
                        'statut'            => $offre->statut,
                        'date_publication'  => $offre->date_publication,
                    ];
                })
                ->values(),

            // à remplir plus tard si tu veux des tops
            'top_entreprises' => [],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Statistiques RECRUTEUR (uniquement ses données)
     */
    private function recruteurStats(User $user)
    {
        // On passe par la relation si elle existe
        $entreprise = $user->entreprise;

        // Si le recruteur n’a pas encore d’entreprise validée/associée,
        // renvoyer des zéros plutôt qu’un 404 (évite le 500 côté front).
        if (!$entreprise) {
            $empty = [
                'total_offres'           => 0,
                'offres_publiees'        => 0,
                'offres_en_attente'      => 0,
                'offres_brouillon'       => 0,
                'total_publicites'       => 0,
                'total_candidatures'     => 0,
                'candidatures_en_cours'  => 0,
                'candidatures_acceptees' => 0,
                'candidatures_refusees'  => 0,
                'recent_offres'          => [],
            ];
            return response()->json(['success' => true, 'data' => $empty]);
        }

        // Offres du recruteur (selon ton schéma: recruteur_id = user.id)
        $totalOffres      = Offre::where('recruteur_id', $user->id)->count();
        $offresPubliees   = Offre::where('recruteur_id', $user->id)
                                ->where('statut', 'publiee')
                                ->where(function ($q) {
                                    // protège si la colonne n’existe pas
                                    if (Schema::hasColumn('offres', 'date_expiration')) {
                                        $q->where('date_expiration', '>', now());
                                    }
                                })
                                ->count();
        $offresEnAttente  = Offre::where('recruteur_id', $user->id)
                                ->where('statut', 'en_attente_validation')
                                ->count();
        $offresBrouillon  = Offre::where('recruteur_id', $user->id)
                                ->where('statut', 'brouillon')
                                ->count();

        // Publicités de l’entreprise
        $totalPublicites  = Publicite::where('entreprise_id', $entreprise->id)->count();

        // Candidatures reçues sur SES offres
        $candidBase = Candidature::whereHas('offre', function ($q) use ($user) {
            $q->where('recruteur_id', $user->id);
        });

        $totalCands      = (clone $candidBase)->count();
        $candsEnCours    = (clone $candidBase)->whereIn('statut', ['en_attente', 'en_cours'])->count();
        $candsAcceptees  = (clone $candidBase)->where('statut', 'acceptee')->count();
        $candsRefusees   = (clone $candidBase)->where('statut', 'refusee')->count();

        $recentOffres = Offre::where('recruteur_id', $user->id)
            ->withCount('candidatures')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($offre) {
                return [
                    'titre'             => $offre->titre,
                    'statut'            => $offre->statut,
                    'date_publication'  => $offre->date_publication,
                    'candidatures_count'=> $offre->candidatures_count,
                ];
            })
            ->values();

        $stats = [
            'total_offres'           => $totalOffres,
            'offres_publiees'        => $offresPubliees,
            'offres_en_attente'      => $offresEnAttente,
            'offres_brouillon'       => $offresBrouillon,

            'total_publicites'       => $totalPublicites,

            'total_candidatures'     => $totalCands,
            'candidatures_en_cours'  => $candsEnCours,
            'candidatures_acceptees' => $candsAcceptees,
            'candidatures_refusees'  => $candsRefusees,

            'recent_offres'          => $recentOffres,
            'top_entreprises'        => [], // pas pertinent pour recruteur
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Statistiques CANDIDAT
     */
    private function candidatStats(User $user)
    {
        // Selon ton schéma : la table candidatures référence le candidat.
        // Si la FK est "candidat_id" pointant vers users.id => OK
        // Sinon, adapte (ex: via $user->candidat->id).
        $candidatId = $user->id;

        $totalCands      = Candidature::where('candidat_id', $candidatId)->count();
        $candsEnCours    = Candidature::where('candidat_id', $candidatId)->whereIn('statut', ['en_attente', 'en_cours'])->count();
        $candsAcceptees  = Candidature::where('candidat_id', $candidatId)->where('statut', 'acceptee')->count();
        $candsRefusees   = Candidature::where('candidat_id', $candidatId)->where('statut', 'refusee')->count();

        $recentOffres = Candidature::where('candidat_id', $candidatId)
            ->with('offre.entreprise')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($cand) {
                return [
                    'titre'            => optional($cand->offre)->titre ?? 'N/A',
                    'entreprise'       => optional(optional($cand->offre)->entreprise)->nom_entreprise ?? 'N/A',
                    'statut'           => $cand->statut,
                    'date_publication' => $cand->created_at,
                ];
            })
            ->values();

        $stats = [
            'total_candidatures'     => $totalCands,
            'candidatures_en_cours'  => $candsEnCours,
            'candidatures_acceptees' => $candsAcceptees,
            'candidatures_refusees'  => $candsRefusees,
            'recent_offres'          => $recentOffres,

            // pour garder le même shape que l’UI (non affichés pour candidat)
            'total_users'            => 0,
            'users_online'           => 0,
            'total_entreprises'      => 0,
            'total_offres'           => 0,
            'offres_publiees'        => 0,
            'offres_en_attente'      => 0,
            'offres_brouillon'       => 0,
            'total_publicites'       => 0,
            'top_entreprises'        => [],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
