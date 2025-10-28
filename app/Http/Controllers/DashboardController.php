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

            // ✅ NOUVEAU : Community Manager
            if (strcasecmp($roleName, 'community_manager') === 0) {
                return $this->communityManagerStats($user, $request);
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

            'top_entreprises' => [],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Statistiques RECRUTEUR (uniquement ses données)
     */
    private function recruteurStats(User $user)
    {
        $entreprise = $user->entreprise()->first();

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

        $totalOffres      = Offre::where('recruteur_id', $user->id)->count();
        $offresPubliees   = Offre::where('recruteur_id', $user->id)
                                ->where('statut', 'publiee')
                                ->where(function ($q) {
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

        $totalPublicites  = Publicite::where('entreprise_id', $entreprise->id)->count();

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
            'top_entreprises'        => [],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * ✅ NOUVEAU : Statistiques COMMUNITY MANAGER
     */
    private function communityManagerStats(User $user, Request $request)
    {
        // Récupérer toutes les entreprises gérables
        $entreprises = $user->getManageableEntreprises();
        
        // ✅ Si entreprise_id fourni, filtrer par cette entreprise uniquement
        if ($request->filled('entreprise_id')) {
            $entrepriseId = (int)$request->input('entreprise_id');
            
            // Vérifier que l'utilisateur peut gérer cette entreprise
            if (!$user->canManageEntreprise($entrepriseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette entreprise'
                ], 403);
            }
            
            $entreprises = $entreprises->where('id', $entrepriseId);
            \Log::info("Stats CM filtrées par entreprise $entrepriseId");
        }
        
        if ($entreprises->isEmpty()) {
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
        
        // ✅ Récupérer les user_id (recruteurs) de ces entreprises
        $recruteurIds = $entreprises->pluck('user_id')->filter()->unique();
        $entrepriseIds = $entreprises->pluck('id');
        
        // Stats des offres
        $totalOffres      = Offre::whereIn('recruteur_id', $recruteurIds)->count();
        $offresPubliees   = Offre::whereIn('recruteur_id', $recruteurIds)
                                ->where('statut', 'publiee')
                                ->where(function ($q) {
                                    if (Schema::hasColumn('offres', 'date_expiration')) {
                                        $q->where('date_expiration', '>', now());
                                    }
                                })
                                ->count();
        $offresEnAttente  = Offre::whereIn('recruteur_id', $recruteurIds)
                                ->where('statut', 'en_attente_validation')
                                ->count();
        $offresBrouillon  = Offre::whereIn('recruteur_id', $recruteurIds)
                                ->where('statut', 'brouillon')
                                ->count();

        // Stats des publicités
        $totalPublicites  = Publicite::whereIn('entreprise_id', $entrepriseIds)->count();

        // Stats des candidatures
        $candidBase = Candidature::whereHas('offre', function ($q) use ($recruteurIds) {
            $q->whereIn('recruteur_id', $recruteurIds);
        });

        $totalCands      = (clone $candidBase)->count();
        $candsEnCours    = (clone $candidBase)->whereIn('statut', ['en_attente', 'en_cours'])->count();
        $candsAcceptees  = (clone $candidBase)->where('statut', 'acceptee')->count();
        $candsRefusees   = (clone $candidBase)->where('statut', 'refusee')->count();

        // Offres récentes
        $recentOffres = Offre::whereIn('recruteur_id', $recruteurIds)
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
            'top_entreprises'        => [],
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Statistiques CANDIDAT
     */
    private function candidatStats(User $user)
    {
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