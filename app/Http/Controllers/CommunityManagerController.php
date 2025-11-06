<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Entreprise;
use App\Models\Offre;
use App\Models\Candidature;
use App\Models\Publicite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityManagerController extends Controller
{
    /**
     * ✅ Récupérer les offres des entreprises gérées par le CM
     * Avec filtre optionnel par entreprise_id
     */
    public function getOffres(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux Community Managers'
            ], 403);
        }
        
        $entrepriseId = $request->query('entreprise_id');
        
        \Log::info('📋 CM récupère offres', [
            'user_id' => $user->id,
            'entreprise_id' => $entrepriseId
        ]);
        
        $entrepriseIds = $user->entreprisesGerees()->pluck('entreprises.id')->toArray();
        
        if (empty($entrepriseIds)) {
            \Log::info('⚠️ CM n\'a aucune entreprise assignée');
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        $query = Offre::with(['entreprise', 'categorie', 'recruteur', 'validateur'])
            ->whereIn('entreprise_id', $entrepriseIds);
        
        if ($entrepriseId && in_array($entrepriseId, $entrepriseIds)) {
            $query->where('entreprise_id', $entrepriseId);
            \Log::info('🔍 Filtrage par entreprise:', ['entreprise_id' => $entrepriseId]);
        } elseif ($entrepriseId && !in_array($entrepriseId, $entrepriseIds)) {
            \Log::warning('⚠️ Tentative d\'accès non autorisé', [
                'user_id' => $user->id,
                'entreprise_id' => $entrepriseId,
                'allowed_ids' => $entrepriseIds
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }
        
        $offres = $query->orderBy('created_at', 'desc')->get();
        
        \Log::info('✅ Offres récupérées', [
            'count' => $offres->count(),
            'entreprise_id' => $entrepriseId,
            'entreprises_gerees' => count($entrepriseIds)
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $offres,
            'meta' => [
                'total_offres' => $offres->count(),
                'entreprise_filtree' => $entrepriseId ? (int)$entrepriseId : null,
                'entreprises_gerees_count' => count($entrepriseIds)
            ]
        ]);
    }

    /**
     * ✅ AJOUTÉ : Récupérer les candidatures des offres des entreprises gérées
     * Avec filtre optionnel par entreprise_id
     */
    public function getCandidatures(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux Community Managers'
            ], 403);
        }
        
        $entrepriseId = $request->query('entreprise_id');
        
        \Log::info('📋 CM récupère candidatures', [
            'user_id' => $user->id,
            'entreprise_id' => $entrepriseId
        ]);
        
        $entrepriseIds = $user->entreprisesGerees()->pluck('entreprises.id')->toArray();
        
        if (empty($entrepriseIds)) {
            \Log::info('⚠️ CM n\'a aucune entreprise assignée');
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        // Query : candidatures des offres des entreprises gérées
        $query = Candidature::with(['offre.entreprise', 'candidat', 'offre.categorie'])
            ->whereHas('offre', function($q) use ($entrepriseIds) {
                $q->whereIn('entreprise_id', $entrepriseIds);
            });
        
        // ✅ Filtrer par entreprise si spécifié
        if ($entrepriseId && in_array($entrepriseId, $entrepriseIds)) {
            $query->whereHas('offre', function($q) use ($entrepriseId) {
                $q->where('entreprise_id', $entrepriseId);
            });
            \Log::info('🔍 Filtrage candidatures par entreprise:', ['entreprise_id' => $entrepriseId]);
        } elseif ($entrepriseId && !in_array($entrepriseId, $entrepriseIds)) {
            \Log::warning('⚠️ Tentative d\'accès non autorisé aux candidatures');
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }
        
        $candidatures = $query->orderBy('created_at', 'desc')->get();
        
        \Log::info('✅ Candidatures récupérées', [
            'count' => $candidatures->count(),
            'entreprise_id' => $entrepriseId
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $candidatures,
            'meta' => [
                'total_candidatures' => $candidatures->count(),
                'entreprise_filtree' => $entrepriseId ? (int)$entrepriseId : null,
                'entreprises_gerees_count' => count($entrepriseIds)
            ]
        ]);
    }

    /**
     * ✅ AJOUTÉ : Récupérer les publicités des entreprises gérées
     * Avec filtre optionnel par entreprise_id
     */
    public function getPublicites(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux Community Managers'
            ], 403);
        }
        
        $entrepriseId = $request->query('entreprise_id');
        
        \Log::info('📋 CM récupère publicités', [
            'user_id' => $user->id,
            'entreprise_id' => $entrepriseId
        ]);
        
        $entrepriseIds = $user->entreprisesGerees()->pluck('entreprises.id')->toArray();
        
        if (empty($entrepriseIds)) {
            \Log::info('⚠️ CM n\'a aucune entreprise assignée');
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
        
        // Query : publicités des entreprises gérées
        $query = Publicite::with(['entreprise', 'createur'])
            ->whereIn('entreprise_id', $entrepriseIds);
        
        // ✅ Filtrer par entreprise si spécifié
        if ($entrepriseId && in_array($entrepriseId, $entrepriseIds)) {
            $query->where('entreprise_id', $entrepriseId);
            \Log::info('🔍 Filtrage publicités par entreprise:', ['entreprise_id' => $entrepriseId]);
        } elseif ($entrepriseId && !in_array($entrepriseId, $entrepriseIds)) {
            \Log::warning('⚠️ Tentative d\'accès non autorisé aux publicités');
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }
        
        $publicites = $query->orderBy('created_at', 'desc')->get();
        
        \Log::info('✅ Publicités récupérées', [
            'count' => $publicites->count(),
            'entreprise_id' => $entrepriseId
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $publicites,
            'meta' => [
                'total_publicites' => $publicites->count(),
                'entreprise_filtree' => $entrepriseId ? (int)$entrepriseId : null,
                'entreprises_gerees_count' => count($entrepriseIds)
            ]
        ]);
    }

    /**
     * ✅ Liste des entreprises gérables par le Community Manager
     */
    public function getEntreprises(Request $request)
    {
        $user = $request->user();
        
        $entreprises = $user->entreprisesGerees()
            ->with(['user', 'pays'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entreprises,
            'meta' => [
                'user_id' => $user->id,
                'user_name' => $user->prenom . ' ' . $user->nom,
                'total_entreprises' => $entreprises->count()
            ]
        ]);
    }

    /**
     * Dashboard du CM
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux Community Managers'
            ], 403);
        }

        $entreprises = $user->entreprisesGerees()->with(['user', 'pays'])->get();

        $stats = [
            'entreprises_count' => $entreprises->count(),
            'entreprises' => $entreprises
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * ✅ MODIFIÉ : Statistiques du CM (avec filtre optionnel)
     */
    public function getStatistiques(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux Community Managers'
            ], 403);
        }

        $entrepriseId = $request->query('entreprise_id');
        $entreprises = $user->entreprisesGerees;
        $entrepriseIds = $entreprises->pluck('id')->toArray();

        // ✅ Si une entreprise est sélectionnée, filtrer les stats
        if ($entrepriseId && in_array($entrepriseId, $entrepriseIds)) {
            $entrepriseIds = [$entrepriseId];
        }

        $stats = [
            'entreprises_count' => count($entrepriseIds),
            'offres_count' => Offre::whereIn('entreprise_id', $entrepriseIds)->count(),
            'candidatures_count' => Candidature::whereHas('offre', function($q) use ($entrepriseIds) {
                $q->whereIn('entreprise_id', $entrepriseIds);
            })->count(),
            'publicites_count' => Publicite::whereIn('entreprise_id', $entrepriseIds)->count(),
            'entreprise_filtree' => $entrepriseId ? (int)$entrepriseId : null
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Assigner un Community Manager à une entreprise (ADMIN ONLY)
     */
    public function assignToEntreprise(Request $request)
    {
        \Log::info('=== ASSIGN REQUEST ===');
        \Log::info('All input:', ['data' => $request->all()]);
        
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'entreprise_id' => 'required|exists:entreprises,id',
        ], [
            'user_id.required' => 'L\'ID du Community Manager est requis',
            'user_id.exists' => 'Cet utilisateur n\'existe pas',
            'entreprise_id.required' => 'L\'ID de l\'entreprise est requis',
            'entreprise_id.exists' => 'Cette entreprise n\'existe pas',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);

        if (!$user->hasRole('community_manager')) {
            \Log::warning('User is not a CM');
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur n\'est pas un Community Manager'
            ], 400);
        }

        $user->entreprisesGerees()->syncWithoutDetaching([$request->entreprise_id]);
        \Log::info('Assignment successful');

        return response()->json([
            'success' => true,
            'message' => 'Community Manager assigné avec succès',
            'data' => [
                'user_id' => $user->id,
                'entreprise_id' => $request->entreprise_id
            ]
        ]);
    }

    /**
     * Retirer un Community Manager d'une entreprise (ADMIN ONLY)
     */
    public function removeFromEntreprise(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'entreprise_id' => 'required|exists:entreprises,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->entreprisesGerees()->detach($request->entreprise_id);

        return response()->json([
            'success' => true,
            'message' => 'Assignation retirée avec succès'
        ]);
    }

    /**
     * Liste des CM assignés à une entreprise (ADMIN ONLY)
     */
    public function getEntrepriseCommunityManagers($entrepriseId)
    {
        $entreprise = Entreprise::findOrFail($entrepriseId);
        $cms = $entreprise->communityManagers()->get();

        return response()->json([
            'success' => true,
            'data' => $cms
        ]);
    }

    /**
     * Liste des entreprises assignées à un CM (ADMIN ONLY)
     */
    public function getCommunityManagerEntreprises($userId)
    {
        $user = User::findOrFail($userId);

        if (!$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Cet utilisateur n\'est pas un Community Manager'
            ], 400);
        }

        $entreprises = $user->entreprisesGerees()
            ->with(['user', 'pays'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $entreprises,
            'meta' => [
                'user_id' => $userId,
                'user_name' => $user->prenom . ' ' . $user->nom,
                'total_entreprises' => $entreprises->count()
            ]
        ]);
    }

    /**
     * Liste tous les Community Managers (ADMIN ONLY)
     */
    public function index()
    {
        $cms = User::whereHas('roles', function($query) {
            $query->where('nom', 'community_manager');
        })->with(['roles', 'entreprisesGerees'])->get();

        return response()->json([
            'success' => true,
            'data' => $cms
        ]);
    }
}