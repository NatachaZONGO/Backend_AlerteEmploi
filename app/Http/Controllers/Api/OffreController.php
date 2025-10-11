<?php

namespace App\Http\Controllers\Api;

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
        $query = Offre::with(['entreprise', 'categorie']) // Recruteur n'est pas nécessaire
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
     * Créer une nouvelle offre (recruteurs seulement)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Vérifier que l'utilisateur est un recruteur
        if (!$user->hasRole('recruteur')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les recruteurs peuvent créer des offres'
            ], 403);
        }

        // Vérifier que l'entreprise est validée
        if ($user->entreprise->statut !== 'valide') {
            return response()->json([
                'success' => false,
                'message' => 'Votre entreprise doit être validée pour publier des offres'
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
            'recruteur_id' => $user->id,
            'statut' => 'brouillon', // Par défaut en brouillon
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
        if ($offre->statut !== 'publiee' && (!$user || $offre->recruteur_id !== $user->id)) {
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
     * Mettre à jour une offre (propriétaire seulement)
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

        // Vérifier que l'utilisateur est le propriétaire de l'offre
        if ($offre->recruteur_id !== $user->id) {
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
     * Supprimer une offre (propriétaire seulement)
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

        // Vérifier que l'utilisateur est le propriétaire de l'offre
        if ($offre->recruteur_id !== $user->id) {
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
     * Soumettre une offre pour validation (recruteurs)
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

        // Vérifier que l'utilisateur est le propriétaire de l'offre
        if ($offre->recruteur_id !== $user->id) {
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
        $admins = User::role('admin')->get();
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
        if (!$user->hasRole('admin')) {
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
     * Fermer une offre
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

        // Vérifier que l'utilisateur est le propriétaire de l'offre
        if ($offre->recruteur_id !== $user->id) {
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
     * Lister les offres du recruteur connecté
     */
    public function mesOffres(Request $request)
    {
        $user = Auth::user();

        if (!$user->hasRole('recruteur')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les recruteurs peuvent accéder à cette fonctionnalité'
            ], 403);
        }

        $query = Offre::with(['entreprise', 'categorie'])
            ->where('recruteur_id', $user->id);

        // Filtrer par statut si spécifié
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        $offres = $query->orderBy('created_at', 'desc')
                       ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $offres
        ]);
    }
}

