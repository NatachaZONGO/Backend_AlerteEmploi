<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categorie;
use Illuminate\Http\Request;

class AdminCategorieController extends Controller
{
    // Lister toutes les catégories
    public function index(){
        $categories = Categorie::withCount(['candidats', 'offres'])->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // Créer une nouvelle catégorie
    public function store(Request $request){
        $request->validate([
            'nom' => 'required|string|max:255|unique:categories',
            'description' => 'sometimes|nullable|string'
        ]);

        $categorie = Categorie::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès',
            'data' => $categorie
        ], 201);
    }

    // Afficher une catégorie spécifique
    public function show($id){
        $categorie = Categorie::withCount(['candidats', 'offres'])->find($id);

        if (!$categorie) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $categorie
        ]);
    }
    // Mettre à jour une catégorie
    public function update(Request $request, $id){
        $categorie = Categorie::find($id);

        if (!$categorie) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string'
        ]);

        $categorie->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour avec succès',
            'data' => $categorie
        ]);
    }

    // Supprimer une catégorie
    public function destroy($id)
    {
        $categorie = Categorie::find($id);

        if (!$categorie) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Vérifier s'il y a des candidats ou offres liés
        if ($categorie->candidats()->count() > 0 || $categorie->offres()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer cette catégorie car elle est utilisée par des candidats ou des offres'
            ], 400);
        }

        $categorie->delete();

        return response()->json([
            'success' => true,
            'message' => 'Catégorie supprimée avec succès'
        ]);
    }

}
