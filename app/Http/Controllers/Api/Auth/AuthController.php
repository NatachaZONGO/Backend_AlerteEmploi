<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Candidat;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            // Optionnel : rôle(s) en clair (ex: "candidat")
            'roles'           => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'nom'       => $request->nom,
                'prenom'    => $request->prenom,
                'email'     => $request->email,
                'telephone' => $request->telephone,
                'password'  => Hash::make($request->password),
                'statut'    => 'actif',
            ]);

            // Rôle (tolère string "Candidat" ou rien -> par défaut 'candidat')
            $roleIds = $this->resolveRoleIdsFromInput($request, ['candidat']);
            if (!empty($roleIds)) {
                $user->roles()->sync($roleIds);
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

            // Recharge la relation pour exposer roles + accessors role/role_id
            return $user->load('roles:id,nom');
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Candidat créé avec succès',
            'data'    => [
                'user'       => $user,
                'roles'      => $user->roles->pluck('nom')->values(),
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
            // Optionnel : rôle(s) en clair (ex: "recruteur")
            'roles'            => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'nom'       => $request->nom,
                'prenom'    => $request->prenom,
                'email'     => $request->email,
                'telephone' => $request->telephone,
                'password'  => Hash::make($request->password),
                'statut'    => 'actif',
            ]);

            // Rôle (tolère string "Recruteur" ou défaut 'recruteur')
            $roleIds = $this->resolveRoleIdsFromInput($request, ['recruteur']);
            if (!empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }

            // Fiche entreprise
            Entreprise::create([
                'user_id'          => $user->id,
                'nom_entreprise'   => $request->nom_entreprise,
                'secteur_activite' => $request->secteur_activite,
                'description'      => $request->description,
                'site_web'         => $request->site_web,
                'logo'             => null,
                'pays_id'          => $request->pays_id,
                'statut'           => 'en attente', // sera validée par un admin
            ]);

            return $user->load('roles:id,nom');
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Recruteur créé avec succès',
            'data'    => [
                'user'       => $user,
                'roles'      => $user->roles->pluck('nom')->values(),
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
            $entreprise = $user->entreprise ?? null;
            if (!$entreprise || $entreprise->statut !== 'valide') {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte en attente de validation'
                ], 403);
            }
        }

        // ✅ Mettre à jour la dernière connexion
        $user->update([
            'last_login' => now()
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Charger les rôles pour exposer 'roles', 'role', 'role_id'
        $user->load('roles:id,nom');

        // Réponse alignée avec le front: token + user en racine
        return response()->json([
            'success'    => true,
            'message'    => 'Connexion réussie',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,
            'roles'      => $user->roles->pluck('nom')->values(),
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
        $user = $request->user()->load('roles:id,nom');

        return response()->json([
            'success' => true,
            'data'    => [
                'user'  => $user,                               // contient 'role' et 'role_id'
                'roles' => $user->roles->pluck('nom')->values(), // ex: ["Administrateur"]
            ]
        ]);
    }

    /**
     * (Optionnel) Métadonnées pour le dashboard unique
     */
    public function dashboard(Request $request)
    {
        $user  = $request->user()->load('roles:id,nom');
        $roles = $user->roles->pluck('nom');

        $flags = [
            'can_manage_users'       => $roles->contains('Administrateur'),
            'can_manage_entreprises' => $roles->contains('Administrateur'),
            'can_manage_offres'      => $roles->contains('Administrateur') || $roles->contains('Recruteur'),
            'can_apply'              => $roles->contains('Candidat'),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'user'   => $user,
                'roles'  => $roles->values(),
                'flags'  => $flags,
            ]
        ]);
    }

    /**
     * Mappe "roles" depuis l'input (string ou array) vers les IDs en base.
     * Exemple: "Administrateur" -> [id]. $default est utilisé si rien n'est fourni.
     */
    private function resolveRoleIdsFromInput(Request $request, array $default = []): array
    {
        $input = $request->input('roles', $default); // "Administrateur" ou ["Administrateur"]
        $names = is_array($input) ? $input : [$input];

        $lc = collect($names)
            ->filter()
            ->map(fn($r) => mb_strtolower(trim((string)$r)));

        // Requête case-insensitive sur la colonne nom
        return Role::whereIn(DB::raw('LOWER(nom)'), $lc)->pluck('id')->all();
    }
}