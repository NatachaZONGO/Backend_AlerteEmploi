<?php

namespace App\Mail;

use App\Models\Offre;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OfferPublishedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Offre $offre,
        public string $destinatairePrenom,
        public string $destinataireNom,
        public string $lienOffre
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nouvelle offre publiée : {$this->offre->titre}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.offer-published',
            with: [
                'prenom' => $this->destinatairePrenom,
                'nom' => $this->destinataireNom,
                'offre' => $this->offre,
                'categorie' => optional($this->offre->categorie)->nom,
                'entreprise' => optional($this->offre->entreprise)->nom_entreprise,
                'lien' => $this->lienOffre,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
