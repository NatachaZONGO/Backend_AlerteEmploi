<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;
use App\Models\User;

class RoleController extends Controller
{
    /**
     * Lister tous les rôles
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Role::orderBy('nom')->get()
        ]);
    }

    /**
     * Créer un rôle
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'nom' => 'required|string|max:100|unique:roles,nom',
            'description' => 'nullable|string|max:255'
        ]);

        if ($v->fails()) {
            return response()->json(['success'=>false, 'errors'=>$v->errors()], 422);
        }

        $role = Role::create($v->validated());

        return response()->json([
            'success' => true,
            'message' => 'Rôle créé avec succès',
            'data' => $role
        ], 201);
    }

    /**
     * Afficher un rôle
     */
    public function show($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'],404);
        }
        return response()->json(['success'=>true,'data'=>$role]);
    }

    /**
     * Mettre à jour un rôle
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'],404);
        }

        $v = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:100|unique:roles,nom,'.$id,
            'description' => 'sometimes|nullable|string|max:255'
        ]);

        if ($v->fails()) {
            return response()->json(['success'=>false,'errors'=>$v->errors()],422);
        }

        $role->update($v->validated());

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour',
            'data' => $role
        ]);
    }

    /**
     * Supprimer un rôle
     */
    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['success'=>false,'message'=>'Rôle introuvable'],404);
        }

        $role->delete();
        return response()->json(['success'=>true,'message'=>'Rôle supprimé']);
    }

    // ================== Gestion des rôles pour les utilisateurs ==================

    /**
     * Remplacer tous les rôles d’un utilisateur
     */
    public function syncUserRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,nom'
        ]);

        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'],404);

        $roleIds = Role::whereIn('nom',$request->roles)->pluck('id')->all();
        $user->roles()->sync($roleIds);

        return response()->json(['success'=>true,'message'=>'Rôles synchronisés','data'=>$user->load('roles')]);
    }

    /**
     * Ajouter des rôles à un utilisateur
     */
    public function attachUserRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,nom'
        ]);

        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'],404);

        $roleIds = Role::whereIn('nom',$request->roles)->pluck('id')->all();
        $user->roles()->syncWithoutDetaching($roleIds);

        return response()->json(['success'=>true,'message'=>'Rôles ajoutés','data'=>$user->load('roles')]);
    }

    /**
     * Retirer des rôles à un utilisateur
     */
    public function detachUserRoles(Request $request, $userId)
    {
        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,nom'
        ]);

        $user = User::find($userId);
        if (!$user) return response()->json(['success'=>false,'message'=>'Utilisateur introuvable'],404);

        $roleIds = Role::whereIn('nom',$request->roles)->pluck('id')->all();
        $user->roles()->detach($roleIds);

        return response()->json(['success'=>true,'message'=>'Rôles retirés','data'=>$user->load('roles')]);
    }
}
