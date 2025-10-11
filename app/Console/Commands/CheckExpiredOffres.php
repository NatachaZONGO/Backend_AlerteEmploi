<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Offre;
use App\Notifications\OffreExpiree;
use Carbon\Carbon;

class CheckExpiredOffres extends Command
{
    protected $signature = 'offres:check-expired';
    protected $description = 'Vérifier et notifier les offres expirées';

    public function handle()
    {
        $offresExpirees = Offre::where('statut', 'publiee')
            ->where('date_expiration', '<', Carbon::today())
            ->get();

        foreach ($offresExpirees as $offre) {
            // Changer le statut
            $offre->update(['statut' => 'expiree']);
            
            // Notifier le recruteur
            $offre->recruteur->notify(new OffreExpiree($offre));
            
            $this->info("Offre expirée: {$offre->titre}");
        }

        $this->info("Vérification terminée. {$offresExpirees->count()} offres expirées.");
    }
}

