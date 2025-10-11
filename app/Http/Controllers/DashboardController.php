<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Entreprise;
use App\Models\Offre;
use App\Models\Candidature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        // ---- Utilisateurs ----
        $totalUsers = User::count();

        // Utilisateurs "en ligne" (ex: tokens sanctum mis à jour < 10 min)
        // Adapte selon ton système d’auth :
        // - Si tu utilises laravel/sanctum: table personal_access_tokens.updated_at
        // - Sinon, si tu as une colonne users.last_activity, utilise-la.
        $onlineWindow = Carbon::now()->subMinutes(10);
        $usersOnline = DB::table('personal_access_tokens')
            ->where('updated_at', '>=', $onlineWindow)
            ->distinct('tokenable_id')
            ->count('tokenable_id');

        // ---- Entreprises ----
        $totalEntreprises = Entreprise::count();

        // ---- Offres ----
        $totalOffres     = Offre::count();
        $offresPubliees  = Offre::where('statut', 'publiee')->count();
        $offresEnAttente = Offre::where('statut', 'en_attente_validation')->count();
        $offresBrouillon = Offre::where('statut', 'brouillon')->count();

        // Offres récentes (5 dernières)
        $recentOffres = Offre::with(['entreprise' => function ($q) {
            $q->select('user_id', 'nom_entreprise'); // <-- user_id utilisé par la relation
            }])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($o) {
                return [
                    'id'               => $o->id,
                    'titre'            => $o->titre,
                    'entreprise'       => optional($o->entreprise)->nom_entreprise ?? '—',
                    'statut'           => $o->statut,
                    'date_publication' => optional($o->date_publication ?? $o->created_at)->toDateTimeString(),
                ];
            });



        // ---- Candidatures ----
        $totalCandidatures     = Candidature::count();
        $candidaturesEnCours   = Candidature::whereIn('statut', ['en_attente','en_cours','entretien'])->count();
        $candidaturesAcceptees = Candidature::where('statut', 'acceptee')->count();
        $candidaturesRefusees  = Candidature::where('statut', 'refusee')->count();

        // ---- Top entreprises par nb d’offres publiées (facultatif) ----
        $topEntreprises = Offre::select('entreprises.nom_entreprise', DB::raw('COUNT(offres.id) as nb_offres'))
            ->join('entreprises', 'entreprises.user_id', '=', 'offres.recruteur_id') // <-- ICI
            ->where('offres.statut', 'publiee')
            ->groupBy('entreprises.nom_entreprise')
            ->orderByDesc('nb_offres')
            ->limit(5)
            ->get()
            ->map(fn($r) => ['entreprise' => $r->nom_entreprise, 'nb_offres' => (int)$r->nb_offres]);

        return response()->json([
            'success' => true,
            // Users
            'total_users'       => $totalUsers,
            'users_online'      => $usersOnline,

            // Entreprises
            'total_entreprises' => $totalEntreprises,

            // Offres
            'total_offres'      => $totalOffres,
            'offres_publiees'   => $offresPubliees,
            'offres_en_attente' => $offresEnAttente,
            'offres_brouillon'  => $offresBrouillon,

            // Candidatures
            'total_candidatures'      => $totalCandidatures,
            'candidatures_en_cours'   => $candidaturesEnCours,
            'candidatures_acceptees'  => $candidaturesAcceptees,
            'candidatures_refusees'   => $candidaturesRefusees,

            // Listes
            'recent_offres'    => $recentOffres,
            'top_entreprises'  => $topEntreprises,
        ]);
    }
}
