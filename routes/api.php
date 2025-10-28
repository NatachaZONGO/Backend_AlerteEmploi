<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\Dashboard\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Dashboard\Admin\AdminUserController;
use App\Http\Controllers\Api\Dashboard\Admin\AdminPaysController;
use App\Http\Controllers\Api\Dashboard\Admin\AdminCategorieController;
use App\Http\Controllers\Api\Dashboard\Admin\AdminRoleController;
use App\Http\Controllers\Api\Dashboard\Candidat\CandidatDashboardController;
use App\Http\Controllers\Api\Dashboard\Recruteur\RecruteurDashboardController;
use App\Http\Controllers\OffreController;
use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\ConseilController;
use App\Http\Controllers\PubliciteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\MonEntrepriseController;
use App\Http\Controllers\CommunityManagerController; 

// ===================== AUTH =====================
Route::prefix('auth')->group(function () {
    Route::post('/register-candidat',  [AuthController::class, 'registerCandidat']);
    Route::post('/register-recruteur', [AuthController::class, 'registerRecruteur']);
    Route::post('/login',              [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',        [AuthController::class, 'me']);
        Route::get('/dashboard', [AuthController::class, 'dashboard']);
    });

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);
});

// ===================== PROFILE =====================
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/',            [UserProfileController::class, 'show']);
    Route::put('/details',     [UserProfileController::class, 'updateBasicInfo']);
    Route::put('/password',    [UserProfileController::class, 'changePassword']);
    Route::put('/candidat',    [UserProfileController::class, 'updateCandidatProfile']);
    Route::post('/photo', [UserProfileController::class, 'uploadPhoto']);
    Route::put('/entreprise',  [UserProfileController::class, 'updateEntrepriseProfile']);
});

// ===================== USERS =====================
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    Route::get('/',         [AdminUserController::class, 'index']);
    Route::post('/',        [AdminUserController::class, 'store']);
    Route::get('/{id}',     [AdminUserController::class, 'show'])->whereNumber('id');
    Route::put('/{id}',     [AdminUserController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}',  [AdminUserController::class, 'destroy'])->whereNumber('id');
    Route::put ('/{id}/status',        [AdminUserController::class, 'changeStatus'])->whereNumber('id');
    Route::post('/{id}/reset-password',[AdminUserController::class, 'resetPassword'])->whereNumber('id');

    Route::post('/{id}/password', [AdminUserController::class, 'resetPassword'])->whereNumber('id');
    Route::patch('/{id}/toggle',  [AdminUserController::class, 'toggleActive'])->whereNumber('id');

    // Gestion des rôles utilisateur via RoleController
    Route::post('/{userId}/roles/attach', [RoleController::class, 'attachUserRoles'])->whereNumber('userId');
    Route::post('/{userId}/roles/detach', [RoleController::class, 'detachUserRoles'])->whereNumber('userId');
    Route::post('/{userId}/roles/sync',   [RoleController::class, 'syncUserRoles'])->whereNumber('userId');
});

