<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Publicite;
use App\Models\User;
use App\Models\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PubliciteController extends Controller
{
    /* ======================= Helpers ======================= */

    /**
     * ✅ Helper pour résoudre l'entreprise_id (compatible avec l'ancien système)
     */
    private function resolveEntrepriseId(Request $request): ?int
    {
        if ($request->filled('entreprise_id')) return (int)$request->integer('entreprise_id');
        if ($request->filled('entreprise_pk')) return (int)$request->integer('entreprise_pk');

        $userId = $request->integer('entreprise_user_id') ?: $request->integer('user_id');
        if ($userId) return Entreprise::where('user_id', $userId)->value('id');

        return null;
    }

    private function isValidUnlockCode(string $code): bool
    {
        return strlen(trim($code)) >= 6;
    }

    /* ======================= CRUD ======================= */

    /**
     * ✅ GET /publicites (Admin voit tout, Recruteur/CM voient leurs entreprises)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = Publicite::with(['entreprise','validateur']);

        // ✅ Si Recruteur ou CM : filtrer par entreprises gérables
        if ($user && ($user->hasRole('recruteur') || $user->hasRole('community_manager'))) {
            $entreprises = $user->getManageableEntreprises();
            $entrepriseIds = $entreprises->pluck('id');
            
            if ($entrepriseIds->isNotEmpty()) {
                $q->whereIn('entreprise_id', $entrepriseIds);
            } else {
                // Aucune entreprise gérable : retourner vide
                return response()->json([
                    'success' => true,
                    'data' => ['data' => [], 'total' => 0]
                ]);
            }
        }

        // Filtres
        if ($request->filled('statut')) $q->where('statut', $request->query('statut'));
        if ($request->filled('type'))   $q->where('type',   $request->query('type'));
        if ($search = $request->query('q')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('titre','like',"%{$search}%")
                   ->orWhere('description','like',"%{$search}%")
                   ->orWhereHas('entreprise', fn($e) =>
                       $e->where('nom_entreprise','like',"%{$search}%")
                   );
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        return response()->json([
            'success' => true,
            'data'    => $q->orderByDesc('created_at')->paginate($perPage),
        ]);
    }

    /**
     * ✅ POST /publicites (Recruteur ET Community Manager)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier que c'est un recruteur OU community manager
        if (!$user->hasRole('recruteur') && !$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les recruteurs et community managers peuvent créer des publicités'
            ], 403);
        }
        
        $base = $request->validate([
            'titre'         => 'required|string|max:255',
            'description'   => 'nullable|string|max:10000',
            'lien_externe'  => 'nullable|url',
            'type'          => 'sometimes|in:banniere,sidebar,footer',
            'duree'         => 'required|in:3,7,14,30,60,90',
            'date_debut'    => 'required|date|after_or_equal:'.now()->toDateString(),
            'media_request' => 'required|in:image,video,both',
            'dual_unlock_code' => 'nullable|string|min:6',
            'entreprise_id'      => 'sometimes|integer|exists:entreprises,id',
            'entreprise_pk'      => 'sometimes|integer|exists:entreprises,id',
            'entreprise_user_id' => 'sometimes|integer|exists:entreprises,user_id',
            'user_id'            => 'sometimes|integer|exists:users,id',
        ]);

        $entrepriseId = $this->resolveEntrepriseId($request);
        
        // ✅ Si pas d'entreprise spécifiée, prendre la première gérable
        if (!$entrepriseId) {
            $entreprises = $user->getManageableEntreprises();
            $entreprise = $entreprises->first();
            
            if (!$entreprise) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune entreprise disponible pour créer des publicités'
                ], 422);
            }
            
            $entrepriseId = $entreprise->id;
        }
        
        // ✅ Vérifier les droits sur l'entreprise
        if (!$user->canManageEntreprise($entrepriseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }

        // Initialiser
        $image = null;
        $video = null;

        // IMAGE
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120']);
            $image = $request->file('image')->store('publicites/images','public');
        } elseif ($request->filled('image')) {
            $request->validate(['image' => 'string|max:1000']);
            $image = $request->input('image');
        }

        // VIDEO
        if ($request->hasFile('video')) {
            $request->validate(['video' => 'file|mimes:mp4,webm,ogg,ogv,mov,avi|max:51200']);
            $video = $request->file('video')->store('publicites/videos','public');
        } elseif ($request->filled('video')) {
            $request->validate(['video' => 'string|max:2000']);
            $video = $request->input('video');
        }

        // Contraintes selon media_request
        if ($base['media_request'] === 'image' && !$image) {
            return response()->json(['success'=>false,'message'=>'Image requise'], 422);
        }
        if ($base['media_request'] === 'video' && !$video) {
            return response()->json(['success'=>false,'message'=>'Vidéo requise'], 422);
        }
        if ($base['media_request'] === 'both' && (!$image || !$video)) {
            return response()->json(['success'=>false,'message'=>'Image et vidéo requises pour le mode both'], 422);
        }

        $calc = Publicite::calculerPrixEtDateFin($base['duree'], $base['date_debut']);

        $mediaEffective = $base['media_request'];
        $dualUnlockCode = null;
        $dualUnlockedAt = null;

        if ($base['media_request'] === 'both') {
            if ($request->filled('dual_unlock_code') && $this->isValidUnlockCode($request->input('dual_unlock_code'))) {
                $dualUnlockCode = $request->input('dual_unlock_code');
                $dualUnlockedAt = now();
                $mediaEffective = 'both';
            } else {
                $mediaEffective = $image ? 'image' : 'video';
            }
        }

        $pub = Publicite::create([
            'titre'            => $request->input('titre'),
            'description'      => $request->input('description'),
            'image'            => $image,
            'video'            => $video,
            'lien_externe'     => $request->input('lien_externe'),
            'type'             => $request->input('type','banniere'),
            'media_request'    => $base['media_request'],
            'media_effective'  => $mediaEffective,
            'dual_unlock_code' => $dualUnlockCode,
            'dual_unlocked_at' => $dualUnlockedAt,
            'payment_status'   => 'unpaid',
            'duree'            => $base['duree'],
            'prix'             => $calc['prix'],
            'date_debut'       => $base['date_debut'],
            'date_fin'         => $calc['date_fin'],
            'entreprise_id'    => $entrepriseId,
            'statut'           => 'brouillon',
        ]);

        return response()->json(['success'=>true,'message'=>'Publicité créée','data'=>$pub], 201);
    }

    // GET /publicites/{id}
    public function show($id)
    {
        $pub = Publicite::with(['entreprise','validateur'])->findOrFail($id);
        return response()->json(['success'=>true,'data'=>$pub]);
    }

    /**
     * ✅ PUT /publicites/{id}
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $pub = Publicite::findOrFail($id);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier les droits sur l'entreprise de la publicité
        if (!$user->canManageEntreprise($pub->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à modifier cette publicité'
            ], 403);
        }

        // Autoriser modification en: brouillon, en_attente, active (mais champs restreints si active)
        $modifiable = ['brouillon','en_attente','active'];
        if (!in_array($pub->statut, $modifiable)) {
            return response()->json(['success'=>false,'message'=>'Modifiable uniquement en brouillon, en attente ou active'],422);
        }

        $mutableAlways     = ['titre','description','lien_externe','image','video'];
        $mutableIfNotActive= ['type','duree','date_debut','media_request','payment_status',
                              'entreprise_id','entreprise_pk','entreprise_user_id','user_id','statut'];

        if ($pub->statut === 'active') {
            $request->replace($request->only($mutableAlways));
        }

        $base = $request->validate([
            'titre'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string|max:10000',
            'lien_externe'    => 'sometimes|nullable|url',
            'type'            => 'sometimes|in:banniere,sidebar,footer',
            'duree'           => 'sometimes|in:3,7,14,30,60,90',
            'date_debut'      => 'sometimes|date|after_or_equal:today',
            'media_request'   => 'sometimes|in:image,video,both',
            'statut'          => 'sometimes|in:brouillon,en_attente,active,expiree,rejetee',
            'payment_status'  => 'sometimes|in:unpaid,paid',
            'entreprise_id'      => 'sometimes|integer|exists:entreprises,id',
            'entreprise_pk'      => 'sometimes|integer|exists:entreprises,id',
            'entreprise_user_id' => 'sometimes|integer|exists:entreprises,user_id',
            'user_id'            => 'sometimes|integer|exists:users,id',
        ]);

        $data = $base;

        // IMAGE
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5120']);
            if ($pub->image && !preg_match('#^https?://#i', $pub->image)) {
                Storage::disk('public')->delete($pub->image);
            }
            $data['image'] = $request->file('image')->store('publicites/images','public');
        } elseif ($request->has('image')) {
            $val = $request->input('image');
            if ($val === '' || $val === null) {
                if ($pub->image && !preg_match('#^https?://#i', $pub->image)) {
                    Storage::disk('public')->delete($pub->image);
                }
                $data['image'] = null;
            } else {
                $data['image'] = $val;
            }
        }

        // VIDEO
        if ($request->hasFile('video')) {
            $request->validate(['video' => 'file|mimes:mp4,webm,ogg,ogv,mov,avi|max:51200']);
            if ($pub->video && !preg_match('#^https?://#i', $pub->video)) {
                Storage::disk('public')->delete($pub->video);
            }
            $data['video'] = $request->file('video')->store('publicites/videos','public');
        } elseif ($request->has('video')) {
            $val = $request->input('video');
            if ($val === '' || $val === null) {
                if ($pub->video && !preg_match('#^https?://#i', $pub->video)) {
                    Storage::disk('public')->delete($pub->video);
                }
                $data['video'] = null;
            } else {
                $data['video'] = $val;
            }
        }

        // Recalcul prix/date si pas active
        if ($pub->statut !== 'active' && (array_key_exists('duree',$data) || array_key_exists('date_debut',$data))) {
            $duree     = $data['duree']      ?? $pub->duree;
            $dateDebut = $data['date_debut'] ?? $pub->date_debut;
            $calc = Publicite::calculerPrixEtDateFin($duree, $dateDebut);
            $data['prix']       = $calc['prix'];
            $data['date_debut'] = $dateDebut;
            $data['date_fin']   = $calc['date_fin'];
        }

        // Changement d'entreprise si pas active
        if ($pub->statut !== 'active' &&
            ($request->filled('entreprise_id') || $request->filled('entreprise_pk') || $request->filled('entreprise_user_id') || $request->filled('user_id'))) {
            $newEntrepriseId = $this->resolveEntrepriseId($request);
            if (!$newEntrepriseId) return response()->json(['success'=>false,'message'=>"Entreprise cible invalide"],422);
            
            // ✅ Vérifier les droits sur la nouvelle entreprise
            if (!$user->canManageEntreprise($newEntrepriseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à l\'entreprise cible'
                ], 403);
            }
            
            $data['entreprise_id'] = $newEntrepriseId;
        }

        // Transitions de statut
        if (array_key_exists('statut',$data)) {
            $from = $pub->statut;
            $to   = $data['statut'];

            if (in_array($from, ['brouillon','en_attente']) && $to === 'en_attente') {
                // ok
            }
            elseif (in_array($from, ['brouillon','en_attente']) && $to === 'active') {
                $paid  = $data['payment_status'] ?? $pub->payment_status;
                $start = new Carbon($data['date_debut'] ?? $pub->date_debut);
                $end   = new Carbon($data['date_fin']   ?? $pub->date_fin);

                if ($paid !== 'paid') return response()->json(['success'=>false,'message'=>'Paiement requis (payment_status=paid)'],422);
                if (now()->lt($start) || now()->gt($end)) return response()->json(['success'=>false,'message'=>'Dates invalides pour activer'],422);

                $data['validee_par']     = Auth::id();
                $data['date_validation'] = now();
            }
            elseif ($from === 'active') {
                if ($to !== 'active') {
                    return response()->json(['success'=>false,'message'=>'Changer le statut depuis active est réservé aux endpoints dédiés (valider/rejeter/desactiver).'],422);
                }
            }
            else {
                return response()->json(['success'=>false,'message'=>'Transition de statut non autorisée'],422);
            }
        }

        $pub->update($data);
        return response()->json(['success'=>true,'message'=>'Publicité mise à jour','data'=>$pub->fresh()]);
    }

    /**
     * ✅ DELETE /publicites/{id}
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $pub = Publicite::findOrFail($id);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier les droits sur l'entreprise de la publicité
        if (!$user->canManageEntreprise($pub->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à supprimer cette publicité'
            ], 403);
        }
        
        if ($pub->image && !preg_match('#^https?://#i',$pub->image)) {
            Storage::disk('public')->delete($pub->image);
        }
        if ($pub->video && !preg_match('#^https?://#i',$pub->video)) {
            Storage::disk('public')->delete($pub->video);
        }
        
        $pub->delete();
        
        return response()->json(['success'=>true,'message'=>'Publicité supprimée']);
    }

    /* ======================= Actions statut ======================= */

    public function valider($id)
    {
        $pub = Publicite::findOrFail($id);
        if ($pub->statut !== 'en_attente') return response()->json(['success'=>false,'message'=>"Cette publicité n'est pas en attente"],422);

        $pub->update([
            'statut' => 'active',
            'validee_par' => Auth::id(),
            'date_validation' => now(),
            'motif_rejet' => null,
        ]);

        return response()->json(['success'=>true,'message'=>'Publicité validée','data'=>$pub->fresh('entreprise','validateur')]);
    }

    public function rejeter(Request $request, $id)
    {
        $request->validate(['motif_rejet'=>'required|string|max:500']);
        $pub = Publicite::findOrFail($id);
        if ($pub->statut !== 'en_attente') return response()->json(['success'=>false,'message'=>"Cette publicité n'est pas en attente"],422);

        $pub->update([
            'statut' => 'rejetee',
            'validee_par' => Auth::id(),
            'date_validation' => now(),
            'motif_rejet' => $request->motif_rejet,
        ]);

        return response()->json(['success'=>true,'message'=>'Publicité rejetée','data'=>$pub->fresh('entreprise','validateur')]);
    }

    public function desactiver($id)
    {
        $pub = Publicite::findOrFail($id);
        if ($pub->statut !== 'active') return response()->json(['success'=>false,'message'=>"Cette publicité n'est pas active"],422);

        $pub->update(['statut'=>'expiree']);
        return response()->json(['success'=>true,'message'=>'Publicité désactivée']);
    }

    /**
     * ✅ Soumettre une publicité pour validation
     */
    public function soumettre($id)
    {
        $user = Auth::user();
        $pub = Publicite::findOrFail($id);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier les droits sur l'entreprise de la publicité
        if (!$user->canManageEntreprise($pub->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à soumettre cette publicité'
            ], 403);
        }
        
        if ($pub->statut !== 'brouillon') {
            return response()->json([
                'success' => false,
                'message' => 'Cette publicité ne peut pas être soumise'
            ], 422);
        }
        
        $pub->update(['statut'=>'en_attente']);
        
        return response()->json(['success'=>true,'message'=>'Publicité soumise']);
    }

    /**
     * ✅ Activer une publicité
     */
    public function activer(Request $request, $id)
    {
        $user = Auth::user();
        $pub = Publicite::findOrFail($id);
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier les droits sur l'entreprise de la publicité
        if (!$user->canManageEntreprise($pub->entreprise_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à activer cette publicité'
            ], 403);
        }

        // On n'active que depuis brouillon/en_attente
        if (!in_array($pub->statut, ['brouillon', 'en_attente'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette publicité ne peut pas être activée depuis son statut actuel.'
            ], 422);
        }

        // On exige la durée, la date de début et la preuve de paiement "paid"
        $data = $request->validate([
            'duree'          => 'required|in:3,7,14,30,60,90',
            'date_debut'     => 'required|date|after_or_equal:today',
            'payment_status' => 'required|in:paid',
        ]);

        // Calcul fin/prix
        $calc = Publicite::calculerPrixEtDateFin($data['duree'], $data['date_debut']);

        // Validation business: dates
        $start = \Carbon\Carbon::parse($data['date_debut']);
        $end   = \Carbon\Carbon::parse($calc['date_fin']);
        if (now()->gt($end)) {
            return response()->json([
                'success' => false,
                'message' => "La période d'activation est déjà échue."
            ], 422);
        }

        // Mise à jour finale
        $pub->update([
            'duree'           => $data['duree'],
            'date_debut'      => $data['date_debut'],
            'date_fin'        => $calc['date_fin'],
            'prix'            => $calc['prix'],
            'payment_status'  => 'paid',
            'statut'          => 'active',
            'validee_par'     => Auth::id(),
            'date_validation' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Publicité activée',
            'data'    => $pub->fresh(['entreprise','validateur']),
        ]);
    }

    /* ======================= Lecture publique & stats ======================= */

    public function tarifs()
    {
        return response()->json(['success'=>true,'data'=>Publicite::$tarifs]);
    }

    public function publiques()
    {
        $rows = Publicite::with(['entreprise'])->active()->orderByDesc('created_at')->get();
        return response()->json(['success'=>true,'data'=>$rows]);
    }

    public function parType($type)
    {
        $rows = Publicite::with(['entreprise'])->byType($type)->active()->orderByDesc('created_at')->get();
        return response()->json(['success'=>true,'data'=>$rows]);
    }

    /**
     * ✅ Statistiques des publicités (Global pour Admin, Personnelles pour Recruteur/CM)
     */
    public function statistiques(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Si admin : statistiques globales
        if ($user->hasRole('administrateur')) {
            $stats = [
                'total'         => Publicite::count(),
                'brouillon'     => Publicite::where('statut','brouillon')->count(),
                'en_attente'    => Publicite::where('statut','en_attente')->count(),
                'active'        => Publicite::where('statut','active')->count(),
                'expiree'       => Publicite::where('statut','expiree')->count(),
                'rejetee'       => Publicite::where('statut','rejetee')->count(),
                'revenus_total' => Publicite::whereIn('statut',['active','expiree'])->sum('prix'),
                'revenus_mois'  => Publicite::whereIn('statut',['active','expiree'])
                    ->whereMonth('created_at', now()->month)
                    ->sum('prix'),
            ];
            
            return response()->json(['success'=>true,'data'=>$stats]);
        }
        
        // ✅ Si recruteur ou CM : statistiques personnelles
        if ($user->hasRole('recruteur') || $user->hasRole('community_manager')) {
            $entreprises = $user->getManageableEntreprises();
            $entrepriseIds = $entreprises->pluck('id');
            
            if ($entrepriseIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total' => 0,
                        'brouillon' => 0,
                        'en_attente' => 0,
                        'active' => 0,
                        'expiree' => 0,
                        'rejetee' => 0,
                        'cout_total' => 0,
                        'cout_mois' => 0,
                    ]
                ]);
            }
            
            $query = Publicite::whereIn('entreprise_id', $entrepriseIds);
            
            $stats = [
                'total'       => (clone $query)->count(),
                'brouillon'   => (clone $query)->where('statut','brouillon')->count(),
                'en_attente'  => (clone $query)->where('statut','en_attente')->count(),
                'active'      => (clone $query)->where('statut','active')->count(),
                'expiree'     => (clone $query)->where('statut','expiree')->count(),
                'rejetee'     => (clone $query)->where('statut','rejetee')->count(),
                'cout_total'  => (clone $query)->whereIn('statut',['active','expiree'])->sum('prix'),
                'cout_mois'   => (clone $query)->whereIn('statut',['active','expiree'])
                    ->whereMonth('created_at', now()->month)
                    ->sum('prix'),
            ];
            
            return response()->json(['success'=>true,'data'=>$stats]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé'
        ], 403);
    }

    /**
     * ✅ Mes publicités (Recruteur ET Community Manager)
     */
    public function getMesPublicites(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
        }
        
        // ✅ Vérifier que c'est un recruteur OU community manager
        if (!$user->hasRole('recruteur') && !$user->hasRole('community_manager')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux recruteurs et community managers'
            ], 403);
        }
        
        // ✅ Récupérer toutes les entreprises gérables
        $entreprises = $user->getManageableEntreprises();
        $entrepriseIds = $entreprises->pluck('id');
        
        if ($entrepriseIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'data' => [],
                    'total' => 0
                ],
                'message' => 'Aucune entreprise à gérer'
            ]);
        }
        
        // ✅ Filtrer par entreprise si spécifié (utile pour CM avec plusieurs entreprises)
        $query = Publicite::with(['entreprise', 'validateur'])
            ->whereIn('entreprise_id', $entrepriseIds);
        
        if ($request->filled('entreprise_id')) {
            $entrepriseId = $request->integer('entreprise_id');
            
            // Vérifier que l'utilisateur peut gérer cette entreprise
            if (!$user->canManageEntreprise($entrepriseId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette entreprise'
                ], 403);
            }
            
            $query->where('entreprise_id', $entrepriseId);
        }
        
        // Filtrer par statut si spécifié
        if ($request->filled('statut')) {
            $query->where('statut', $request->query('statut'));
        }
        
        $publicites = $query->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $publicites,
            'meta' => [
                // ✅ Liste des entreprises pour le filtre frontend
                'entreprises' => $entreprises
            ]
        ]);
    }

    /* ======================= Compteurs & dual ======================= */

    public function incrementerVue($id)
    {
        $pub = Publicite::active()->find($id);
        if (!$pub) return response()->json(['success'=>false,'message'=>'Publicité non trouvée ou inactive'],404);
        $pub->incrementVues();
        return response()->json(['success'=>true,'message'=>'Vue comptabilisée']);
    }

    public function incrementerClic($id)
    {
        $pub = Publicite::active()->find($id);
        if (!$pub) return response()->json(['success'=>false,'message'=>'Publicité non trouvée ou inactive'],404);
        $pub->incrementClics();
        return response()->json(['success'=>true,'message'=>'Clic comptabilisé','lien_externe'=>$pub->lien_externe]);
    }

    public function verifyDual(Request $request, $id)
    {
        $request->validate(['unlock_code'=>'required|string|min:6']);
        $pub = Publicite::findOrFail($id);

        if (!$this->isValidUnlockCode($request->unlock_code)) {
            return response()->json(['success'=>false,'message'=>'Code invalide'],422);
        }
        if (!$pub->image || !$pub->video) {
            return response()->json(['success'=>false,'message'=>'Image ou vidéo manquante'],422);
        }

        $pub->update([
            'media_effective'=>'both',
            'dual_unlock_code'=>$request->unlock_code,
            'dual_unlocked_at'=>now(),
        ]);

        return response()->json(['success'=>true,'message'=>'Double média activé','data'=>$pub->fresh()]);
    }
}