<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Nouvelle offre</title></head>
<body style="font-family: Arial,sans-serif; background:#f6f6f6; padding:20px;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;">
    <div style="background:#2563eb;color:#fff;padding:18px;">
      <h2 style="margin:0;">Nouvelle offre publiée</h2>
    </div>

    <div style="padding:20px;">
      <p>Bonjour <strong>{{ $prenom }} {{ $nom }}</strong>,</p>

      <p>Une nouvelle offre correspondant à votre catégorie <strong>{{ $categorie }}</strong> vient d’être publiée :</p>

      <div style="background:#f3f4f6;border-radius:8px;padding:14px;margin:14px 0;">
        <p style="margin:6px 0;"><strong>Poste :</strong> {{ $offre->titre }}</p>
        @if($entreprise)
          <p style="margin:6px 0;"><strong>Entreprise :</strong> {{ $entreprise }}</p>
        @endif
        <p style="margin:6px 0;"><strong>Localisation :</strong> {{ $offre->localisation ?? '—' }}</p>
        <p style="margin:6px 0;"><strong>Type de contrat :</strong> {{ $offre->type_contrat ?? '—' }}</p>
      </div>

      <p style="text-align:center;margin:22px 0;">
        <a href="{{ $lien }}" style="background:#16a34a;color:#fff;text-decoration:none;padding:12px 20px;border-radius:6px;display:inline-block;">
          Voir l’offre
        </a>
      </p>

      <p style="color:#6b7280;">Bonne chance !</p>
      <p style="color:#6b7280;">L’équipe AlerteEmploi</p>
    </div>

    <div style="background:#f3f4f6;padding:14px;text-align:center;color:#6b7280;">
      <small>© {{ date('Y') }} AlerteEmploi. Tous droits réservés.</small>
    </div>
  </div>
</body>
</html>
