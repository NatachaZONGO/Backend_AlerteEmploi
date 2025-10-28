<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEntrepriseAccess
{
    /**
     * Vérifie que l'utilisateur a le droit de gérer l'entreprise
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        // Récupérer l'ID de l'entreprise depuis la route ou le body
        $entrepriseId = $request->route('entreprise_id') 
                     ?? $request->route('id') 
                     ?? $request->input('entreprise_id');

        // Si pas d'entreprise spécifiée, on laisse passer
        // (cas des routes qui listent toutes les entreprises gérables)
        if (!$entrepriseId) {
            return $next($request);
        }

        // Vérifier les droits
        if (!$user->canManageEntreprise($entrepriseId)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise'
            ], 403);
        }

        return $next($request);
    }
}