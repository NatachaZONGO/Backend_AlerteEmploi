<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunityManagerController extends Controller
{
    /**
     * ✅ Liste des entreprises gérables par le Community Manager (CORRIGÉE)
     */
    public function getEntreprises(Request $request)
    {
        $user = $request->user();
        
        // ✅ Charger les entreprises AVEC les relations user et pays
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
     * Statistiques du CM
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

        $entreprises = $user->entreprisesGerees;
        $entrepriseIds = $entreprises->pluck('id');

        $stats = [
            'entreprises_count' => $entreprises->count(),
            'offres_count' => \App\Models\Offre::whereIn('entreprise_id', $entrepriseIds)->count(),
            'candidatures_count' => \App\Models\Candidature::whereHas('offre', function($q) use ($entrepriseIds) {
                $q->whereIn('entreprise_id', $entrepriseIds);
            })->count(),
            'publicites_count' => \App\Models\Publicite::whereIn('entreprise_id', $entrepriseIds)->count(),
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

        // ✅ Charger avec relations
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