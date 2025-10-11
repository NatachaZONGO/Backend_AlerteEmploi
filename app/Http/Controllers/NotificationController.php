<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    // =================== GESTION CRUD DES NOTIFICATIONS ===================

    /**
     * Liste toutes les notifications (pour admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with(['auteur:id,nom,prenom'])
                            ->orderBy('created_at', 'desc');

        // Filtres optionnels
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('mode') && $request->mode) {
            $query->where('mode', $request->mode);
        }
        
        if ($request->has('statut') && $request->statut) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('destinataire') && $request->destinataire) {
            $query->where('destinataire', $request->destinataire);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('size', 10);
        $notifications = $query->paginate($perPage);

        return response()->json([
            'content' => $notifications->items(),
            'totalElements' => $notifications->total(),
            'totalPages' => $notifications->lastPage(),
            'size' => $notifications->perPage(),
            'number' => $notifications->currentPage() - 1,
            'success' => true
        ]);
    }

    /**
     * Affiche une notification spécifique
     */
    public function show(Notification $notification): JsonResponse
    {
        $notification->load(['auteur:id,nom,prenom', 'utilisateurCible:id,nom,prenom']);
        
        return response()->json($notification);
    }

    /**
     * Crée une nouvelle notification
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string',
            'destinataire' => 'required|string',
            'priorite' => 'required|in:urgente,normale,basse',
            'canaux' => 'required|array',
            'canaux.*' => 'in:app,email,sms,push',
            'mode' => 'in:manuel,automatique,programmee',
            'date_programmee' => 'nullable|date|after:now',
            'destinataire_id' => 'nullable|exists:users,id',
            'criteres_ciblage' => 'nullable|array'
        ]);

        // ✅ Pas d'authentification - auteur optionnel
        $validated['auteur_id'] = $request->get('auteur_id', null);
        
        // Statut par défaut
        $validated['statut'] = $request->get('statut', 'brouillon');
  
        // Calculer le nombre estimé de destinataires
        $validated['nombre_destinataires'] = $this->calculerNombreDestinataires($validated);

        $notification = Notification::create($validated);

        // Si c'est pour envoi immédiat
        if ($notification->statut === 'envoyee') {
            $this->envoyerNotification($notification);
        }

        return response()->json([
            'data' => $notification,
            'message' => 'Notification créée avec succès',
            'success' => true
        ], 201);
    }

    /**
     * Met à jour une notification
     */
    public function update(Request $request, Notification $notification): JsonResponse
    {
        // Vérifier si la notification peut être modifiée
        if (!$notification->peutEtreModifiee()) {
            return response()->json([
                'message' => 'Cette notification ne peut plus être modifiée',
                'success' => false
            ], 422);
        }

        $validated = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'type' => 'sometimes|string',
            'destinataire' => 'sometimes|string',
            'priorite' => 'sometimes|in:urgente,normale,basse',
            'canaux' => 'sometimes|array',
            'statut' => 'sometimes|in:brouillon,programmee,envoyee',
            'date_programmee' => 'nullable|date|after:now',
            'destinataire_id' => 'nullable|exists:users,id',
            'criteres_ciblage' => 'nullable|array'
        ]);

        // Recalculer le nombre de destinataires si nécessaire
        if (isset($validated['destinataire']) || isset($validated['criteres_ciblage'])) {
            $validated['nombre_destinataires'] = $this->calculerNombreDestinataires(
                array_merge($notification->toArray(), $validated)
            );
        }

        $notification->update($validated);

        return response()->json([
            'data' => $notification,
            'message' => 'Notification mise à jour avec succès',
            'success' => true
        ]);
    }

    /**
     * Supprime une notification
     */
    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();
        
        return response()->json([
            'message' => 'Notification supprimée avec succès',
            'success' => true
        ]);
    }

    // =================== ACTIONS SUR LES NOTIFICATIONS ===================

    /**
     * Envoie une notification immédiatement
     */
    public function envoyer(Notification $notification): JsonResponse
    {
        if (!$notification->peutEtreEnvoyee()) {
            return response()->json([
                'message' => 'Cette notification ne peut pas être envoyée',
                'success' => false
            ], 422);
        }

        try {
            $this->envoyerNotification($notification);
            
            return response()->json([
                'message' => 'Notification envoyée avec succès',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Marque une notification comme lue pour un utilisateur
     */
    public function marquerLue(Request $request, Notification $notification): JsonResponse
    {
        // ✅ Utilisateur passé en paramètre ou par défaut
        $userId = $request->get('user_id', 1);
        
        // Vérifier si l'utilisateur est destinataire de cette notification
        $pivot = $notification->destinataires()->where('user_id', $userId)->first();
        
        if (!$pivot) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas destinataire de cette notification',
                'success' => false
            ], 403);
        }

        // Marquer comme lue si pas déjà lue
        if ($pivot->pivot->statut === 'envoyee') {
            $notification->destinataires()->updateExistingPivot($userId, [
                'date_lecture' => now(),
                'statut' => 'lue'
            ]);
            
            // Incrémenter le compteur global
            $notification->incrementerLectures();
        }

        return response()->json([
            'message' => 'Notification marquée comme lue',
            'success' => true
        ]);
    }

    /**
     * Archive une notification pour un utilisateur
     */
    public function archiver(Request $request, Notification $notification): JsonResponse
    {
        // ✅ Utilisateur passé en paramètre ou par défaut
        $userId = $request->get('user_id', 1);
        
        $notification->destinataires()->updateExistingPivot($userId, [
            'statut' => 'archivee',
            'date_archivage' => now()
        ]);

        return response()->json([
            'message' => 'Notification archivée',
            'success' => true
        ]);
    }

    // =================== NOTIFICATIONS UTILISATEUR ===================

    /**
     * Récupère les notifications d'un utilisateur
     */
    public function mesNotifications(Request $request): JsonResponse
    {
        // ✅ Utilisateur passé en paramètre ou par défaut
        $userId = $request->get('user_id', 1);
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé',
                'success' => false
            ], 404);
        }
        
        $query = $user->notifications()
             ->orderBy('notifications.created_at', 'desc');


        // Filtrer par statut si demandé
        if ($request->has('statut') && $request->statut) {
            $query->wherePivot('statut', $request->statut);
        }

        $notifications = $query->paginate($request->get('size', 10));

        return response()->json([
            'content' => $notifications->items(),
            'totalElements' => $notifications->total(),
            'success' => true
        ]);
    }

    /**
     * Récupère le nombre de notifications non lues d'un utilisateur
     */
    public function nonLues(Request $request): JsonResponse
    {
        // ✅ Utilisateur passé en paramètre ou par défaut
        $userId = $request->get('user_id', 1);
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'count' => 0,
                'success' => true
            ]);
        }
        
        $count = $user->notifications()
                     ->wherePivot('statut', 'envoyee')
                     ->count();

        return response()->json([
            'count' => $count,
            'success' => true
        ]);
    }

    /**
     * Marque toutes les notifications comme lues pour un utilisateur
     */
    public function marquerToutesLues(Request $request): JsonResponse
    {
        // ✅ Utilisateur passé en paramètre ou par défaut
        $userId = $request->get('user_id', 1);
        
        DB::table('notification_users')
            ->where('user_id', $userId)
            ->where('statut', 'envoyee')
            ->update([
                'statut' => 'lue',
                'date_lecture' => now(),
                'updated_at' => now()
            ]);

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'success' => true
        ]);
    }

    // =================== NOTIFICATIONS AUTOMATIQUES ===================

    /**
     * Déclenche une notification automatique (pour tests ou intégrations)
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'evenement' => 'required|string',
            'donnees' => 'required|array',
            'timestamp' => 'nullable|string'
        ]);

        try {
            $notification = $this->declencherNotificationAutomatique(
                $validated['evenement'],
                $validated['donnees']
            );

            if ($notification) {
                return response()->json([
                    'data' => $notification,
                    'message' => 'Notification automatique déclenchée avec succès',
                    'success' => true
                ], 201);
            }

            return response()->json([
                'message' => 'Aucune notification déclenchée pour cet événement',
                'success' => true
            ], 204);

        } catch (\Exception $e) {
            Log::error('Erreur déclenchement notification auto: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erreur lors du déclenchement: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Active/désactive les notifications automatiques
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'actives' => 'required|boolean'
        ]);

        $actives = $validated['actives'];
        
        // Mettre à jour tous les rôles
        NotificationRole::query()->update(['actif' => $actives]);

        $message = $actives 
            ? 'Notifications automatiques activées' 
            : 'Notifications automatiques désactivées';

        return response()->json([
            'message' => $message,
            'success' => true
        ]);
    }

    /**
     * Récupère l'état des notifications automatiques
     */
    public function status(): JsonResponse
    {
        $actives = NotificationRole::where('actif', true)->exists();

        return response()->json([
            'actives' => $actives,
            'success' => true
        ]);
    }

    /**
     * Récupère les statistiques des notifications automatiques
     */
    public function stats(): JsonResponse
    {
        // Statistiques des dernières 24h
        $stats24h = Notification::automatiques()
                                ->recentes(24)
                                ->count();

        // Notifications automatiques envoyées avec succès
        $envoieesAuto = Notification::automatiques()
                                   ->envoyees()
                                   ->count();

        // Nombre de rôles actifs
        $rolesActifs = NotificationRole::actifs()->count();

        // Statistiques par déclencheur
        $parDeclencheur = Notification::automatiques()
                                     ->selectRaw('declencheur, COUNT(*) as count')
                                     ->groupBy('declencheur')
                                     ->pluck('count', 'declencheur')
                                     ->toArray();

        // Statistiques par type
        $parType = Notification::automatiques()
                              ->selectRaw('type, COUNT(*) as count')
                              ->groupBy('type')
                              ->pluck('count', 'type')
                              ->toArray();

        // Taux de réussite global
        $totalAuto = Notification::automatiques()->count();
        $reussies = Notification::automatiques()->envoyees()->count();
        $tauxReussite = $totalAuto > 0 ? round(($reussies / $totalAuto) * 100, 2) : 0;

        return response()->json([
            'totalAuto' => $stats24h,
            'envoieesAuto' => $envoieesAuto,
            'declencheurs' => $rolesActifs,
            'tempsReponse' => 150, // Valeur fixe pour l'exemple
            'parDeclencheur' => $parDeclencheur,
            'parType' => $parType,
            'tauxReussite' => $tauxReussite,
            'totalNotifications' => $totalAuto,
            'success' => true
        ]);
    }

    // =================== GESTION DES RÔLES AUTOMATIQUES ===================

    /**
     * Liste les rôles de notifications automatiques
     */
    public function roles(): JsonResponse
    {
        $roles = NotificationRole::with(['createur:id,nom,prenom', 'modificateur:id,nom,prenom'])
                                 ->orderBy('priorite_role', 'asc')
                                 ->orderBy('created_at', 'desc')
                                 ->get();

        return response()->json([
            'content' => $roles,
            'success' => true
        ]);
    }

    /**
     * Affiche un rôle spécifique
     */
    public function showRole(NotificationRole $role): JsonResponse
    {
        $role->load(['createur:id,nom,prenom', 'modificateur:id,nom,prenom']);
        
        return response()->json([
            'data' => $role,
            'success' => true
        ]);
    }

    /**
     * Crée un nouveau rôle de notification automatique
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'evenement_declencheur' => 'required|string',
            'conditions' => 'nullable|array',
            'template_notification' => 'required|array',
            'template_notification.titre_template' => 'required|string',
            'template_notification.message_template' => 'required|string',
            'template_notification.priorite_defaut' => 'required|in:urgente,normale,basse',
            'template_notification.canaux_defaut' => 'required|array',
            'destinataires_cibles' => 'required|array',
            'criteres_ciblage' => 'nullable|array',
            'delai_envoi_minutes' => 'nullable|integer|min:0',
            'limite_par_jour' => 'nullable|integer|min:1',
            'limite_par_utilisateur' => 'nullable|integer|min:1',
            'priorite_role' => 'nullable|integer|min:1',
            'actif' => 'boolean'
        ]);

        $role = NotificationRole::create($validated);

        return response()->json([
            'data' => $role,
            'message' => 'Rôle de notification créé avec succès',
            'success' => true
        ], 201);
    }

    /**
     * Met à jour un rôle de notification automatique
     */
    public function updateRole(Request $request, NotificationRole $role): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'evenement_declencheur' => 'sometimes|string',
            'conditions' => 'nullable|array',
            'template_notification' => 'sometimes|array',
            'destinataires_cibles' => 'sometimes|array',
            'criteres_ciblage' => 'nullable|array',
            'delai_envoi_minutes' => 'nullable|integer|min:0',
            'limite_par_jour' => 'nullable|integer|min:1',
            'limite_par_utilisateur' => 'nullable|integer|min:1',
            'priorite_role' => 'nullable|integer|min:1',
            'actif' => 'boolean'
        ]);

        $role->mettreAJour($validated);

        return response()->json([
            'data' => $role,
            'message' => 'Rôle mis à jour avec succès',
            'success' => true
        ]);
    }

    /**
     * Supprime un rôle de notification automatique
     */
    public function deleteRole(NotificationRole $role): JsonResponse
    {
        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé avec succès',
            'success' => true
        ]);
    }

    /**
     * Active/désactive un rôle spécifique
     */
    public function toggleRole(NotificationRole $role): JsonResponse
    {
        if ($role->estActif()) {
            $role->desactiver();
            $message = 'Rôle désactivé';
        } else {
            $role->activer();
            $message = 'Rôle activé';
        }

        return response()->json([
            'message' => $message,
            'success' => true
        ]);
    }

    /**
     * Teste un rôle spécifique avec des données factices
     */
    public function testRole(NotificationRole $role): JsonResponse
    {
        try {
            // Générer des données de test selon l'événement
            $donneesTest = $this->genererDonneesTest($role->evenement_declencheur);
            
            // Déclencher la notification
            $notification = $this->declencherNotificationDepuisRole($role, $donneesTest);

            return response()->json([
                'data' => $notification,
                'message' => 'Test du rôle réussi',
                'success' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du test: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    // =================== EXPORT ===================

    /**
     * Export des notifications en CSV
     */
    public function exportCSV(Request $request)
    {
        $query = Notification::with('auteur:id,nom,prenom');

        // Appliquer les mêmes filtres que l'index
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        if ($request->has('mode') && $request->mode) {
            $query->where('mode', $request->mode);
        }
        if ($request->has('statut') && $request->statut) {
            $query->where('statut', $request->statut);
        }

        $notifications = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="notifications_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($notifications) {
            $file = fopen('php://output', 'w');
            
            // En-têtes CSV
            fputcsv($file, [
                'ID', 'Titre', 'Type', 'Mode', 'Destinataire', 'Priorité', 
                'Statut', 'Auteur', 'Date Création', 'Date Envoi'
            ]);

            // Données
            foreach ($notifications as $notification) {
                fputcsv($file, [
                    $notification->id,
                    $notification->titre,
                    $notification->type,
                    $notification->mode,
                    $notification->destinataire,
                    $notification->priorite,
                    $notification->statut,
                    $notification->auteur ? $notification->auteur->nom : 'Système',
                    $notification->created_at->format('d/m/Y H:i'),
                    $notification->date_envoi ? $notification->date_envoi->format('d/m/Y H:i') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // =================== MÉTHODES PRIVÉES ===================

    /**
     * Envoie effectivement une notification
     */
    private function envoyerNotification(Notification $notification): void
    {
        try {
            // 1. Obtenir les destinataires
            $destinataires = $this->obtenirDestinataires($notification);
            
            // 2. Mettre à jour la notification
            $notification->update([
                'statut' => 'envoyee',
                'date_envoi' => now(),
                'nombre_destinataires' => $destinataires->count()
            ]);

            // 3. Créer les relations avec les destinataires
            foreach ($destinataires as $destinataire) {
                $notification->destinataires()->attach($destinataire->id, [
                    'date_envoi' => now(),
                    'statut' => 'envoyee',
                    'canal_utilise' => implode(',', $notification->canaux)
                ]);
            }

            // 4. Ici vous pourriez ajouter l'envoi par email, SMS, push, etc.
            // Exemple: dispatch(new EnvoiEmailJob($notification, $destinataires));

        } catch (\Exception $e) {
            $notification->marquerEchouee($e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtient les destinataires selon le type
     */
    private function obtenirDestinataires(Notification $notification)
    {
        switch ($notification->destinataire) {
            case 'tous':
                return User::all();
                
            case 'candidats':
                return User::whereHas('roles', function($query) {
                    $query->where('nom', 'candidat');
                })->get();
                
            case 'recruteurs':
                return User::whereHas('roles', function($query) {
                    $query->where('nom', 'recruteur');
                })->get();
                
            case 'admins':
                return User::whereHas('roles', function($query) {
                    $query->where('nom', 'admin');
                })->get();
                
            case 'utilisateur_specifique':
                if ($notification->destinataire_id) {
                    return User::where('id', $notification->destinataire_id)->get();
                }
                return collect();
                
            case 'candidats_categorie':
                // Logique pour candidats par catégorie
                $categories = $notification->criteres_ciblage['categories'] ?? [];
                if (!empty($categories)) {
                    return User::whereHas('candidat', function($query) use ($categories) {
                        $query->whereIn('categorie_id', $categories);
                    })->get();
                }
                return collect();
                
            default:
                return collect();
        }
    }

    /**
     * Calcule le nombre estimé de destinataires
     */
    private function calculerNombreDestinataires(array $notificationData): int
    {
        $notification = new Notification($notificationData);
        return $this->obtenirDestinataires($notification)->count();
    }

    /**
     * Déclenche une notification automatique
     */
    private function declencherNotificationAutomatique(string $evenement, array $donnees): ?Notification
    {
        // Rechercher les rôles actifs pour cet événement
        $roles = NotificationRole::actifs()
                                 ->pourEvenement($evenement)
                                 ->parPriorite()
                                 ->get();

        if ($roles->isEmpty()) {
            Log::info("Aucun rôle trouvé pour l'événement: {$evenement}");
            return null;
        }

        $notification = null;

        foreach ($roles as $role) {
            // Vérifier si le rôle peut être déclenché
            if (!$role->peutEtreDeclenche()) {
                continue;
            }

            // Vérifier si les conditions sont remplies
            if (!$role->conditionsSontRemplies($donnees)) {
                continue;
            }

            // Créer et envoyer la notification
            $notification = $this->declencherNotificationDepuisRole($role, $donnees);
            
            // Marquer le rôle comme exécuté
            $role->marquerExecute();
            
            break; // On ne déclenche qu'un seul rôle par événement
        }

        return $notification;
    }

    /**
     * Crée une notification depuis un rôle
     */
    private function declencherNotificationDepuisRole(NotificationRole $role, array $donnees): Notification
    {
        // Générer les données de la notification
        $notificationData = $role->genererNotification($donnees);
        
        // Créer la notification
        $notification = Notification::create($notificationData);
        
        // Envoyer immédiatement si pas de délai
        if ($role->delai_envoi_minutes === 0) {
            $this->envoyerNotification($notification);
        }
        
        return $notification;
    }

    /**
     * Génère des données de test pour un événement
     */
    private function genererDonneesTest(string $evenement): array
    {
        switch ($evenement) {
            case 'user_registration':
                return [
                    'id' => 999,
                    'utilisateur' => [
                        'id' => 999,
                        'nom' => 'Test',
                        'prenom' => 'Utilisateur',
                        'email' => 'test.utilisateur@example.com'
                    ]
                ];

            case 'recruiter_registration':
                return [
                    'id' => 999,
                    'utilisateur' => [
                        'id' => 999,
                        'nom' => 'Test',
                        'prenom' => 'Recruteur',
                        'email' => 'test.recruteur@example.com'
                    ],
                    'entreprise' => [
                        'nom' => 'Entreprise Test',
                        'secteur' => 'Informatique'
                    ]
                ];

            case 'job_posted':
                return [
                    'id' => 999,
                    'offre' => [
                        'id' => 999,
                        'titre' => 'Développeur Full Stack - Test',
                        'entreprise' => 'TechCorp Test',
                        'localisation' => 'Paris',
                        'salaire' => '45000-55000€'
                    ],
                    'categories' => ['informatique', 'web'],
                    'recruteur_id' => 1
                ];

            case 'application_submitted':
                return [
                    'id' => 999,
                    'candidat' => [
                        'id' => 999,
                        'nom' => 'Test',
                        'prenom' => 'Candidat',
                        'email' => 'test.candidat@example.com'
                    ],
                    'offre' => [
                        'id' => 999,
                        'titre' => 'Designer UX/UI - Test',
                        'entreprise' => 'CreativeStudio Test'
                    ],
                    'recruteur_id' => 1
                ];

            case 'suspicious_login':
                return [
                    'id' => 999,
                    'utilisateur_id' => 1,
                    'ip_address' => '192.168.1.100',
                    'localisation' => 'Localisation inconnue',
                    'navigateur' => 'Chrome/Test',
                    'niveau_risque' => 'ELEVE'
                ];

            default:
                return [
                    'id' => 999,
                    'message' => 'Test de notification automatique'
                ];
        }
    }

    public function marquerLueNew(Request $request, Notification $notification): JsonResponse
    {
        $userId = $request->get('user_id', 1);
        $unread = (bool) $request->get('unread', false);

        // Vérifie que l'utilisateur fait partie des destinataires
        $pivot = $notification->destinataires()->where('user_id', $userId)->first();
        if (!$pivot) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est pas destinataire de cette notification',
                'success' => false
            ], 403);
        }

        if ($unread) {
            // Revenir à "envoyee"
            $notification->destinataires()->updateExistingPivot($userId, [
                'statut' => 'envoyee',
                'date_lecture' => null,
                'updated_at' => now(),
            ]);
            // décrémente le compteur global si > 0
            $notification->update([
                'nombre_lues' => max(0, ($notification->nombre_lues ?? 0) - 1),
            ]);

            return response()->json([
                'message' => 'Notification marquée comme non lue',
                'success' => true
            ]);
        }

        // Marquer lue (comportement existant)
        if ($pivot->pivot->statut === 'envoyee') {
            $notification->destinataires()->updateExistingPivot($userId, [
                'date_lecture' => now(),
                'statut' => 'lue'
            ]);
            $notification->incrementerLectures();
        }

        return response()->json([
            'message' => 'Notification marquée comme lue',
            'success' => true
        ]);
    }


    // Liste messages d’une conversation
public function messages(Notification $notification, Request $req) {
    $per = (int)($req->get('size', 30));
    $items = $notification->messages()->with('sender:id,nom,prenom')
                ->orderBy('created_at','asc')->paginate($per);

    return response()->json(['content'=>$items->items(),'totalElements'=>$items->total(),'success'=>true]);
}

// Envoyer un message dans la conversation
    public function sendMessage(Request $req, Notification $notification) {
        $data = $req->validate([
            'sender_id'=>'nullable|exists:users,id',
            'type'=>'in:text,image,file,system',
            'content'=>'nullable|string',
            'meta'=>'nullable|array',
            'replied_to_id'=>'nullable|exists:notification_messages,id'
        ]);

        $msg = $notification->messages()->create($data);

        // marquer non-lu côté destinataires (simple: remettre pivot statut=envoyee)
        DB::table('notification_users')
        ->where('notification_id',$notification->id)
        ->where('user_id','!=', $data['sender_id'] ?? 0)
        ->update(['statut'=>'envoyee','updated_at'=>now()]);

        return response()->json(['data'=>$msg,'success'=>true],201);
    }

    // Marquer la conversation lue (pour 1 user)
    public function readConversation(Request $req, Notification $notification) {
        $userId = (int)($req->get('user_id', 1));
        $notification->destinataires()->updateExistingPivot($userId, [
            'statut'=>'lue','date_lecture'=>now()
        ]);
        return response()->json(['success'=>true]);
    }

    // (optionnel) Marquer NON lue
    public function unreadConversation(Request $req, Notification $notification) {
        $userId = (int)($req->get('user_id', 1));
        $notification->destinataires()->updateExistingPivot($userId, [
            'statut'=>'envoyee','date_lecture'=>null
        ]);
        return response()->json(['success'=>true]);
    }

}