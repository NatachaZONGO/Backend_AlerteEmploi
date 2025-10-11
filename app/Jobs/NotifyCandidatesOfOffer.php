<?php

namespace App\Jobs;

use App\Mail\OfferPublishedNotification; // ← assure-toi que ce Mailable existe
use App\Models\Candidat;
use App\Models\Offre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyCandidatesOfOffer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $offreId;

    public function __construct(int $offreId)
    {
        $this->offreId = $offreId;
    }

    public function handle(): void
    {
        $offre = Offre::with(['categorie', 'entreprise'])->find($this->offreId);

        if (!$offre) {
            Log::warning('[NotifyCandidatesOfOffer] Offre introuvable', ['offre_id' => $this->offreId]);
            return;
        }

        if ($offre->statut !== 'publiee') {
            Log::info('[NotifyCandidatesOfOffer] Offre non publiée → pas d’envoi', [
                'offre_id' => $offre->id,
                'statut'   => $offre->statut
            ]);
            return;
        }

        // 🔗 Lien vers la LISTE des offres (pas de /:id)
        $frontendBase = rtrim(Config::get('app.frontend_url', 'http://localhost:4200'), '/');
        $lienOffres   = $frontendBase . '/offres';

        $categorieNom = optional($offre->categorie)->nom ?: '—';
        $texte = "Une nouvelle offre « {$offre->titre} » a été publiée dans la catégorie {$categorieNom}.\n\nVoir les offres : {$lienOffres}";

        // Notifier candidats actifs (même catégorie), par lots
        $countTotal = 0;
        Candidat::with('user')
            ->where('categorie_id', $offre->categorie_id)
            ->whereHas('user', function ($q) {
                $q->where('statut', 'actif')->whereNotNull('email');
            })
            ->chunkById(200, function ($candidats) use ($offre, $lienOffres, &$countTotal) {
                foreach ($candidats as $cand) {
                    $user = $cand->user;
                    if (!$user || empty($user->email)) {
                        continue;
                    }

                    try {
                        Mail::to($user->email)->queue(
                            new OfferPublishedNotification(
                                offre: $offre,
                                destinatairePrenom: $user->prenom ?? '',
                                destinataireNom: $user->nom ?? '',
                                lienOffre: $lienOffres   // ← on passe la liste
                            )
                        );
                        $countTotal++;
                    } catch (\Throwable $e) {
                        Log::error('[NotifyCandidatesOfOffer] Envoi KO', [
                            'to'  => $user->email,
                            'err' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('[NotifyCandidatesOfOffer] Envois terminés', [
            'offre_id'  => $offre->id,
            'categorie' => $categorieNom,
            'envoyes'   => $countTotal,
            'lien'      => $lienOffres,
        ]);
    }
}
