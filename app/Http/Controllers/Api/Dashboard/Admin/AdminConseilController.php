<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conseil;
use Illuminate\Http\Request;

class AdminConseilController extends Controller
{
    /**
     * Liste tous les conseils
     */
    public function index()
    {
        $conseils = Conseil::orderBy('date_publication', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $conseils
        ]);
    }

    /**
     * Créer un nouveau conseil
     */
    public function store(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string',
            'date_publication' => 'required|date'
        ]);

        $conseil = Conseil::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Conseil créé avec succès',
            'data' => $conseil
        ], 201);
    }

    /**
     * Afficher un conseil spécifique
     */
    public function show($id)
    {
        $conseil = Conseil::find($id);

        if (!$conseil) {
            return response()->json([
                'success' => false,
                'message' => 'Conseil non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $conseil
        ]);
    }

    /**
     * Mettre à jour un conseil
     */
    public function update(Request $request, $id)
    {
        $conseil = Conseil::find($id);

        if (!$conseil) {
            return response()->json([
                'success' => false,
                'message' => 'Conseil non trouvé'
            ], 404);
        }

        $request->validate([
            'titre' => 'sometimes|required|string|max:255',
            'contenu' => 'sometimes|required|string',
            'date_publication' => 'sometimes|required|date'
        ]);

        $conseil->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Conseil mis à jour avec succès',
            'data' => $conseil
        ]);
    }

    /**
     * Supprimer un conseil
     */
    public function destroy($id)
    {
        $conseil = Conseil::find($id);

        if (!$conseil) {
            return response()->json([
                'success' => false,
                'message' => 'Conseil non trouvé'
            ], 404);
        }

        $conseil->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conseil supprimé avec succès'
        ]);
    }
}


