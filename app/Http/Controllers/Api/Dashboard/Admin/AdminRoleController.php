<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    // 
    public function index()
    {
        $roles = Role::withCount('users')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    // Créer un nouveau rôle
    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255|unique:roles',
            'description' => 'required|string|max:500'
        ]);

        $role = Role::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Rôle créé avec succès',
            'data' => $role
        ], 201);
    }

    // Afficher un rôle spécifique
    public function show($id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    // Mettre à jour un rôle
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|string|max:255|unique:roles,nom,' . $id,
            'description' => 'sometimes|string|max:500'
        ]);

        $role->update($request->only(['nom', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour avec succès',
            'data' => $role
        ]);
    }

    // Supprimer un rôle
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un rôle assigné à des utilisateurs'
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rôle supprimé avec succès'
        ]);
    }

    // Attribuer un rôle à un utilisateur
    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        if (!$user->hasRole($role->nom)) {
            $user->roles()->attach($role->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rôle attribué avec succès',
            'data' => $user->load('roles')
        ]);
    }

    // Retirer un rôle d'un utilisateur
    public function removeRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($request->user_id);
        $user->roles()->detach($request->role_id);

        return response()->json([
            'success' => true,
            'message' => 'Rôle retiré avec succès',
            'data' => $user->load('roles')
        ]);
    }

    // Changer le rôle principal d'un utilisateur
    public function changeUserRole(Request $request, $userId)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($userId);
        $newRole = Role::findOrFail($request->role_id);

        // Retirer tous les rôles actuels
        $user->roles()->detach();
        
        // Attribuer le nouveau rôle
        $user->roles()->attach($newRole->id);

        return response()->json([
            'success' => true,
            'message' => 'Rôle utilisateur modifié avec succès',
            'data' => $user->load('roles')
        ]);
    }
}