// ===================== ROLES (CRUD) =====================
Route::prefix('roles')->group(function () {
    Route::get('/',        [RoleController::class, 'index']);
    Route::post('/',       [RoleController::class, 'store']);
    Route::get('/{id}',    [RoleController::class, 'show'])->whereNumber('id');
    Route::put('/{id}',    [RoleController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [RoleController::class, 'destroy'])->whereNumber('id');
});

// ============================================================================
// ENTREPRISES (CRUD de base + Validations - Protégé)
// ============================================================================
Route::middleware('auth:sanctum')->prefix('entreprises')->group(function () {
    // CRUD standard
    Route::get('/',         [AdminDashboardController::class, 'entreprisesIndex']);
    Route::post('/',        [AdminDashboardController::class, 'entreprisesStore']);
    Route::get('/{id}',     [AdminDashboardController::class, 'entreprisesShow'])->whereNumber('id');
    Route::put('/{id}',     [AdminDashboardController::class, 'entreprisesUpdate'])->whereNumber('id');
    Route::delete('/{id}',  [AdminDashboardController::class, 'entreprisesDestroy'])->whereNumber('id');

    // Workflow de validation (Admin)
    Route::get('/pending',              [AdminDashboardController::class, 'getPendingEntreprises']);
    Route::put('/{id}/validate',        [AdminDashboardController::class, 'validateEntrepriseByCompanyId'])->whereNumber('id');
    Route::put('/{id}/reject',          [AdminDashboardController::class, 'rejectEntrepriseByCompanyId'])->whereNumber('id');
    Route::put('/{id}/revalidate',      [AdminDashboardController::class, 'revalidateEntrepriseByCompanyId'])->whereNumber('id');

    // ✅ Statistiques d'une entreprise spécifique (Admin + CM assigné)
    Route::get('/{id}/stats',           [AdminDashboardController::class, 'entrepriseStats'])->whereNumber('id');
    Route::get('/mes-entreprises', [AdminDashboardController::class, 'mesEntreprisesGerees']);
});

// ============================================================================
// ✅ COMMUNITY MANAGER - Gestion par l'Admin
// ============================================================================
Route::middleware('auth:sanctum')->prefix('admin/community-managers')->group(function () {
    // Liste tous les Community Managers
    Route::get('/', [CommunityManagerController::class, 'index']);
    
    // Assigner un CM à une entreprise
    Route::post('/assign', [CommunityManagerController::class, 'assignToEntreprise']);
    
    // Retirer un CM d'une entreprise
    Route::post('/remove', [CommunityManagerController::class, 'removeFromEntreprise']);
    
    // Liste des entreprises assignées à un CM spécifique
    Route::get('/{userId}/entreprises', [CommunityManagerController::class, 'getCommunityManagerEntreprises'])
        ->whereNumber('userId');
    
    // Liste des CM assignés à une entreprise donnée
    Route::get('/entreprises/{entrepriseId}/community-managers', [CommunityManagerController::class, 'getEntrepriseCommunityManagers'])
        ->whereNumber('entrepriseId');
});

// ============================================================================
// ✅ COMMUNITY MANAGER - Espace personnel du CM connecté
// ============================================================================
Route::middleware('auth:sanctum')->prefix('community')->group(function () {
    // Mes entreprises assignées (CM uniquement)
    Route::get('/entreprises', [CommunityManagerController::class, 'getEntreprises']);
    
    // Dashboard du CM
    Route::get('/dashboard', [CommunityManagerController::class, 'dashboard']);
    
    // Statistiques globales du CM (toutes ses entreprises)
    Route::get('/stats', [CommunityManagerController::class, 'getStatistiques']);
});

// ============================================================================
// ✅ MON ENTREPRISE (Recruteur connecté ET CM avec entreprise sélectionnée)
// ============================================================================
Route::middleware('auth:sanctum')->prefix('mon-entreprise')->group(function () {
    // Récupérer les infos de mon entreprise
    Route::get('/', [MonEntrepriseController::class, 'show']);
    
    // Mettre à jour mon entreprise
    Route::put('/', [MonEntrepriseController::class, 'update']);
    
    // Upload du logo
    Route::post('/logo', [MonEntrepriseController::class, 'uploadLogo']);
    
    // Statistiques de mon entreprise
    Route::get('/stats', [MonEntrepriseController::class, 'getStats']);
});

// ============================================================================
// CONSEILS
// ============================================================================
Route::prefix('conseils')->group(function () {
    Route::get('/', [ConseilController::class, 'index']); // liste (tous)
    Route::get('/recent', [ConseilController::class, 'recent']); // récents sur 3 mois
    Route::get('/{id}', [ConseilController::class, 'show']); // détail
    
    // ✅ Protégé (Admin ET Community Manager peuvent créer/modifier)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/', [ConseilController::class, 'store']); // créer
        Route::put('/{id}', [ConseilController::class, 'update']); // maj
        Route::delete('/{id}', [ConseilController::class, 'destroy']); // supprimer
    });
});

// ============================================================================
// ✅ OFFRES (Recruteur ET Community Manager)
// ============================================================================
Route::prefix('offres')->controller(OffreController::class)->group(function () {
    // Public
    Route::get('/',            'index');
    Route::get('/featured',    'featured');
    Route::get('/statistiques','statistiques');
    Route::get('/{id}',        'show')->whereNumber('id');

    // ✅ Protégé (Recruteur ET Community Manager)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/dashboard',     'dashboard');
        Route::get('/mes-offres',    'mesOffres');

        Route::post('/',             'store');
        Route::put ('/{id}',         'update')->whereNumber('id');
        Route::delete('/{id}',       'destroy')->whereNumber('id');

        // Workflow
        Route::patch('/{id}/soumettre-validation', 'soumettreValidation')->whereNumber('id');
        Route::patch('/{id}/valider',              'valider')->whereNumber('id');
        Route::patch('/{id}/rejeter',              'rejeter')->whereNumber('id');
        Route::post ('/{id}/publier',              'publier')->whereNumber('id');
        Route::patch('/{id}/fermer',               'fermer')->whereNumber('id');

        // Mise en avant
        Route::post ('/{id}/feature',   'markAsFeatured')->whereNumber('id');
        Route::post ('/{id}/unfeature', 'unfeature')->whereNumber('id');
    });
});

