<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Candidat;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouveau candidat
     */
    public function registerCandidat(Request $request)
    {
        // Harmoniser le nom attendu par la règle "confirmed"
        if ($request->has('confirmPassword')) {
            $request->merge(['password_confirmation' => $request->input('confirmPassword')]);
        }
        $validator = Validator::make($request->all(), [
            'nom'             => 'required|string|max:255',
            'prenom'          => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users',
            'telephone'       => 'required|string|max:20|unique:users',
            'password'        => 'required|string|min:8|confirmed',
            // Profil candidat
            'sexe'            => 'required|in:Homme,Femme',
            'date_naissance'  => 'required|date',
            'categorie_id'    => 'required|exists:categories,id',
            'ville'           => 'required|string',
            'niveau_etude'    => 'required|string',
            'disponibilite'   => 'required|string',
            'pays_id'         => 'required|exists:pays,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'statut'    => 'actif',
        ]);

        // Rôle candidat
        if ($role = Role::where('nom', 'candidat')->first()) {
            $user->roles()->attach($role->id);
        }

        // Profil candidat
        Candidat::create([
            'user_id'        => $user->id,
            'sexe'           => $request->sexe,
            'date_naissance' => $request->date_naissance,
            'categorie_id'   => $request->categorie_id,
            'ville'          => $request->ville,
            'niveau_etude'   => $request->niveau_etude,
            'disponibilite'  => $request->disponibilite,
            'pays_id'        => $request->pays_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->roles()->pluck('nom'); // tableau simple côté front

        return response()->json([
            'success' => true,
            'message' => 'Candidat créé avec succès',
            'data'    => [
                'user'       => $user->load('roles'),
                'roles'      => $roles,
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Inscription d'un nouveau recruteur
     */
    public function registerRecruteur(Request $request)
    {
        if ($request->has('confirmPassword')) {
        $request->merge(['password_confirmation' => $request->input('confirmPassword')]);
        }
        $validator = Validator::make($request->all(), [
            'nom'              => 'required|string|max:255',
            'prenom'           => 'required|string|max:255',
            'email'            => 'required|string|email|max:255|unique:users',
            'telephone'        => 'required|string|max:20|unique:users',
            'password'         => 'required|string|min:8|confirmed',
            // Entreprise
            'nom_entreprise'   => 'required|string',
            'secteur_activite' => 'required|string',
            'pays_id'          => 'required|exists:pays,id',
            'description'      => 'nullable|string',
            'site_web'         => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'telephone' => $request->telephone,
            'password'  => Hash::make($request->password),
            'statut'    => 'actif',
        ]);

        if ($role = Role::where('nom', 'recruteur')->first()) {
            $user->roles()->attach($role->id);
        }

        Entreprise::create([
            'user_id'         => $user->id,
            'nom_entreprise'  => $request->nom_entreprise,
            'secteur_activite'=> $request->secteur_activite,
            'description'     => $request->description,
            'site_web'        => $request->site_web,
            'logo'            => null,
            'pays_id'         => $request->pays_id,
            'statut'          => 'en attente', // devra être validée par un admin
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->roles()->pluck('nom');

        return response()->json([
            'success' => true,
            'message' => 'Recruteur créé avec succès',
            'data'    => [
                'user'       => $user->load('roles'),
                'roles'      => $roles,
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Connexion
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects'
            ], 401);
        }

        if ($user->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Compte désactivé'
            ], 403);
        }

        // Cas particulier recruteur : entreprise non validée → blocage
        if ($user->hasRole('recruteur')) {
            $entreprise = $user->entreprise ?? null; // relation à adapter si besoin
            if (!$entreprise || $entreprise->statut !== 'valide') {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte en attente de validation'
                ], 403);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->roles()->pluck('nom');

        // Plus de redirect_url ici → le front redirige vers /dashboard
        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data'    => [
                'user'       => $user->load('roles'),
                'roles'      => $roles,
                'token'      => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Profil connecté
     */
    public function me(Request $request)
    {
        $user  = $request->user()->load('roles');
        $roles = $user->roles->pluck('nom');

        return response()->json([
            'success' => true,
            'data'    => [
                'user'  => $user,
                'roles' => $roles,
            ]
        ]);
    }

    /**
     * (Optionnel) Métadonnées pour le dashboard unique (le front affiche/masque selon rôles)
     */
    public function dashboard(Request $request)
    {
        $user  = $request->user();
        $roles = $user->roles()->pluck('nom');

        // Exemples de flags utilisés côté front pour cacher/afficher des tuiles
        $flags = [
            'can_manage_users'     => $roles->contains('admin'),
            'can_manage_entreprises'=> $roles->contains('admin'),
            'can_manage_offres'    => $roles->contains('admin') || $roles->contains('recruteur'),
            'can_apply'            => $roles->contains('candidat'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'user'   => $user->load('roles'),
                'roles'  => $roles,
                'flags'  => $flags,
                // le front va de toute façon sur /dashboard (unique)
            ]
        ]);
    }
}
