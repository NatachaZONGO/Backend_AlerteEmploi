<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Candidat;
use App\Models\Entreprise;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

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
     * ✅ Connexion (avec gestion Community Manager)
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

        // ✅ Cas recruteur : vérifier entreprise validée
        if ($user->hasRole('recruteur')) {
            $entreprise = $user->entreprise ?? null;
            if (!$entreprise || $entreprise->statut !== 'valide') {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte en attente de validation'
                ], 403);
            }
        }

        // ✅ Cas Community Manager : vérifier qu'il a au moins une entreprise assignée
        if ($user->hasRole('community_manager')) {
            if ($user->entreprisesGerees()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune entreprise assignée. Contactez l\'administrateur.'
                ], 403);
            }
        }

        // ✅ Mettre à jour la dernière connexion
        $user->update(['last_login' => now()]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Charger les relations nécessaires
        $user->load(['roles:id,nom', 'entreprise', 'entreprisesGerees']);

        // ✅ Récupérer les entreprises gérables
        $entreprises = $user->getManageableEntreprises();

        // Réponse enrichie avec les entreprises
        return response()->json([
            'success'    => true,
            'message'    => 'Connexion réussie',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user,
            'roles'      => $user->roles->pluck('nom')->values(),
            // ✅ Ajouter les entreprises gérables
            'entreprises' => $entreprises,
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
     * ✅ Profil connecté (avec entreprises gérables)
     */
    public function me(Request $request)
    {
        $user = $request->user()->load(['roles:id,nom', 'entreprise', 'entreprisesGerees']);

        // ✅ Récupérer les entreprises gérables
        $entreprises = $user->getManageableEntreprises();

        return response()->json([
            'success' => true,
            'data'    => [
                'user'        => $user,
                'roles'       => $user->roles->pluck('nom')->values(),
                // ✅ Ajouter les entreprises gérables
                'entreprises' => $entreprises,
            ]
        ]);
    }

    /**
     * ✅ Dashboard avec flags Community Manager
     */
    public function dashboard(Request $request)
    {
        $user  = $request->user()->load(['roles:id,nom', 'entreprise', 'entreprisesGerees']);
        $roles = $user->roles->pluck('nom');

        // ✅ Flags de permissions avec Community Manager
        $flags = [
            'can_manage_users'       => $roles->contains('administrateur'),
            'can_manage_entreprises' => $roles->contains('administrateur'),
            'can_manage_offres'      => $roles->contains('administrateur') 
                                     || $roles->contains('recruteur') 
                                     || $roles->contains('community_manager'), // ✅ NOUVEAU
            'can_apply'              => $roles->contains('candidat'),
            'can_manage_content'     => $roles->contains('administrateur') 
                                     || $roles->contains('community_manager'), // ✅ NOUVEAU
            'can_moderate'           => $roles->contains('administrateur') 
                                     || $roles->contains('community_manager'), // ✅ NOUVEAU
        ];

        // ✅ Récupérer les entreprises gérables
        $entreprises = $user->getManageableEntreprises();

        return response()->json([
            'success' => true,
            'data'    => [
                'user'        => $user,
                'roles'       => $roles->values(),
                'flags'       => $flags,
                'entreprises' => $entreprises, // ✅ NOUVEAU
            ]
        ]);
    }

    /**
     * Mappe "roles" depuis l'input (string ou array) vers les IDs en base.
     */
    private function resolveRoleIdsFromInput(Request $request, array $default = []): array
    {
        $input = $request->input('roles', $default);
        $names = is_array($input) ? $input : [$input];

        $lc = collect($names)
            ->filter()
            ->map(fn($r) => mb_strtolower(trim((string)$r)));

        return Role::whereIn(DB::raw('LOWER(nom)'), $lc)->pluck('id')->all();
    }

    /**
     * ✅ DEMANDER LA RÉINITIALISATION DU MOT DE PASSE
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'L\'adresse email est requise.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.exists' => 'Aucun compte n\'est associé à cette adresse email.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $token = Str::random(64);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now()
        ]);

        $user = User::where('email', $email)->first();

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:4200');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);

        try {
            Mail::send('emails.reset-password', [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'token' => $token
            ], function ($message) use ($user) {
                $message->to($user->email, $user->prenom . ' ' . $user->nom)
                        ->subject('Réinitialisation de votre mot de passe - AlertEmploi');
            });

            return response()->json([
                'success' => true,
                'message' => 'Un email de réinitialisation a été envoyé à votre adresse.',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Erreur envoi email réinitialisation: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ RÉINITIALISER LE MOT DE PASSE
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'token.required' => 'Le token est requis.',
            'email.required' => 'L\'email est requis.',
            'email.exists' => 'Aucun compte trouvé avec cette adresse email.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.'
            ], 400);
        }

        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide.'
            ], 400);
        }

        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Le lien de réinitialisation a expiré. Veuillez faire une nouvelle demande.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
        ], 200);
    }

    /**
     * ✅ VÉRIFIER SI UN TOKEN EST VALIDE
     */
    public function verifyResetToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré.'
            ], 400);
        }

        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide.'
            ], 400);
        }

        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Le token a expiré.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token valide.'
        ], 200);
    }
}