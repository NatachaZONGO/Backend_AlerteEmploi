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
        $rules = [
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'telephone' => 'nullable|string|max:20|unique:users,telephone',
            'password'  => 'required|string|min:8',
            'photo'     => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120', // 5 Mo
            'statut'    => ['nullable', Rule::in(['actif', 'inactif'])],
            'role_id'   => 'required|integer|exists:roles,id',
        ];
        $messages = [
            'photo.max'   => 'La photo ne doit pas dépasser 5120 kilo-octets (~5 Mo).',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Formats autorisés : jpg, jpeg, png, gif, webp.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors'  => $validator->errors()
            ], 422);
        }
        $data = $validator->validated();

        // Upload photo (optionnel)
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create([
            'nom'       => $data['nom'],
            'prenom'    => $data['prenom'],
            'email'     => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'photo'     => $photoPath,
            'statut'    => $data['statut'] ?? 'actif',
            'password'  => Hash::make($data['password']),
        ]);

        // Rôle principal
        $user->roles()->sync([$data['role_id']]);

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

        // Si un fichier est présent, on exige certaines règles "required",
        // sinon on rend tout "sometimes".
        if ($request->hasFile('photo')) {
            $rules = [
                'nom'       => 'required|string|max:255',
                'prenom'    => 'required|string|max:255',
                'telephone' => ['nullable', 'string', 'max:20', Rule::unique('users','telephone')->ignore($user->id)],
                'email'     => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
                'statut'    => ['required', Rule::in(['actif', 'inactif'])],
                'password'  => 'nullable|string|min:8',
                'photo'     => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                'role_id'   => 'required|integer|exists:roles,id',
            ];
        } else {
            $rules = [
                'nom'       => 'sometimes|string|max:255',
                'prenom'    => 'sometimes|string|max:255',
                'telephone' => ['sometimes','nullable','string','max:20', Rule::unique('users','telephone')->ignore($user->id)],
                'email'     => ['sometimes','email','max:255', Rule::unique('users','email')->ignore($user->id)],
                'statut'    => ['sometimes', Rule::in(['actif', 'inactif'])],
                'password'  => 'sometimes|nullable|string|min:8',
                'photo'     => 'sometimes|nullable|file|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                'role_id'   => 'sometimes|integer|exists:roles,id',
            ];
        }

        $messages = [
            'photo.max'   => 'La photo ne doit pas dépasser 5120 kilo-octets (~5 Mo).',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'Formats autorisés : jpg, jpeg, png, gif, webp.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors'  => $validator->errors()
            ], 422);
        }
        $data = $validator->validated();

        // Mot de passe (seulement si fourni)
        if (array_key_exists('password', $data) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Photo
        if ($request->hasFile('photo')) {
            // Supprimer l’ancienne si elle existe
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        } else {
            // si on n’envoie pas de fichier, ne pas toucher à la photo
            unset($data['photo']);
        }

        // Mettre à jour le rôle si fourni
        if (array_key_exists('role_id', $data)) {
            $user->roles()->sync([$data['role_id']]);
            unset($data['role_id']); // pas un champ de la table users
        }

        // Mise à jour des autres champs
        $user->update($data);

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
     * Synchroniser le rôle principal d'un utilisateur
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
