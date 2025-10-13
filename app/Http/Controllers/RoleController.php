<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    // ========================= CRUD RÔLES =========================

    /** GET /api/roles */
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ]);
    }

    /** POST /api/roles */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom'         => ['required','string','max:100','unique:roles,nom'],
            'description' => ['nullable','string','max:255'],
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rôle créé avec succès',
            'data'    => $role,
        ], 201);
    }

    /** GET /api/roles/{id} */
    public function show($id)
    {
        $role = Role::withCount('users')->find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'], 404);
        }

        return response()->json(['success'=>true,'data'=>$role]);
    }

    /** PUT /api/roles/{id} */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'], 404);
        }

        $validated = $request->validate([
            'nom'         => ['sometimes','string','max:100', Rule::unique('roles','nom')->ignore($id)],
            'description' => ['sometimes','nullable','string','max:255'],
        ]);

        $role->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour avec succès',
            'data'    => $role,
        ]);
    }

    /** DELETE /api/roles/{id} */
    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'], 404);
        }

        if ($role->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un rôle déjà attribué à des utilisateurs.'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rôle supprimé avec succès'
        ]);
    }

    // ==================== GESTION DES RÔLES UTILISATEUR ====================

    /**
     * POST /api/users/{userId}/roles/attach
     * Body: { "roles": ["Administrateur","recruteur"] } ou { "role_ids": [1,2] }
     */
    public function attachUserRoles(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'], 404);

        $roleIds = $this->resolveRoleIds($request);
        if (empty($roleIds)) {
            return response()->json(['success'=>false,'message'=>'Aucun rôle valide fourni'], 422);
        }

        $user->roles()->syncWithoutDetaching($roleIds);
        $user->load('roles:id,nom');

        return response()->json([
            'success' => true,
            'message' => 'Rôles ajoutés',
            'data'    => [
                'user'  => $user,
                'roles' => $user->roles->pluck('nom')->values(),
            ]
        ]);
    }

    /**
     * POST /api/users/{userId}/roles/detach
     * Body: { "roles": ["Administrateur"] } ou { "role_ids": [1] }
     */
    public function detachUserRoles(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'], 404);

        $roleIds = $this->resolveRoleIds($request);
        if (empty($roleIds)) {
            return response()->json(['success'=>false,'message'=>'Aucun rôle valide fourni'], 422);
        }

        $user->roles()->detach($roleIds);
        $user->load('roles:id,nom');

        return response()->json([
            'success' => true,
            'message' => 'Rôles retirés',
            'data'    => [
                'user'  => $user,
                'roles' => $user->roles->pluck('nom')->values(),
            ]
        ]);
    }

    /**
     * POST /api/users/{userId}/roles/sync
     * Body: { "roles": ["candidat"] } ou { "role_ids": [3] }
     */
    public function syncUserRoles(Request $request, $userId)
    {
        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'], 404);

        $roleIds = $this->resolveRoleIds($request);
        if (empty($roleIds)) {
            return response()->json(['success'=>false,'message'=>'Aucun rôle valide fourni'], 422);
        }

        DB::transaction(fn() => $user->roles()->sync($roleIds));

        $user->load('roles:id,nom');

        return response()->json([
            'success' => true,
            'message' => 'Rôles synchronisés',
            'data'    => [
                'user'  => $user,
                'roles' => $user->roles->pluck('nom')->values(),
            ]
        ]);
    }

    // ============================ HELPERS ============================

    /**
     * Retourne des ids de rôles depuis:
     * - role_ids: [1,2]  (prioritaire)
     * - roles: ["Administrateur","recruteur"] (case-insensitive)
     */
    private function resolveRoleIds(Request $request): array
    {
        // 1) ids directs
        $ids = collect($request->input('role_ids', []))
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        if (!empty($ids)) {
            return Role::whereIn('id', $ids)->pluck('id')->all();
        }

        // 2) noms (insensible à la casse, espaces tolérés)
        $names = $request->input('roles', []);
        $names = is_array($names) ? $names : [$names];

        $lc = collect($names)->filter()->map(fn($r) => mb_strtolower(trim((string) $r)));
        if ($lc->isEmpty()) return [];

        return Role::whereIn(DB::raw('LOWER(nom)'), $lc)->pluck('id')->all();
    }
}
