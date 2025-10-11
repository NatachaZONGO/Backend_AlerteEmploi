<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class UserProfileController extends Controller
{
    /**
     * Afficher le profil de l'utilisateur connecté
     */
    public function show()
    {
        $user = Auth::user()->load(['roles', 'candidat', 'entreprise']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user
            ]
        ]);
    }

    /**
     * Mettre à jour les informations de base de l'utilisateur
     */
    public function updateBasicInfo(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'telephone' => 'sometimes|string|max:20|unique:users,telephone,' . $user->id,
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'photo' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Informations mises à jour avec succès',
            'data' => [
                'user' => $user->fresh()->load('roles')
            ]
        ]);
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier l'ancien mot de passe
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect'
            ], 400);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    }

    /**
     * Mettre à jour le profil candidat (si applicable)
     */
    public function updateCandidatProfile(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('candidat') || !$user->candidat) {
            return response()->json([
                'success' => false,
                'message' => 'Profil candidat non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'sexe' => 'sometimes|in:Homme,Femme',
            'date_naissance' => 'sometimes|date',
            'categorie_id' => 'sometimes|exists:categories,id',
            'ville' => 'sometimes|string|max:255',
            'niveau_etude' => 'sometimes|string|max:255',
            'disponibilite' => 'sometimes|string|max:255',
            'pays_id' => 'sometimes|exists:pays,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->candidat->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil candidat mis à jour avec succès',
            'data' => [
                'candidat' => $user->candidat->fresh()
            ]
        ]);
    }

    /**
     * Mettre à jour le profil entreprise (si applicable)
     */
    public function updateEntrepriseProfile(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasRole('recruteur') || !$user->entreprise) {
            return response()->json([
                'success' => false,
                'message' => 'Profil entreprise non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom_entreprise' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'site_web' => 'sometimes|nullable|url',
            'secteur_activite' => 'sometimes|string|max:255',
            'logo' => 'sometimes|nullable|string|max:255',
            'pays_id' => 'sometimes|exists:pays,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->entreprise->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil entreprise mis à jour avec succès',
            'data' => [
                'entreprise' => $user->entreprise->fresh()
            ]
        ]);
    }
}