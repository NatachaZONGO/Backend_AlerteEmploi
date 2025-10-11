<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Offre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Jobs\NotifyCandidatesOfOffer;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;     
use App\Models\Candidat;          
use Illuminate\Support\Facades\Config; 



class OffreController extends Controller
{
    /**
     * Lister toutes les offres avec filtres
     */
    public function index(Request $request)
    {
        // 1) Fermer automatiquement les offres expirées
        $offresExpirees = Offre::where('date_expiration', '<', now())
            ->whereIn('statut', ['publiee', 'validee', 'brouillon', 'en_attente_validation'])
            ->update(['statut' => 'fermee']);

        // 2) Retirer automatiquement la mise en avant expirée
        $unfeatured = Offre::whereNotNull('featured_until')
            ->where('featured_until', '<', now())
            ->where('sponsored_level', '>', 0)
            ->update([
                'sponsored_level' => 0,
                'featured_until'  => null
            ]);

        $query = Offre::with(['entreprise', 'categorie', 'validateur']);

        // ---- Filtres existants
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('type_offre')) {
            $query->where('type_offre', $request->type_offre);
        }
        if ($request->filled('localisation')) {
            $query->where('localisation', 'like', '%' . $request->localisation . '%');
        }
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }
        if ($request->filled('experience')) {
            $query->where('experience', 'like', '%' . $request->experience . '%');
        }
        if ($request->filled('recruteur_id')) {
            $query->where('recruteur_id', $request->recruteur_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // ---- Filtres "vedette"
        // sponsored=yes => sponsored_level > 0 (et si tu veux, encore “actif”)
        if ($request->filled('sponsored')) {
            if ($request->sponsored === 'yes') {
                $query->where('sponsored_level', '>', 0)
                      ->where(function ($q) {
                          $q->whereNull('featured_until')
                            ->orWhere('featured_until', '>=', now());
                      });
            } elseif ($request->sponsored === 'no') {
                $query->where('sponsored_level', 0);
            }
        }

        if ($request->filled('sponsored_level')) {
            $query->where('sponsored_level', (int)$request->sponsored_level);
        }

        if ($request->filled('sponsored_level_min')) {
            $query->where('sponsored_level', '>=', (int)$request->sponsored_level_min);
        }

        $offres = $query->orderBy('created_at', 'desc')
                        ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $offres,
            'meta' => [
                'offres_fermees_automatiquement' => $offresExpirees,
                'offres_unfeatured_automatiquement' => $unfeatured,
            ]
        ]);
    }

    /**
     * Afficher une offre spécifique
     */
    public function show($id)
    {
        $offre = Offre::with(['recruteur', 'entreprise', 'categorie', 'validateur'])->find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        return response()->json(['success' => true, 'data' => $offre]);
    }

    /**
     * Créer une nouvelle offre
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre'            => 'required|string|max:255',
            'description'      => 'required|string',
            'experience'       => 'required|string|max:255',
            'localisation'     => 'required|string|max:255',
            'type_offre'       => 'required|in:emploi,stage',
            'type_contrat'     => 'required|string|max:255',
            'date_expiration'  => 'required|date|after:today',
            'salaire'          => 'nullable|numeric|min:0',
            'categorie_id'     => 'required|exists:categories,id',
            'recruteur_id'     => 'required|exists:users,id',

            // --- vedette (optionnel à la création)
            'sponsored_level'  => 'nullable|integer|min:0|max:3',
            'featured_until'   => 'nullable|date|after:now',
            'statut'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $payload = $request->only([
            'titre','description','experience','localisation','type_offre','type_contrat',
            'date_expiration','salaire','categorie_id','recruteur_id','statut',
            'sponsored_level','featured_until'
        ]);

        // Valeurs par défaut
        $payload['statut'] = $payload['statut'] ?? 'brouillon';
        $payload['sponsored_level'] = isset($payload['sponsored_level']) ? (int)$payload['sponsored_level'] : 0;

        $offre = Offre::create($payload);

        return response()->json([
            'success' => true,
            'message' => 'Offre créée avec succès',
            'data'    => $offre->load(['entreprise', 'categorie'])
        ], 201);
    }

    /**
     * Mettre à jour une offre
     */
    public function update(Request $request, $id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'titre'            => 'sometimes|string|max:255',
            'description'      => 'sometimes|string',
            'experience'       => 'sometimes|string|max:255',
            'localisation'     => 'sometimes|string|max:255',
            'type_offre'       => 'sometimes|in:emploi,stage',
            'type_contrat'     => 'sometimes|string|max:255',
            'date_expiration'  => 'sometimes|date|after:today',
            'salaire'          => 'nullable|numeric|min:0',
            'categorie_id'     => 'sometimes|exists:categories,id',
            'statut'           => 'sometimes|string',

            // --- vedette
            'sponsored_level'  => 'sometimes|integer|min:0|max:3',
            'featured_until'   => 'sometimes|nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()
            ], 422);
        }

        $offre->update($request->only([
            'titre','description','experience','localisation',
            'type_offre','type_contrat','date_expiration','salaire',
            'categorie_id','statut',
            'sponsored_level','featured_until',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Offre mise à jour avec succès',
            'data'    => $offre->load(['entreprise', 'categorie'])
        ]);
    }

    /**
     * Supprimer une offre
     */
    public function destroy($id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        $offre->delete();

        return response()->json(['success' => true, 'message' => 'Offre supprimée avec succès']);
    }

    /**
     * Soumettre une offre pour validation
     */
    public function soumettreValidation($id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        if ($offre->statut !== 'brouillon') {
            return response()->json(['success' => false, 'message' => 'Seules les offres en brouillon peuvent être soumises pour validation'], 400);
        }

        $offre->update(['statut' => 'en_attente_validation']);

        return response()->json([
            'success' => true,
            'message' => 'Offre soumise pour validation avec succès',
            'data'    => $offre->load(['entreprise', 'categorie'])
        ]);
    }

    /**
     * Valider une offre
     */
    public function valider($id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        if (!in_array($offre->statut, ['en_attente_validation', 'brouillon', 'rejetee'])) {
            return response()->json(['success' => false, 'message' => 'Cette offre ne peut pas être validée (statut actuel: '.$offre->statut.')'], 400);
        }

        $offre->update([
            'statut'          => 'validee',
            'date_validation' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre validée avec succès',
            'data'    => $offre->load(['entreprise', 'categorie', 'validateur'])
        ]);
    }

    /**
     * Rejeter une offre
     */
    public function rejeter(Request $request, $id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        if (!in_array($offre->statut, ['en_attente_validation', 'brouillon', 'validee'])) {
            return response()->json(['success' => false, 'message' => 'Cette offre ne peut pas être rejetée (statut actuel: '.$offre->statut.')'], 400);
        }

        $validator = Validator::make($request->all(), [
            'motif_rejet' => 'required|string|max:500'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Motif de rejet requis', 'errors' => $validator->errors()], 422);
        }

        $offre->update([
            'statut'          => 'rejetee',
            'motif_rejet'     => $request->motif_rejet,
            'date_validation' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre rejetée',
            'data'    => $offre->load(['entreprise', 'categorie', 'validateur'])
        ]);
    }

    /**
 * Publier une offre validée
 */
public function publier($id)
{
    $offre = Offre::with(['categorie','entreprise'])->find($id);
    if (!$offre) {
        return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
    }
    if ($offre->statut !== 'validee') {
        return response()->json(['success' => false, 'message' => 'Seules les offres validées peuvent être publiées'], 400);
    }

    $offre->update([
        'statut'           => 'publiee',
        'date_publication' => now(),
    ]);

    // Lien front
    $frontend = Config::get('app.frontend_url', 'http://localhost:4200');
    $lienOffre = rtrim($frontend, '/') . '/offres/' . $offre->id;

    // Destinataires = tous les candidats de la même catégorie
    $usersCandidats = Candidat::with('user')
        ->where('categorie_id', $offre->categorie_id)
        ->whereHas('user', fn($q) => $q->where('statut', 'actif'))
        ->get()
        ->pluck('user')
        ->filter();

    // Notification in-app minimale
    $titre = 'Nouvelle offre publiée';
    $message = sprintf(
        "Une nouvelle offre « %s » a été publiée dans la catégorie %s.\n\nVoir l’offre : %s",
        $offre->titre,
        optional($offre->categorie)->nom ?? '—',
        $lienOffre
    );
    Notification::pushToUsers($usersCandidats, $titre, $message);

    // Emails (job existant)
    NotifyCandidatesOfOffer::dispatch($offre->id);

    return response()->json([
        'success' => true,
        'message' => 'Offre publiée avec succès',
        'data'    => $offre->load(['entreprise', 'categorie', 'validateur']),
    ]);
}

    /**
     * Fermer une offre (manuel)
     */
    public function fermer($id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }
        if (in_array($offre->statut, ['fermee', 'rejetee'])) {
            return response()->json(['success' => false, 'message' => 'Cette offre est déjà fermée ou rejetée'], 400);
        }

        $offre->update(['statut' => 'fermee']);

        return response()->json([
            'success' => true,
            'message' => 'Offre fermée avec succès',
            'data'    => $offre->load(['entreprise', 'categorie'])
        ]);
    }

    /**
     * Fermer automatiquement les offres expirées (manuel)
     */
    public function fermerOffresExpirees()
    {
        $offresExpirees = Offre::where('date_expiration', '<', now())
            ->whereIn('statut', ['publiee', 'validee', 'brouillon', 'en_attente_validation'])
            ->get();

        foreach ($offresExpirees as $offre) {
            $offre->update(['statut' => 'fermee']);
        }

        return response()->json([
            'success' => true,
            'message' => count($offresExpirees) . ' offre(s) fermée(s) automatiquement',
            'data'    => ['count' => count($offresExpirees)]
        ]);
    }

    /**
     * === MISE EN AVANT (VEDETTE) ===
     */

    // Mettre en vedette
    public function markAsFeatured(Request $request, $id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'sponsored_level' => 'nullable|integer|min:1|max:3',
            'duration_days'   => 'nullable|integer|min:1|max:365',
            'featured_until'  => 'nullable|date|after:now',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Erreurs de validation', 'errors' => $validator->errors()], 422);
        }

        $level = (int)($request->sponsored_level ?? 1);

        // calcul de featured_until : priorité au champ explicite, sinon duration_days, sinon 30 jours
        if ($request->filled('featured_until')) {
            $until = Carbon::parse($request->featured_until);
        } else {
            $days  = (int)($request->duration_days ?? 30);
            $until = now()->addDays($days);
        }

        $offre->update([
            'sponsored_level' => $level,
            'featured_until'  => $until,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offre mise en vedette',
            'data'    => $offre->fresh()->load(['entreprise','categorie'])
        ]);
    }

    // Retirer la mise en avant
    public function unfeature($id)
    {
        $offre = Offre::find($id);
        if (!$offre) {
            return response()->json(['success' => false, 'message' => 'Offre non trouvée'], 404);
        }

        $offre->update([
            'sponsored_level' => 0,
            'featured_until'  => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mise en avant retirée',
            'data'    => $offre->fresh()->load(['entreprise','categorie'])
        ]);
    }

    // Liste des offres vedettes actives (pratique pour la home)
    public function featured()
    {
        $rows = Offre::with(['entreprise','categorie'])
            ->where('sponsored_level', '>', 0)
            ->where(function ($q) {
                $q->whereNull('featured_until')
                  ->orWhere('featured_until', '>=', now());
            })
            ->orderByDesc('sponsored_level')
            ->orderByDesc('date_publication')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Statistiques globales
     */
    public function statistiques()
    {
        $stats = [
            'total_offres'            => Offre::count(),
            'en_attente_validation'   => Offre::where('statut', 'en_attente_validation')->count(),
            'validees'                => Offre::where('statut', 'validee')->count(),
            'publiees'                => Offre::where('statut', 'publiee')->count(),
            'rejetees'                => Offre::where('statut', 'rejetee')->count(),
            'brouillons'              => Offre::where('statut', 'brouillon')->count(),
            'fermees'                 => Offre::where('statut', 'fermee')->count(),
            'expirees'                => Offre::where('statut', 'expiree')->count(),
            'sponsored_actives'       => Offre::where('sponsored_level','>',0)
                                              ->where(function($q){
                                                  $q->whereNull('featured_until')
                                                    ->orWhere('featured_until','>=',now());
                                              })->count(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }

    /**
     * Dashboard global
     */
    public function dashboard()
    {
        $stats = [
            'total_offres'          => Offre::count(),
            'en_attente_validation' => Offre::where('statut', 'en_attente_validation')->count(),
            'validees'              => Offre::where('statut', 'validee')->count(),
            'publiees'              => Offre::where('statut', 'publiee')->count(),
        ];

        $offresRecentes = Offre::with(['entreprise', 'categorie'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'offres_recentes' => $offresRecentes
            ]
        ]);
    }

    // Route pour récupérer les offres du recruteur connecté
    public function mesOffres(Request $request)
    {
        $user = $request->user();
        
        // Vérifier que c'est un recruteur
        if (!$user->hasRole('Recruteur')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux recruteurs'
            ], 403);
        }

        $offres = Offre::where('recruteur_id', $user->id)
                    ->with(['categorie', 'candidatures'])
                    ->orderBy('created_at', 'desc')
                    ->get();

        return response()->json([
            'success' => true,
            'data' => $offres
        ]);
    }

    // Route pour récupérer les candidatures du candidat connecté
    public function mesCandidatures(Request $request)
    {
        $user = $request->user();
        
        // Vérifier que c'est un candidat
        if (!$user->hasRole('Candidat')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux candidats'
            ], 403);
        }

        // Récupérer le candidat_id depuis la table candidats
        $candidat = Candidat::where('user_id', $user->id)->first();
        
        if (!$candidat) {
            return response()->json([
                'success' => false,
                'message' => 'Profil candidat introuvable'
            ], 404);
        }

        $candidatures = Candidature::where('candidat_id', $candidat->id)
                                    ->with(['offre', 'offre.entreprise'])
                                    ->orderBy('created_at', 'desc')
                                    ->get();

        return response()->json([
            'success' => true,
            'data' => $candidatures
        ]);
    }
}
