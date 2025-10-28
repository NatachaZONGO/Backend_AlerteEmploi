<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fa;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #555;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        .reset-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        .reset-button:hover {
            transform: translateY(-2px);
        }
        .alternative-link {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .alternative-link p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }
        .alternative-link a {
            word-break: break-all;
            color: #667eea;
            font-size: 13px;
        }
        .warning {
            margin-top: 30px;
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        .warning p {
            margin: 0;
            font-size: 14px;
            color: #856404;
        }
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #6c757d;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .security-info {
            margin-top: 30px;
            padding: 20px;
            background: #e7f3ff;
            border-radius: 8px;
        }
        .security-info h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #0066cc;
        }
        .security-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .security-info li {
            font-size: 14px;
            color: #555;
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>🔒 Réinitialisation de mot de passe</h1>
            <p>AlertEmploi - Votre plateforme emploi</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Bonjour <strong>{{ $user->prenom }} {{ $user->nom }}</strong>,</p>

            <p class="message">
                Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte AlertEmploi.
                Pour définir un nouveau mot de passe, cliquez sur le bouton ci-dessous :
            </p>

            <!-- Bouton principal -->
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="reset-button">
                    Réinitialiser mon mot de passe
                </a>
            </div>

            <!-- Lien alternatif -->
            <div class="alternative-link">
                <p><strong>Le bouton ne fonctionne pas ?</strong></p>
                <p>Copiez et collez ce lien dans votre navigateur :</p>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </div>

            <!-- Avertissement -->
            <div class="warning">
                <p>
                    ⏰ <strong>Important :</strong> Ce lien expire dans <strong>60 minutes</strong>.
                    Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.
                </p>
            </div>

            <!-- Conseils sécurité -->
            <div class="security-info">
                <h3>💡 Conseils de sécurité</h3>
                <ul>
                    <li>Choisissez un mot de passe fort avec au moins 8 caractères</li>
                    <li>Utilisez une combinaison de lettres, chiffres et symboles</li>
                    <li>Ne partagez jamais votre mot de passe</li>
                    <li>Changez régulièrement vos mots de passe</li>
                </ul>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>AlertEmploi</strong></p>
            <p>Votre plateforme de recrutement au Burkina Faso</p>
            <p style="margin-top: 15px;">
                <a href="mailto:contact@alertemploi.bf">contact@alertemploi.bf</a>
            </p>
            <p style="font-size: 12px; color: #999; margin-top: 20px;">
                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
        </div>
    </div>
</body>
</html>