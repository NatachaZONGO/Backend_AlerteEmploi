<?php

use Illuminate\Support\Facades\Route;

// ==== Controllers ============================================================
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

// ============================================================================
// AUTH (public + protégé)
// ============================================================================
Route::post('/register-candidat',  [AuthController::class, 'registerCandidat']);
Route::post('/register-recruteur', [AuthController::class, 'registerRecruteur']);

Route::post('/login',  [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',        [AuthController::class, 'me']);        // infos utilisateur + rôles
    Route::get('/dashboard', [AuthController::class, 'dashboard']); // flags selon rôle
});

// ============================================================================
// PROFIL UTILISATEUR (protégé)
// ============================================================================
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/',            [UserProfileController::class, 'show']);             // Afficher le profil
    Route::put('/details',     [UserProfileController::class, 'updateBasicInfo']);  // Mettre à jour les informations de base
    Route::put('/password',    [UserProfileController::class, 'changePassword']);   // Changer mot de passe
    Route::put('/candidat',    [UserProfileController::class, 'updateCandidatProfile']);
    Route::put('/entreprise',  [UserProfileController::class, 'updateEntrepriseProfile']);
});

// ============================================================================
// USERS / RÔLES (protégé) – Admin tooling
// ============================================================================
Route::middleware('auth:sanctum')->prefix('users')->group(function () {
    // AdminUserController
    Route::get('/',         [AdminUserController::class, 'index']);
    Route::post('/',        [AdminUserController::class, 'store']);
    Route::get('/{id}',     [AdminUserController::class, 'show'])->whereNumber('id');
    Route::put('/{id}',     [AdminUserController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}',  [AdminUserController::class, 'destroy'])->whereNumber('id');

    // Outils
    Route::post('/{id}/roles',    [AdminUserController::class, 'syncRoles'])->whereNumber('id');
    Route::post('/{id}/password', [AdminUserController::class, 'resetPassword'])->whereNumber('id');
    Route::patch('/{id}/toggle',  [AdminUserController::class, 'toggleActive'])->whereNumber('id');

    // Gestion des rôles (RoleController "simple")
    Route::post('/{userId}/roles/sync',   [RoleController::class, 'syncUserRoles'])->whereNumber('userId');
    Route::post('/{userId}/roles/attach', [RoleController::class, 'attachUserRoles'])->whereNumber('userId');
    Route::post('/{userId}/roles/detach', [RoleController::class, 'detachUserRoles'])->whereNumber('userId');
});

// Ressource rôles (admin étendu)
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('/roles', AdminRoleController::class);
    Route::post('/roles/assign',                 [AdminRoleController::class, 'assignRole']);
    Route::post('/roles/remove',                 [AdminRoleController::class, 'removeRole']);
    Route::put('/users/{userId}/role',           [AdminRoleController::class, 'changeUserRole'])->whereNumber('userId');
});

// ============================================================================
// ENTREPRISES (protégé)
// ============================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get   ('/entreprises',        [AdminDashboardController::class, 'entreprisesIndex']);
    Route::post  ('/entreprises',        [AdminDashboardController::class, 'entreprisesStore']);
    Route::get   ('/entreprises/{id}',   [AdminDashboardController::class, 'entreprisesShow'])->whereNumber('id');
    Route::put   ('/entreprises/{id}',   [AdminDashboardController::class, 'entreprisesUpdate'])->whereNumber('id');
    Route::delete('/entreprises/{id}',   [AdminDashboardController::class, 'entreprisesDestroy'])->whereNumber('id');

    Route::get ('/entreprises/pending',                [AdminDashboardController::class, 'getPendingEntreprises']);
    Route::put ('/entreprises/{id}/validate',          [AdminDashboardController::class, 'validateEntrepriseByCompanyId'])->whereNumber('id');
    Route::put ('/entreprises/{id}/reject',            [AdminDashboardController::class, 'rejectEntrepriseByCompanyId'])->whereNumber('id');
    Route::put ('/entreprises/{id}/revalidate',        [AdminDashboardController::class, 'revalidateEntrepriseByCompanyId'])->whereNumber('id');
});

// ============================================================================
// CONSEILS
// ============================================================================
Route::prefix('conseils')->group(function () { Route::get('/', [ConseilController::class, 'index']); 
    Route::get('/', [ConseilController::class, 'index']); // liste (tous)
    Route::get('/recent', [ConseilController::class, 'recent']); // récents sur 3 mois
    Route::get('/{id}', [ConseilController::class, 'show']); // détail
    Route::post('/', [ConseilController::class, 'store']); // créer
    Route::put('/{id}', [ConseilController::class, 'update']); // maj
    Route::delete('/{id}', [ConseilController::class, 'destroy']); // supprimer
});

// ============================================================================
// OFFRES (mixte public + protégé) – controller group
// ============================================================================
Route::prefix('offres')->controller(OffreController::class)->group(function () {
    // Public
    Route::get('/',            'index');        // ?sponsored=..., ?sponsored_level=...
    Route::get('/featured',    'featured');     // vedettes actives
    Route::get('/statistiques','statistiques');
    Route::get('/{id}',        'show')->whereNumber('id');

    // Protégé
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

        // Mes candidatures (dérive candidat via $request->user())
        Route::get('/mes-candidatures', [CandidatureController::class, 'mesCandidatures']);
        // (facultatif) variante path param : /mes-candidatures/8
        Route::get('/mes-candidatures/{candidat}', [CandidatureController::class, 'mesCandidatures'])
            ->whereNumber('candidat');

        // Recruteur / Admin (à adapter si tu ajoutes un middleware rôle)
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
// PUBLICITÉS (mixte public + protégé) – DÉDOUBLONNÉ
// ============================================================================
Route::prefix('publicites')->group(function () {
    // Public
    Route::get('/actives',      [PubliciteController::class, 'publiques']);
    Route::get('/type/{type}',  [PubliciteController::class, 'parType'])
        ->whereIn('type', ['banniere','sidebar','footer']);
    Route::post('/{id}/vue',    [PubliciteController::class, 'incrementerVue'])->whereNumber('id');
    Route::post('/{id}/clic',   [PubliciteController::class, 'incrementerClic'])->whereNumber('id');

    // Protégé
    Route::middleware('auth:sanctum')->group(function () {
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
// NOTIFICATIONS (protégé – lié à l’utilisateur)
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
Route::middleware('auth:sanctum')->prefix('pays')->group(function () {
    Route::get   ('/',     [AdminPaysController::class, 'index']);
    Route::post  ('/',     [AdminPaysController::class, 'store']);
    Route::get   ('/{id}', [AdminPaysController::class, 'show'])->whereNumber('id');
    Route::put   ('/{id}', [AdminPaysController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [AdminPaysController::class, 'destroy'])->whereNumber('id');
});

Route::middleware('auth:sanctum')->prefix('categories')->group(function () {
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
