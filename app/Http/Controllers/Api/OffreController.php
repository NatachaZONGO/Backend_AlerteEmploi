<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Offre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Notifications\NouvelleOffreEnAttenteNotification;
use App\Models\User;

class OffreController extends Controller
{
    /**
     * Lister toutes les offres publiques (pour candidats)
     */
    public function index(Request $request)
    {
        $query = Offre::with(['entreprise', 'categorie'])
            ->active(); // Seulement les offres actives

        // Filtres optionnels
        if ($request->has('type_offre')) {
            $query->where('type_offre', $request->type_offre);
        }

        if ($request->has('localisation')) {
            $query->where('localisation', 'like', '%' . $request->localisation . '%');
        }

        if ($request->has('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        if ($request->has('experience')) {
            $query->where('experience', 'like', '%' . $request->experience . '%');
        }

        // Tri par date de publication (plus récentes en premier)
        $offres = $query->orderBy('date_publication', 'desc')
                       ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $offres
        ]);
    }

    /**
     * ✅ Créer une nouvelle offre (Recruteurs ET Community Managers)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // ✅ Vérifier que l'utilisateur est recruteur OU community manager
        if (!$user->hasRole('recruteur') && !$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les recruteurs et community managers peuvent créer des offres'
            ], 403);
        }

        // ✅ Récupérer l'entreprise
        $entrepriseId = $request->input('entreprise_id');

        // Si pas spécifiée, prendre la première gérable
        if (!$entrepriseId) {
            $entreprises = $user->getManageableEntreprises();
            $entreprise = $entreprises->first();
            
            if (!$entreprise) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune entreprise disponible pour publier des offres'
                ], 403);
            }
            
            $entrepriseId = $entreprise->id;
        }

        // ✅ Vérifier les droits sur l'entreprise
        if (!$user->canManageEntreprise($entrepriseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }

        // ✅ Vérifier que l'entreprise est validée
        $entreprise = \App\Models\Entreprise::find($entrepriseId);
        if (!$entreprise || $entreprise->statut !== 'valide') {
            return response()->json([
                'success' => false,
                'message' => 'L\'entreprise doit être validée pour publier des offres'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'experience' => 'required|string|max:255',
            'localisation' => 'required|string|max:255',
            'type_offre' => 'required|in:emploi,stage',
            'type_contrat' => 'required|string|max:255',
            'date_expiration' => 'required|date|after:today',
            'salaire' => 'nullable|numeric|min:0',
            'categorie_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $offre = Offre::create([
            'titre' => $request->titre,
            'description' => $request->description,
            'experience' => $request->experience,
            'localisation' => $request->localisation,
            'type_offre' => $request->type_offre,
            'type_contrat' => $request->type_contrat,
            'date_expiration' => $request->date_expiration,
            'salaire' => $request->salaire,
            'categorie_id' => $request->categorie_id,
            'entreprise_id' => $entrepriseId, // ✅ IMPORTANT
            'recruteur_id' => $user->id, // L'utilisateur qui a créé (peut être CM)
            'statut' => 'brouillon',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre créée avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ], 201);
    }

    /**
     * Afficher une offre spécifique
     */
    public function show($id)
    {
        $offre = Offre::with(['recruteur', 'entreprise', 'categorie'])->find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        // Vérifier si l'offre est accessible
        $user = Auth::user();
        
        // ✅ Vérifier si l'utilisateur peut gérer cette offre
        $canManage = $user && $user->canManageEntreprise($offre->entreprise_id);
        
        if ($offre->statut !== 'publiee' && !$canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non accessible'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $offre
        ]);
    }

    /**
     * ✅ Mettre à jour une offre (Recruteur propriétaire OU CM assigné)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        // ✅ Vérifier les droits sur l'entreprise de l'offre
        if (!$user->canManageEntreprise($offre->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette offre'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'experience' => 'sometimes|string|max:255',
            'localisation' => 'sometimes|string|max:255',
            'type_offre' => 'sometimes|in:emploi,stage',
            'type_contrat' => 'sometimes|string|max:255',
            'date_expiration' => 'sometimes|date|after:today',
            'salaire' => 'nullable|numeric|min:0',
            'categorie_id' => 'sometimes|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $offre->update($request->only([
            'titre', 'description', 'experience', 'localisation',
            'type_offre', 'type_contrat', 'date_expiration', 'salaire', 'categorie_id'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Offre mise à jour avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }

    /**
     * ✅ Supprimer une offre (Recruteur propriétaire OU CM assigné)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        // ✅ Vérifier les droits sur l'entreprise de l'offre
        if (!$user->canManageEntreprise($offre->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette offre'
            ], 403);
        }

        $offre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Offre supprimée avec succès'
        ]);
    }

    /**
     * ✅ Soumettre une offre pour validation (Recruteur OU CM)
     */
    public function soumettreValidation($id)
    {
        $user = Auth::user();
        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        // ✅ Vérifier les droits sur l'entreprise de l'offre
        if (!$user->canManageEntreprise($offre->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à soumettre cette offre'
            ], 403);
        }

        if ($offre->statut !== 'brouillon') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les offres en brouillon peuvent être soumises pour validation'
            ], 400);
        }

        $offre->soumettreValidation();

        // Notifier tous les admins
        $admins = User::whereHas('roles', function($query) {
            $query->where('nom', 'administrateur');
        })->get();
        
        foreach ($admins as $admin) {
            $admin->notify(new NouvelleOffreEnAttenteNotification($offre));
        }

        return response()->json([
            'success' => true,
            'message' => 'Offre soumise pour validation avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }

    /**
     * Publier une offre validée (admins seulement)
     */
    public function publier($id)
    {
        $user = Auth::user();

        // Seuls les admins peuvent publier
        if (!$user->hasRole('administrateur')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les administrateurs peuvent publier des offres'
            ], 403);
        }

        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        if ($offre->statut !== 'validee') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les offres validées peuvent être publiées'
            ], 400);
        }

        $offre->publier();

        return response()->json([
            'success' => true,
            'message' => 'Offre publiée avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }

    /**
     * ✅ Fermer une offre (Recruteur OU CM)
     */
    public function fermer($id)
    {
        $user = Auth::user();
        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        // ✅ Vérifier les droits sur l'entreprise de l'offre
        if (!$user->canManageEntreprise($offre->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à fermer cette offre'
            ], 403);
        }

        $offre->fermer();

        return response()->json([
            'success' => true,
            'message' => 'Offre fermée avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }

    /**
     * ✅ Lister les offres des entreprises gérables (Recruteur OU CM)
     */
    public function mesOffres(Request $request)
    {
        $user = Auth::user();

        // ✅ Vérifier que l'utilisateur est recruteur OU community manager
        if (!$user->hasRole('recruteur') && !$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les recruteurs et community managers peuvent accéder à cette fonctionnalité'
            ], 403);
        }

        // ✅ Récupérer toutes les entreprises gérables
        $entreprises = $user->getManageableEntreprises();
        $entrepriseIds = $entreprises->pluck('id');

        // ✅ Requête pour les offres de toutes les entreprises gérables
        $query = Offre::with(['entreprise', 'categorie'])
            ->whereIn('entreprise_id', $entrepriseIds);

        // Filtrer par statut si spécifié
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtrer par entreprise spécifique (utile pour le CM qui gère plusieurs entreprises)
        if ($request->has('entreprise_id')) {
            $entrepriseId = $request->input('entreprise_id');
            
            // Vérifier que l'utilisateur peut gérer cette entreprise
            if (!$user->canManageEntreprise($entrepriseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette entreprise'
                ], 403);
            }
            
            $query->where('entreprise_id', $entrepriseId);
        }

        $offres = $query->orderBy('created_at', 'desc')
                       ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $offres,
            // ✅ Ajouter la liste des entreprises pour le filtre frontend
            'meta' => [
                'entreprises' => $entreprises
            ]
        ]);
    }

    /**
     * ✅ NOUVEAU : Dashboard des offres (statistiques)
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasRole('recruteur') && !$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        // Récupérer les entreprises gérables
        $entreprises = $user->getManageableEntreprises();
        $entrepriseIds = $entreprises->pluck('id');

        // Statistiques
        $stats = [
            'total' => Offre::whereIn('entreprise_id', $entrepriseIds)->count(),
            'brouillon' => Offre::whereIn('entreprise_id', $entrepriseIds)->where('statut', 'brouillon')->count(),
            'en_attente' => Offre::whereIn('entreprise_id', $entrepriseIds)->where('statut', 'en_attente')->count(),
            'validee' => Offre::whereIn('entreprise_id', $entrepriseIds)->where('statut', 'validee')->count(),
            'publiee' => Offre::whereIn('entreprise_id', $entrepriseIds)->where('statut', 'publiee')->count(),
            'fermee' => Offre::whereIn('entreprise_id', $entrepriseIds)->where('statut', 'fermee')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'entreprises' => $entreprises
            ]
        ]);
    }

    /**
     * Valider une offre (Admin seulement)
     */
    public function valider($id)
    {
        $user = Auth::user();

        if (!$user->hasRole('administrateur')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les administrateurs peuvent valider des offres'
            ], 403);
        }

        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        if ($offre->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => 'Seules les offres en attente peuvent être validées'
            ], 400);
        }

        $offre->update(['statut' => 'validee']);

        return response()->json([
            'success' => true,
            'message' => 'Offre validée avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }

    /**
     * Rejeter une offre (Admin seulement)
     */
    public function rejeter(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user->hasRole('administrateur')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les administrateurs peuvent rejeter des offres'
            ], 403);
        }

        $offre = Offre::find($id);

        if (!$offre) {
            return response()->json([
                'success' => false,
                'message' => 'Offre non trouvée'
            ], 404);
        }

        $offre->update([
            'statut' => 'rejetee',
            'raison_rejet' => $request->input('raison_rejet')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre rejetée avec succès',
            'data' => $offre->load(['recruteur', 'entreprise', 'categorie'])
        ]);
    }
}