// ============================================================================
// CANDIDATURES (mixte public + protégé)
// ============================================================================
Route::prefix('candidatures')->group(function () {
    // --- Public / invité
    Route::post('/guest',         [CandidatureController::class, 'storeGuest']);
    Route::get ('/suivi/{code}',  [CandidatureController::class, 'findByCode']);
    Route::post('/renvoyer-email',[CandidatureController::class, 'resendEmail']);
    Route::get ('/{id}/download/cv', [CandidatureController::class, 'downloadCvById'])->whereNumber('id');
    Route::get ('/{id}/download/lm', [CandidatureController::class, 'downloadLmById'])->whereNumber('id');

    // --- Protégé par token
    Route::middleware('auth:sanctum')->group(function () {
        // Créer une candidature (depuis compte connecté)
        Route::post('/', [CandidatureController::class, 'store']);

        // Mes candidatures (candidat)
        Route::get('/mes-candidatures', [CandidatureController::class, 'mesCandidatures']);
        Route::get('/mes-candidatures/{candidat}', [CandidatureController::class, 'mesCandidatures'])
            ->whereNumber('candidat');

        // ✅ Recruteur / Community Manager (candidatures reçues)
        Route::get('/recues',                         [CandidatureController::class, 'candidaturesRecues']);
        Route::put('/{candidature}/statut',           [CandidatureController::class, 'updateStatut'])->whereNumber('candidature');
        Route::get('/offres/{offreId}/candidatures',  [CandidatureController::class, 'getByOffre'])->whereNumber('offreId');

        // Admin (liste + stats)
        Route::get('/',            [CandidatureController::class, 'index']);
        Route::get('/statistiques',[CandidatureController::class, 'statistiques']);

        // Suppressions
        Route::delete('/{id}', [CandidatureController::class, 'destroy'])->whereNumber('id');
        Route::delete('/',      [CandidatureController::class, 'destroyMultiple']);
    });
});

// ============================================================================
// ✅ PUBLICITÉS (Recruteur ET Community Manager)
// ============================================================================
Route::prefix('publicites')->group(function () {
    // Public
    Route::get('/actives',      [PubliciteController::class, 'publiques']);
    Route::get('/type/{type}',  [PubliciteController::class, 'parType'])
        ->whereIn('type', ['banniere','sidebar','footer']);
    Route::post('/{id}/vue',    [PubliciteController::class, 'incrementerVue'])->whereNumber('id');
    Route::post('/{id}/clic',   [PubliciteController::class, 'incrementerClic'])->whereNumber('id');

    // ✅ Protégé (Recruteur ET Community Manager)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/mes-publicites', [PubliciteController::class, 'getMesPublicites']);
        
        // CRUD
        Route::get   ('/',      [PubliciteController::class, 'index']);
        Route::post  ('/',      [PubliciteController::class, 'store']);
        Route::get   ('/{id}',  [PubliciteController::class, 'show'])->whereNumber('id');
        Route::put   ('/{id}',  [PubliciteController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}',  [PubliciteController::class, 'destroy'])->whereNumber('id');

        // Activation & statuts
        Route::put ('/{id}/activer',   [PubliciteController::class, 'activer'])->whereNumber('id');
        Route::put ('/{id}/valider',   [PubliciteController::class, 'valider'])->whereNumber('id');
        Route::put ('/{id}/rejeter',   [PubliciteController::class, 'rejeter'])->whereNumber('id');
        Route::put ('/{id}/desactiver',[PubliciteController::class, 'desactiver'])->whereNumber('id');
        Route::post('/{id}/soumettre', [PubliciteController::class, 'soumettre'])->whereNumber('id');

        // Utilitaires
        Route::get ('/statistiques', [PubliciteController::class, 'statistiques']);
        Route::get ('/tarifs',       [PubliciteController::class, 'tarifs']);
        Route::post('/{id}/verify-dual', [PubliciteController::class, 'verifyDual'])->whereNumber('id');
    });
});

// ============================================================================
// NOTIFICATIONS (protégé – lié à l'utilisateur)
// ============================================================================
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get ('mes',            [NotificationController::class, 'mesNotifications']);
    Route::get ('mes/non-lues',   [NotificationController::class, 'nonLues']);
    Route::patch('{notification}/read', [NotificationController::class, 'marquerLueNew'])->whereNumber('notification');
    Route::patch('mes/read-all',  [NotificationController::class, 'marquerToutesLues']);
});

// ============================================================================
// CATÉGORIES & PAYS (protégé)
// ============================================================================
Route::prefix('pays')->group(function () {
    Route::get   ('/',     [AdminPaysController::class, 'index']);
    Route::post  ('/',     [AdminPaysController::class, 'store']);
    Route::get   ('/{id}', [AdminPaysController::class, 'show'])->whereNumber('id');
    Route::put   ('/{id}', [AdminPaysController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [AdminPaysController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('categories')->group(function () {
    Route::get   ('/',     [AdminCategorieController::class, 'index']);
    Route::post  ('/',     [AdminCategorieController::class, 'store']);
    Route::get   ('/{id}', [AdminCategorieController::class, 'show'])->whereNumber('id');
    Route::put   ('/{id}', [AdminCategorieController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [AdminCategorieController::class, 'destroy'])->whereNumber('id');
});
// ============================================================================
// DASHBOARD GLOBALES (protégé)
// ============================================================================
Route::middleware('auth:sanctum')->get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);
