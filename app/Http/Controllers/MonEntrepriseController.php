<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Entreprise;
use App\Models\Offre;
use App\Models\Publicite;
use Illuminate\Support\Facades\Storage;

class MonEntrepriseController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        
        // Récupérer l'entreprise du recruteur
        $entreprise = Entreprise::where('user_id', $user->id)->first();
        
        if (!$entreprise) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune entreprise trouvée'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $entreprise
        ]);
    }
    
    public function update(Request $request)
    {
        $user = $request->user();
        $entreprise = Entreprise::where('user_id', $user->id)->firstOrFail();
        
        $validated = $request->validate([
            'nom_entreprise' => 'sometimes|string|max:255',
            'secteur_activite' => 'nullable|string',
            'taille_entreprise' => 'nullable|string',
            'email' => 'nullable|email',
            'telephone' => 'nullable|string',
            'site_web' => 'nullable|url',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string',
            'pays' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $entreprise->update($validated);
        
        return response()->json([
            'success' => true,
            'data' => $entreprise,
            'message' => 'Entreprise mise à jour avec succès'
        ]);
    }
    
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
        
        $user = $request->user();
        $entreprise = Entreprise::where('user_id', $user->id)->firstOrFail();
        
        if ($request->hasFile('logo')) {
            // Supprimer l'ancien logo si existe
            if ($entreprise->logo) {
                Storage::delete('public/' . $entreprise->logo);
            }
            
            $path = $request->file('logo')->store('logos', 'public');
            $entreprise->logo = $path;
            $entreprise->save();
        }
        
        return response()->json([
            'success' => true,
            'data' => $entreprise,
            'message' => 'Logo mis à jour avec succès'
        ]);
    }
    
    public function getStats(Request $request)
    {
        $user = $request->user();
        $entreprise = Entreprise::where('user_id', $user->id)->first();
        
        if (!$entreprise) {
            return response()->json([
                'success' => false,
                'data' => []
            ]);
        }
        
        $stats = [
            'total_offres' => Offre::where('recruteur_id', $user->id)->count(),
            'offres_actives' => Offre::where('recruteur_id', $user->id)
                ->where('statut', 'publiee')
                ->where('date_expiration', '>', now())
                ->count(),
            'offres_brouillon' => Offre::where('recruteur_id', $user->id)
                ->where('statut', 'brouillon')
                ->count(),
            'total_publicites' => Publicite::where('entreprise_id', $entreprise->id)->count(),
            'publicites_actives' => Publicite::where('entreprise_id', $entreprise->id)
                ->where('statut', 'active')
                ->count(),
            'candidatures_recues' => 0 // À implémenter selon votre modèle de candidatures
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}