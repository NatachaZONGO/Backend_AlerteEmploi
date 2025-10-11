<?php

namespace App\Http\Controllers;

use App\Models\Conseil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ConseilController extends Controller
{
    public function index(Request $request)
    {
        $q = Conseil::query();

        if ($s = $request->get('search')) {
            $q->where(function ($x) use ($s) {
                $x->where('titre', 'like', "%{$s}%")
                  ->orWhere('contenu', 'like', "%{$s}%")
                  ->orWhere('tags', 'like', "%{$s}%");
            });
        }

        if ($v = $request->get('categorie'))    $q->where('categorie', $v);
        if ($v = $request->get('type_conseil')) $q->where('type_conseil', $v);
        if ($v = $request->get('niveau'))       $q->where('niveau', $v);
        if ($v = $request->get('statut'))       $q->where('statut', $v);
        if ($v = $request->get('auteur'))       $q->where('auteur', 'like', "%{$v}%");

        if ($from = $request->get('date_debut')) $q->whereDate('date_publication', '>=', $from);
        if ($to   = $request->get('date_fin'))   $q->whereDate('date_publication', '<=', $to);

        $q->orderByRaw('CASE WHEN date_publication IS NULL THEN 1 ELSE 0 END')
          ->orderBy('date_publication', 'desc')
          ->orderBy('created_at', 'desc');

        $perPage = (int) ($request->get('per_page') ?? 10);
        $page = $q->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $page,
        ]);
    }

    public function show($id)
    {
        $c = Conseil::find($id);
        if (!$c) {
            return response()->json(['success' => false, 'message' => 'Conseil non trouvé'], 404);
        }
        return response()->json(['success' => true, 'data' => $c]);
    }

    public function recent(Request $request)
    {
        $limit = (int) ($request->get('limit') ?? 5);

        $items = Conseil::query()
            ->whereNotNull('date_publication')
            ->where('date_publication', '>=', now()->subMonths(3))
            ->orderBy('date_publication', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'titre'            => 'required|string|min:5|max:255',
            'contenu'          => 'required|string|min:10',
            'categorie'        => 'nullable|string|max:100',
            'type_conseil'     => 'nullable|in:article,conseil_rapide,liste,video,infographie,checklist,template',
            'niveau'           => 'nullable|in:debutant,intermediaire,avance,expert',
            'statut'           => 'nullable|in:brouillon,en_revision,programme,publie,archive,suspendu',
            'tags'             => 'nullable|string|max:255',
            'auteur'           => 'nullable|string|max:120',
            'date_publication' => 'nullable|date',
            'vues'             => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreurs de validation', 'errors' => $v->errors()], 422);
        }

        $data = $request->only([
            'titre','contenu','categorie','type_conseil','niveau','statut','tags','auteur','date_publication','vues'
        ]);

        // Defaults
        $data['type_conseil'] = $data['type_conseil'] ?? 'article';
        $data['niveau']       = $data['niveau'] ?? 'debutant';
        $data['statut']       = $data['statut'] ?? 'brouillon';
        $data['vues']         = $data['vues'] ?? 0;

        // Sécurité : si publie sans date, on force maintenant (le Model le fait aussi)
        if (($data['statut'] ?? null) === 'publie' && empty($data['date_publication'])) {
            $data['date_publication'] = now();
        }

        $c = Conseil::create($data);

        return response()->json(['success' => true, 'message' => 'Conseil créé avec succès', 'data' => $c], 201);
    }

    public function update(Request $request, $id)
    {
        $c = Conseil::find($id);
        if (!$c) {
            return response()->json(['success' => false, 'message' => 'Conseil non trouvé'], 404);
        }

        $v = Validator::make($request->all(), [
            'titre'            => 'sometimes|required|string|min:5|max:255',
            'contenu'          => 'sometimes|required|string|min:10',
            'categorie'        => 'nullable|string|max:100',
            'type_conseil'     => 'nullable|in:article,conseil_rapide,liste,video,infographie,checklist,template',
            'niveau'           => 'nullable|in:debutant,intermediaire,avance,expert',
            'statut'           => 'nullable|in:brouillon,en_revision,programme,publie,archive,suspendu',
            'tags'             => 'nullable|string|max:255',
            'auteur'           => 'nullable|string|max:120',
            'date_publication' => 'nullable|date',
            'vues'             => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreurs de validation', 'errors' => $v->errors()], 422);
        }

        $payload = $request->only([
            'titre','contenu','categorie','type_conseil','niveau','statut','tags','auteur','date_publication','vues'
        ]);

        // Si on passe à "publie" sans date, on force une date
        if (($payload['statut'] ?? null) === 'publie' && empty($payload['date_publication'])) {
            $payload['date_publication'] = $c->date_publication ?? now();
        }

        $c->update($payload);

        return response()->json(['success' => true, 'message' => 'Conseil mis à jour avec succès', 'data' => $c]);
    }

    public function destroy($id)
    {
        $c = Conseil::find($id);
        if (!$c) {
            return response()->json(['success' => false, 'message' => 'Conseil non trouvé'], 404);
        }

        $c->delete();

        return response()->json(['success' => true, 'message' => 'Conseil supprimé avec succès']);
    }
}
