<?php

namespace App\Http\Controllers\Api\Dashboard\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // AJOUTÉ: Import de la classe Rule
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Entreprise;
use App\Notifications\EntrepriseValidated;
use App\Notifications\EntrepriseRejected;
use App\Notifications\NouvelleEntrepriseNotification;
use App\Models\Role;
use App\Models\Pays;


class AdminDashboardController extends Controller
{
    /**
     * Affichage du tableau de bord administrateur
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $stats = [
            'total_utilisateurs'                => User::count(),
            'total_candidats'                   => User::whereHas('roles', fn($q) => $q->where('nom','candidat'))->count(),
            'total_recruteurs'                  => User::whereHas('roles', fn($q) => $q->where('nom','recruteur'))->count(),
            'entreprises_en_attente'            => Entreprise::where('statut','en attente')->count(),
            'entreprises_validees'              => Entreprise::where('statut','valide')->count(),
            'total_offres'                      => \App\Models\Offre::count(),
            'offres_en_attente_validation'      => \App\Models\Offre::where('statut','en_attente_validation')->count(),
            'offres_publiees'                   => \App\Models\Offre::where('statut','publiee')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'stats'   => $stats,
                'message' => 'Bienvenue sur le tableau de bord administrateur',
            ],
        ]);
    }

    /** Liste paginée **/
    public function entreprisesIndex(Request $request)
    {
        $query = Entreprise::with(['user','pays'])
            ->select(
                'id','user_id','nom_entreprise','description','site_web','telephone','email',
                'secteur_activite','logo','pays_id','statut','motif_rejet','created_at','updated_at'
            )
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('statut', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('nom_entreprise','like',"%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('email','like',"%{$search}%"));
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate((int)$request->query('per_page', 15)),
        ]);
    }

    /**
     * Créer une entreprise
     * POST /api/admin/entreprises
     */
    public function entreprisesStore(Request $request)
    {
        try {
            // 1) Règles de base (sans logo)
            $baseRules = [
                'user_id'          => ['required','exists:users,id'],
                'nom_entreprise'   => ['required','string','max:255'],
                'description'      => ['nullable','string'],
                'pays_id'          => ['nullable','exists:pays,id'],
                'site_web'         => ['nullable','string','max:255'],
                'telephone'        => ['nullable','string','max:30'],
                'email'            => ['nullable','email','max:255'],
                'secteur_activite' => ['required','in:primaire,secondaire,tertiaire,quaternaire'],
                'statut'           => ['nullable', Rule::in(['en attente','valide','refuse'])],
                // `logo` sera validé conditionnellement plus bas
            ];

            $validated = $request->validate($baseRules);
            $validated['statut'] = $validated['statut'] ?? 'en attente';

            // 2) Validation conditionnelle du logo (un seul champ)
            if ($request->hasFile('logo')) {
                // Si un fichier a été envoyé dans `logo`
                $request->validate([
                    'logo' => ['image','mimes:jpg,jpeg,png,webp,gif','max:5120'], // 5MB
                ]);
                $path = $request->file('logo')->store('logos', 'public'); // ex: logos/xxx.jpg
                $validated['logo'] = $path;
            } elseif ($request->filled('logo')) {
                // Sinon, si c'est une string → on attend une URL
                $request->validate([
                    'logo' => ['string','url','max:255'],
                ]);
                // `$validated['logo']` est déjà dans $request->logo
                $validated['logo'] = $request->input('logo');
            } else {
                // Rien fourni → logo null
                $validated['logo'] = null;
            }

            $entreprise = Entreprise::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Entreprise créée avec succès',
                'data'    => $entreprise->load(['user','pays']),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Store entreprise error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur',
            ], 500);
        }
    }
    /**
     * Détails d'une entreprise
     * GET /api/admin/entreprises/{id}
     */
    public function entreprisesShow($id)
    {
        $entreprise = Entreprise::with(['user','pays'])->find($id);
        if (!$entreprise) {
            return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'], 404);
        }
        return response()->json(['success'=>true,'data'=>$entreprise]);
    }
    /**
     * Mettre à jour une entreprise
     * PUT /api/admin/entreprises/{id}
     */
   public function entreprisesUpdate(Request $request, $id)
{
    $entreprise = Entreprise::find($id);
    if (!$entreprise) {
        return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'],404);
    }

    // 1) Validation de base (clé présente => prise en compte)
    $validated = $request->validate([
        'nom_entreprise'   => ['sometimes','string','max:255'],
        'description'      => ['sometimes','nullable','string'],
        'pays_id'          => ['sometimes','nullable','exists:pays,id'],
        'site_web'         => ['sometimes','nullable','string','max:255'],
        'telephone'        => ['sometimes','nullable','string','max:30'],
        'email'            => ['sometimes','nullable','email','max:255'],
        'secteur_activite' => ['sometimes','nullable','in:primaire,secondaire,tertiaire,quaternaire'],
        'statut'           => ['sometimes', Rule::in(['en attente','valide','refuse'])],
        'motif_rejet'      => ['sometimes','nullable','string','max:500'],
        // logo géré plus bas
    ]);

    // 2) Gestion logo (multipart OU string)
    if ($request->hasFile('logo')) {
        $request->validate([
            'logo' => ['image','mimes:jpg,jpeg,png,webp,gif','max:5120'],
        ]);
        $validated['logo'] = $request->file('logo')->store('logos', 'public');
    } elseif ($request->has('logo')) {
        $val = $request->input('logo');
        if ($val === null || $val === '') {
            $validated['logo'] = null;
        } elseif (preg_match('#^https?://#i', $val)) {
            $request->validate(['logo' => ['string','url','max:255']]);
            $validated['logo'] = $val;
        } else {
            // Chemin relatif existant (ex: logos/abc.jpg)
            $request->validate(['logo' => ['string','max:255']]);
            $validated['logo'] = $val;
        }
    }

    // 3) Remplir puis sauver (montre ce qui va vraiment changer)
    $entreprise->fill($validated);
    $dirty = $entreprise->getDirty(); // DEBUG TEMP
    $entreprise->save();

    return response()->json([
        'success' => true,
        'message' => 'Entreprise mise à jour avec succès',
        'data'    => $entreprise->fresh()->load(['user','pays']),
        // 'debug' => ['validated'=>$validated, 'dirty'=>$dirty], // décommente 1 min si besoin
    ]);
}



    /**
     * Supprimer une entreprise
     * DELETE /api/admin/entreprises/{id}
     */
   public function entreprisesDestroy($id)
{
    $entreprise = Entreprise::find($id);
    if (!$entreprise) {
        return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'],404);
    }

    try {
        $entreprise->delete();
        return response()->json(['success'=>true,'message'=>'Entreprise supprimée avec succès']);
    } catch (\Illuminate\Database\QueryException $e) {
        \Log::warning('Suppression entreprise impossible', ['err'=>$e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Cette entreprise ne peut pas être supprimée car des données y sont liées.',
        ], 409);
    }
}


