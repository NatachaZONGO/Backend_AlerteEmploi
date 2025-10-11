<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pays;
use Illuminate\Http\Request;

class AdminPaysController extends Controller
{
    public function index(){
        $pays = Pays::withCount('candidatProfiles')->get();
        return response()->json(['success' => true, 'data' => $pays]);
    }

    public function show($id){
        $p = Pays::withCount('candidatProfiles')->find($id);
        if (!$p) return response()->json(['success'=>false,'message'=>'Pays non trouvé'], 404);
        return response()->json(['success'=>true,'data'=>$p]);
    }

    public function store(Request $request)
    {
        // Règles de base
        $base = $request->validate([
            'nom'           => 'required|string|max:255',
            'code_iso'      => 'required|string|max:3',
            'indicatif_tel' => 'sometimes|nullable|string|max:10',
            'is_active'     => 'sometimes|boolean',
        ]);

        $payload = $base;

        // --- Gestion drapeau (fichier OU string)
        if ($request->hasFile('flag') || $request->hasFile('flagFile')) {
            $file = $request->file('flag') ?? $request->file('flagFile');
            $request->validate([
                'flag' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            ]);
            $payload['flag_image'] = $file->store('flags', 'public'); // ex: flags/xxx.jpg
        } elseif ($request->has('flag') || $request->has('flagUrl')) {
            $val = $request->input('flag') ?? $request->input('flagUrl');
            if ($val === null || $val === '') {
                $payload['flag_image'] = null;
            } elseif (preg_match('#^https?://#i', $val)) {
                $request->validate(['flag' => 'string|url|max:255']);
                $payload['flag_image'] = $val; // URL externe
            } else {
                // Chemin relatif connu
                $request->validate(['flag' => 'string|max:255']);
                $payload['flag_image'] = $val;
            }
        }

        $pays = \App\Models\Pays::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pays créé avec succès',
            'data'    => $pays,
        ], 201);
    }


    public function update(Request $request, $id)
    {
        $pays = \App\Models\Pays::find($id);
        if (!$pays) {
            return response()->json(['success' => false, 'message' => 'Pays non trouvé'], 404);
        }

        $base = $request->validate([
            'nom'           => 'sometimes|string|max:255',
            'code_iso'      => 'sometimes|string|max:3',
            'indicatif_tel' => 'sometimes|nullable|string|max:10',
            'is_active'     => 'sometimes|boolean',
        ]);

        $payload = $base;

        if ($request->hasFile('flag') || $request->hasFile('flagFile')) {
            $file = $request->file('flag') ?? $request->file('flagFile');
            $request->validate([
                'flag' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120',
            ]);
            $payload['flag_image'] = $file->store('flags', 'public');
        } elseif ($request->has('flag') || $request->has('flagUrl')) {
            $val = $request->input('flag') ?? $request->input('flagUrl');
            if ($val === null || $val === '') {
                $payload['flag_image'] = null; // efface
            } elseif (preg_match('#^https?://#i', $val)) {
                $request->validate(['flag' => 'string|url|max:255']);
                $payload['flag_image'] = $val;
            } else {
                $request->validate(['flag' => 'string|max:255']);
                $payload['flag_image'] = $val;
            }
        }

        $pays->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Pays mis à jour avec succès',
            'data'    => $pays->fresh(),
        ]);
    }

    public function destroy($id){
        $p = Pays::find($id);
        if (!$p) return response()->json(['success'=>false,'message'=>'Pays non trouvé'], 404);

        if ($p->candidatProfiles()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Ce pays est utilisé par des candidats, il ne peut pas être supprimé'
            ], 400);
        }

        $p->delete();
        return response()->json(['success'=>true,'message'=>'Pays supprimé avec succès']);
    }
}
