<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de candidature</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .code-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            border: 2px dashed #667eea;
        }
        .code-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            letter-spacing: 3px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .details h3 {
            color: #495057;
            font-size: 16px;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .detail-item {
            margin: 10px 0;
            display: flex;
            align-items: flex-start;
        }
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 100px;
            margin-right: 10px;
        }
        .detail-value {
            color: #2c3e50;
            flex: 1;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: #ffc107;
            color: #856404;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .instructions {
            background-color: #e8f4f8;
            border-left: 4px solid #17a2b8;
            padding: 15px 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .instructions h4 {
            color: #0c5460;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            color: #0c5460;
            margin: 5px 0;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.3s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .footer p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 14px;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #6c757d;
            text-decoration: none;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 10px 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .warning strong {
            color: #7c5a0b;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✨ Candidature Confirmée</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">
                Bonjour <strong>{{ $prenom }} {{ $nom }}</strong>,
            </p>

            <p>
                Nous avons bien reçu votre candidature et nous vous remercions de l'intérêt que vous portez à notre offre. 
                Votre dossier sera examiné dans les meilleurs délais.
            </p>

            <!-- Code de suivi -->
            <div class="code-box">
                <div class="code-label">Votre code de suivi</div>
                <div class="code">{{ $code_suivi }}</div>
                <small style="color: #7f8c8d;">Conservez précieusement ce code</small>
            </div>

            <!-- Détails de la candidature -->
            <div class="details">
                <h3>📋 Détails de votre candidature</h3>
                
                <div class="detail-item">
                    <span class="detail-label">Poste :</span>
                    <span class="detail-value"><strong>{{ $offre_titre }}</strong></span>
                </div>

                @if($entreprise)
                <div class="detail-item">
                    <span class="detail-label">Entreprise :</span>
                    <span class="detail-value">{{ $entreprise }}</span>
                </div>
                @endif

                <div class="detail-item">
                    <span class="detail-label">Date :</span>
                    <span class="detail-value">{{ $date_candidature }}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Statut :</span>
                    <span class="detail-value">
                        <span class="status-badge">En attente</span>
                    </span>
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4>💡 Comment suivre votre candidature ?</h4>
                <ul>
                    <li>Utilisez votre <strong>code de suivi {{ $code_suivi }}</strong> pour consulter l'état de votre candidature</li>
                    <li>Accédez à notre plateforme en ligne pour voir les mises à jour en temps réel</li>
                    <li>Vous recevrez un email dès qu'il y aura une évolution de votre dossier</li>
                </ul>
            </div>

            <!-- Call to Action -->
            <div class="button-container">
                <a href="{{ url('/suivi-candidature') }}" class="button">
                    Suivre ma candidature
                </a>
            </div>

            <!-- Warning -->
            <div class="warning">
                <strong>⚠️ Important :</strong> Cet email est automatique, merci de ne pas y répondre directement. 
                Pour toute question, utilisez le formulaire de contact sur notre plateforme.
            </div>

            <!-- Message de fin -->
            <p style="margin-top: 30px; color: #6c757d;">
                Nous vous souhaitons bonne chance pour la suite du processus de recrutement !
            </p>

            <p style="color: #6c757d;">
                Cordialement,<br>
                <strong>L'équipe de recrutement</strong>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© {{ date('Y') }} AlerteEmploi&Offres. Tous droits réservés.</p>
            <p style="font-size: 12px; color: #adb5bd;">
                Cet email a été envoyé à {{ $prenom }} {{ $nom }} suite à votre candidature.
            </p>
            
            <!-- Optional: Social Links -->
            <div class="social-links">
                <a href="#">LinkedIn</a>
                <a href="#">Twitter</a>
                <a href="#">Facebook</a>
            </div>

            <p style="font-size: 12px; margin-top: 15px;">
                <a href="{{ url('/privacy') }}" style="color: #6c757d;">Politique de confidentialité</a> | 
                <a href="{{ url('/terms') }}" style="color: #6c757d;">Conditions d'utilisation</a>
            </p>
        </div>
    </div>
</body>
</html>