/**
 * Récupérer la liste des entreprises en attente de validation
 *
 * @return \Illuminate\Http\JsonResponse
 */
    public function getPendingEntreprises()
    {
        $entreprises = Entreprise::with(['user','pays'])
            ->where('statut','en attente')
            ->orderBy('created_at','desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'entreprises' => $entreprises,
                'total'       => $entreprises->count(),
            ],
        ]);
    }


    /**
     * Valider une entreprise
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
   public function validateEntrepriseByCompanyId($id)
{
    $entreprise = Entreprise::with(['user','pays'])->find($id);
    if (!$entreprise) {
        return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'],404);
    }
    if ($entreprise->statut !== 'en attente') {
        return response()->json(['success'=>false,'message'=>'Cette entreprise a déjà été traitée'],400);
    }

    $entreprise->update([
        'statut'      => 'valide',
        'motif_rejet' => null, // nettoie l’ancien motif
    ]);

    // ✅ ne jamais casser l’API pour un email
    try {
        if ($entreprise->user) {
            $entreprise->user->notify(new \App\Notifications\EntrepriseValidated());
        }
    } catch (\Throwable $e) {
        \Log::warning('Notification validateEntreprise échouée', ['err' => $e->getMessage()]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Entreprise validée avec succès.',
        'data'    => ['entreprise' => $entreprise->fresh()->load(['user','pays'])],
    ]);
}

public function rejectEntrepriseByCompanyId($id, \Illuminate\Http\Request $request)
{
    $entreprise = Entreprise::with(['user','pays'])->find($id);
    if (!$entreprise) {
        return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'],404);
    }
    if ($entreprise->statut !== 'en attente') {
        return response()->json(['success'=>false,'message'=>'Cette entreprise a déjà été traitée'],400);
    }

    $validated = $request->validate(['motif' => 'required|string|max:500']);

    $entreprise->update([
        'statut'      => 'refuse',
        'motif_rejet' => $validated['motif'],
    ]);

    try {
        if ($entreprise->user) {
            $entreprise->user->notify(new \App\Notifications\EntrepriseRejected($validated['motif']));
        }
    } catch (\Throwable $e) {
        \Log::warning('Notification rejectEntreprise échouée', ['err' => $e->getMessage()]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Entreprise rejetée.',
        'data'    => ['entreprise' => $entreprise->fresh()->load(['user','pays'])],
    ]);
}

// (Optionnel) revalidation d’un refusé
public function revalidateEntrepriseByCompanyId($id)
{
    $entreprise = Entreprise::with(['user','pays'])->find($id);
    if (!$entreprise) {
        return response()->json(['success'=>false,'message'=>'Entreprise non trouvée'],404);
    }
    if ($entreprise->statut !== 'refuse') {
        return response()->json(['success'=>false,'message'=>'Seules les entreprises refusées peuvent être revalidées'],400);
    }

    $entreprise->update([
        'statut'      => 'valide',
        'motif_rejet' => null,
    ]);

    try {
        if ($entreprise->user) {
            $entreprise->user->notify(new \App\Notifications\EntrepriseValidated());
        }
    } catch (\Throwable $e) {
        \Log::warning('Notification revalidateEntreprise échouée', ['err' => $e->getMessage()]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Entreprise revalidée avec succès.',
        'data'    => ['entreprise' => $entreprise->fresh()->load(['user','pays'])],
    ]);
}

}