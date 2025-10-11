<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Role;

class AdminUserController extends Controller
{
    /**
     * Lister les utilisateurs (recherche, filtre par rôle, pagination)
     * GET /users?role=recruteur&q=doe&per_page=15
     */
    public function index(Request $request)
    {
        $perPage = (int)($request->get('per_page', 15));
        $perPage = $perPage > 0 ? $perPage : 15;

        $query = User::with(['roles', 'candidat', 'entreprise']);

        // Recherche globale
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        // Filtrer par rôle
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('nom', $request->role);
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    /**
     * Créer un utilisateur (création "admin")
     * POST /users
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'telephone' => 'nullable|string|max:20|unique:users,telephone',
            'password'  => 'required|string|min:8',
            // CORRECTION: Accepter les fichiers images
            'photo'     => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'statut'    => ['nullable', Rule::in(['actif', 'inactif'])],
            // CORRECTION: Utiliser role_id au lieu de roles (array)
            'role_id'   => 'required|integer|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Gérer l'upload de la photo
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create([
            'nom'          => $data['nom'],
            'prenom'       => $data['prenom'],
            'email'        => $data['email'],
            'telephone'    => $data['telephone'] ?? null,
            'photo'        => $photoPath, // Utiliser 'photo' au lieu de 'photo_profil'
            'statut'       => $data['statut'] ?? 'actif',
            'password'     => Hash::make($data['password']),
        ]);

        // Attacher le rôle par ID
        $user->roles()->attach($data['role_id']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data'    => $user->load('roles')
        ], 201);
    }

    /**
     * Afficher un utilisateur
     * GET /users/{id}
     */
    public function show($id)
    {
        $user = User::with(['roles', 'candidat', 'entreprise'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $user
        ]);
    }

    /**
     * Mettre à jour un utilisateur
     * PUT /users/{id} ou POST /users/{id} avec _method=PUT
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // CORRECTION: Gestion spéciale pour les requêtes avec fichiers
        $hasFile = $request->hasFile('photo');
        
        if ($hasFile) {
            // Si il y a un fichier, validation spécifique
            $validator = Validator::make($request->all(), [
                'nom'       => 'required|string|max:255',
                'prenom'    => 'required|string|max:255',
                'telephone' => ['nullable','string','max:20', Rule::unique('users','telephone')->ignore($user->id)],
                'email'     => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
                'statut'    => ['required', Rule::in(['actif', 'inactif'])],
                'photo'     => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'password'  => 'nullable|string|min:8',
                'role_id'   => 'required|integer|exists:roles,id'
            ]);
        } else {
            // Si pas de fichier, validation normale
            $validator = Validator::make($request->all(), [
                'nom'       => 'sometimes|string|max:255',
                'prenom'    => 'sometimes|string|max:255',
                'telephone' => ['sometimes','nullable','string','max:20', Rule::unique('users','telephone')->ignore($user->id)],
                'email'     => ['sometimes','email','max:255', Rule::unique('users','email')->ignore($user->id)],
                'statut'    => ['sometimes', Rule::in(['actif', 'inactif'])],
                'password'  => 'sometimes|nullable|string|min:8',
                'role_id'   => 'sometimes|integer|exists:roles,id'
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Gérer le mot de passe
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Gérer l'upload de la photo
        if ($hasFile) {
            // Supprimer l'ancienne photo si elle existe
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }
            
            // Stocker la nouvelle photo
            $photoPath = $request->file('photo')->store('photos', 'public');
            $data['photo'] = $photoPath;
        }
        
        // Retirer photo du data si pas de fichier pour éviter les erreurs
        if (!$hasFile && array_key_exists('photo', $data)) {
            unset($data['photo']);
        }

        $user->update($data);

        // Mettre à jour le rôle si fourni
        if (array_key_exists('role_id', $data)) {
            $user->roles()->sync([$data['role_id']]);
            unset($data['role_id']); // Éviter de l'inclure dans la mise à jour du user
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data'    => $user->fresh()->load('roles')
        ]);
    }

    /**
     * Supprimer un utilisateur
     * DELETE /users/{id}
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Supprimer la photo si elle existe
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé'
        ]);
    }

    /**
     * Changer le statut (actif/inactif)
     * PATCH /users/{id}/status
     */
    public function changeStatus(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'statut' => ['required', Rule::in(['actif', 'inactif'])]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Statut invalide',
                'errors'  => $validator->errors()
            ], 422);
        }

        $user->update(['statut' => $request->statut]);

        return response()->json([
            'success' => true,
            'message' => 'Statut utilisateur modifié avec succès',
            'data'    => $user
        ]);
    }

    /**
     * Réinitialiser / changer le mot de passe
     * POST /users/{id}/password  { password, password_confirmation }
     */
    public function resetPassword(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed'
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe mis à jour'
        ]);
    }

    /**
     * Synchroniser les rôles d'un utilisateur
     * POST /users/{id}/roles  { "role_id": 2 }
     */
    public function syncRoles(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $data = $request->validate([
            'role_id' => 'required|integer|exists:roles,id'
        ]);

        $user->roles()->sync([$data['role_id']]);

        return response()->json([
            'success' => true,
            'message' => 'Rôle synchronisé',
            'data'    => $user->load('roles')
        ]);
    }
}