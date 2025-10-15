<?php

namespace App\Http\Controllers;

use App\Models\Candidature;
use App\Models\Candidat;
use App\Models\User;
use App\Models\Pays;
use App\Mail\CandidatureConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Notification;


class CandidatureController extends Controller
{
    /**
     * Envoie l'email de confirmation avec le code de suivi
     */
    private function sendConfirmationEmail($candidature, $email)
    {
        try {
            // Charge les relations nécessaires
            $candidature->load(['offre', 'candidat.user']);
            
            // Prépare les données pour l'email
            $data = [
                'code_suivi' => $candidature->code,
                'nom' => $candidature->candidat->user->nom ?? 'Candidat',
                'prenom' => $candidature->candidat->user->prenom ?? '',
                'offre_titre' => $candidature->offre->titre ?? 'Offre',
                'entreprise' => $candidature->offre->entreprise ?? '',
                'date_candidature' => $candidature->created_at->format('d/m/Y à H:i'),
            ];
            
            // Envoie l'email
            Mail::to($email)->send(new CandidatureConfirmation($data));
            
            return true;
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas le processus
            \Log::error('Erreur envoi email candidature: ' . $e->getMessage());
            return false;
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'offre_id'              => ['required','exists:offres,id'],
                'candidat_id'           => ['required','exists:candidats,id'],

                // Lettre de motivation (texte OU fichier)
                'lm_source'             => ['nullable','in:upload,text,none'],
                'lettre_motivation'     => ['nullable','string','max:5000'],
                'lettre_motivation_file'=> ['required_if:lm_source,upload','file','mimes:pdf,doc,docx,txt,odt','max:5120'],

                // CV (optionnel)
                'cv_source'             => ['nullable','in:upload,none,existing'],
                'cv'                    => ['required_if:cv_source,upload','file','mimes:pdf,doc,docx','max:5120'],
            ]);

            // Anti-doublon
            $exists = Candidature::where('offre_id', $data['offre_id'])
                ->where('candidat_id', $data['candidat_id'])
                ->exists();

            if ($exists) {
                return response()->json(['success'=>false,'message'=>'Candidature déjà existante'], 422);
            }

            // Upload CV
            $cvPath = null;
            if (($data['cv_source'] ?? null) === 'upload' && $request->hasFile('cv')) {
                $cvPath = $request->file('cv')->store('cvs', 'public');
            }

            // Lettre (texte/fichier)
            $lmPath = null;
            $lmSource = $data['lm_source'] ?? 'none';
            if ($lmSource === 'upload' && $request->hasFile('lettre_motivation_file')) {
                $lmPath = $request->file('lettre_motivation_file')->store('letters', 'public');
            }

            // Payload propre (le code sera généré automatiquement par le Model)
            $payload = [
                'offre_id'    => $data['offre_id'],
                'candidat_id' => $data['candidat_id'],
                'statut'      => 'en_attente',
                'cv'          => $cvPath,
            ];

            if ($lmPath) {
                if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier')) {
                    $payload['lettre_motivation_fichier'] = $lmPath;
                } else {
                    $payload['lettre_motivation'] = '[file] ' . $lmPath;
                }
            } elseif (!empty($data['lettre_motivation'])) {
                $payload['lettre_motivation'] = $data['lettre_motivation'];
            }

            $cand = Candidature::create($payload);
            $cand->loadMissing('offre');

            $candidat = Candidat::with('user')->find($data['candidat_id']);
            if ($candidat && $candidat->user && $candidat->user->email) {
                $this->sendConfirmationEmail($cand, $candidat->user->email);
            }

            if ($candidat && $candidat->user) {
                Notification::pushToUsers(
                    collect([$candidat->user]),
                    'Candidature envoyée',
                    "Votre candidature a bien été enregistrée.\n\nOffre : " . ($cand->offre?->titre ?? '—') .
                    "\nCode de suivi : {$cand->code}\nSuivre votre dossier : " . frontend_url('suivi-candidature')

                );
            }

            // ✅ Publie UNIQUEMENT des endpoints API stables pour le front
            $cand->cv_url = $cand->cv ? url("api/candidatures/{$cand->id}/download/cv") : null;

            $hasLmFile = false;
            if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier') && !empty($cand->lettre_motivation_fichier)) {
                $hasLmFile = true;
            } elseif (!empty($cand->lettre_motivation) && Str::startsWith((string)$cand->lettre_motivation, '[file] ')) {
                $hasLmFile = true;
            }
            $cand->lm_url = $hasLmFile ? url("api/candidatures/{$cand->id}/download/lm") : null;

            return response()->json([
                'success' => true,
                'message' => 'Candidature créée avec succès',
                'code_suivi' => $cand->code,
                'data'    => $cand
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>'Erreur: '.$e->getMessage()],500);
        }
    }

    /** Flux invité */
    public function storeGuest(Request $request)
    {
        $data = $request->validate([
            'offre_id'              => ['required','integer','exists:offres,id'],
            'nom'                   => ['required','string','max:100'],
            'prenom'                => ['required','string','max:100'],
            'email'                 => ['required','email','max:150'],
            'telephone'             => ['nullable','string','max:30'],

            'date_naissance'        => ['required','date','before:today'],
            'pays_code'             => ['nullable','string','size:2'],

            'ville'                 => ['required','string','max:120'],
            'sexe'                  => ['nullable','string','max:20'],
            'niveau_etude'          => ['nullable','string','max:100'],
            'disponibilite'         => ['nullable','string','max:100'],

            'experience'            => ['nullable','integer','min:0','max:60'],

            // LM
            'lm_source'             => ['nullable','in:upload,text,none'],
            'lettre_motivation'     => ['nullable','string','max:5000'],
            'lettre_motivation_file'=> ['required_if:lm_source,upload','file','mimes:pdf,doc,docx,txt,odt','max:5120'],

            // CV
            'cv_source'             => ['nullable','in:upload,none,existing'],
            'cv'                    => ['required_if:cv_source,upload','file','mimes:pdf,doc,docx','max:5120'],
        ]);

        $disponibilite = trim((string)($data['disponibilite'] ?? '')) ?: 'immediate';
        $userEmail = $data['email']; // Sauvegarde l'email pour l'envoi

        return DB::transaction(function () use ($request, $data, $disponibilite, $userEmail) {
            // USER
            $user = User::firstOrCreate(
                ['email'=>$data['email']],
                [
                    'nom'       => $data['nom'],
                    'prenom'    => $data['prenom'],
                    'telephone' => $data['telephone'] ?? null,
                    'password'  => Hash::make(Str::random(28)),
                    'statut'    => 'inactif',
                ]
            );
            if (!$user->nom)       $user->nom = $data['nom'];
            if (!$user->prenom)    $user->prenom = $data['prenom'];
            if (!$user->telephone && !empty($data['telephone'])) $user->telephone = $data['telephone'];
            $user->save();


            // PAYS
            $paysId = null;
            if (!empty($data['pays_code'])) {
                $code = strtoupper($data['pays_code']);
                $col  = Schema::hasColumn('pays','code')     ? 'code'
                      : (Schema::hasColumn('pays','code_iso') ? 'code_iso'
                      : (Schema::hasColumn('pays','alpha2')   ? 'alpha2' : null));
                if ($col) $paysId = Pays::where($col, $code)->value('id');
            }

            // Sexe
            $sexeInput = strtoupper(trim((string)($data['sexe'] ?? '')));
            $sexeStr   = $this->mapSexeToString($sexeInput);
            $sexeInt   = $this->mapSexeToInt($sexeInput);

            $ville       = trim($data['ville']) ?: 'Indéterminée';
            $niveauEtude = $data['niveau_etude'] ?? null;

            // CANDIDAT
            $candidat = Candidat::where('user_id', $user->id)->first();

            if (!$candidat) {
                try {
                    $candidat = Candidat::create([
                        'user_id'        => $user->id,
                        'ville'          => $ville,
                        'niveau_etude'   => $niveauEtude,
                        'sexe'           => $sexeStr,
                        'date_naissance' => $data['date_naissance'],
                        'pays_id'        => $paysId,
                        'disponibilite'  => $disponibilite,
                    ]);
                } catch (QueryException $e) {
                    if ($this->isTruncatedSexe($e)) {
                        try {
                            $candidat = Candidat::create([
                                'user_id'        => $user->id,
                                'ville'          => $ville,
                                'niveau_etude'   => $niveauEtude,
                                'sexe'           => $sexeInt,
                                'date_naissance' => $data['date_naissance'],
                                'pays_id'        => $paysId,
                                'disponibilite'  => $disponibilite,
                            ]);
                        } catch (QueryException $e2) {
                            if ($this->isTruncatedSexe($e2)) {
                                $candidat = Candidat::create([
                                    'user_id'        => $user->id,
                                    'ville'          => $ville,
                                    'niveau_etude'   => $niveauEtude,
                                    'sexe'           => null,
                                    'date_naissance' => $data['date_naissance'],
                                    'pays_id'        => $paysId,
                                    'disponibilite'  => $disponibilite,
                                ]);
                            } else {
                                throw $e2;
                            }
                        }
                    } else {
                        throw $e;
                    }
                }
            } else {
                $dirty = false;
                if (empty($candidat->ville) && $ville) { $candidat->ville = $ville; $dirty = true; }
                if (!$candidat->pays_id && $paysId)    { $candidat->pays_id = $paysId; $dirty = true; }
                if (!$candidat->niveau_etude && $niveauEtude) { $candidat->niveau_etude = $niveauEtude; $dirty = true; }
                if (!$candidat->sexe && $sexeStr)      { $candidat->sexe = $sexeStr; $dirty = true; }
                if (empty($candidat->date_naissance) && !empty($data['date_naissance'])) {
                    $candidat->date_naissance = $data['date_naissance']; $dirty = true;
                }
                if (empty($candidat->disponibilite) && !empty($disponibilite)) {
                    $candidat->disponibilite = $disponibilite; $dirty = true;
                }

                if ($dirty) {
                    try {
                        $candidat->save();
                    } catch (QueryException $e) {
                        if ($this->isTruncatedSexe($e)) {
                            $candidat->sexe = $sexeInt;
                            try { $candidat->save(); }
                            catch (QueryException $e2) {
                                if ($this->isTruncatedSexe($e2)) {
                                    $candidat->sexe = null;
                                    $candidat->save();
                                } else {
                                    throw $e2;
                                }
                            }
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            // Uploads
            $cvPath = null;
            if (($data['cv_source'] ?? null) === 'upload' && $request->hasFile('cv')) {
                $cvPath = $request->file('cv')->store('cvs', 'public');
            }

            $lmPath   = null;
            $lmSource = $data['lm_source'] ?? 'none';
            if ($lmSource === 'upload' && $request->hasFile('lettre_motivation_file')) {
                $lmPath = $request->file('lettre_motivation_file')->store('letters', 'public');
            }

            // Anti-doublon
            $dup = Candidature::where('offre_id', $data['offre_id'])
                ->where('candidat_id', $candidat->id)
                ->exists();
            if ($dup) {
                return response()->json(['success'=>false,'message'=>'Vous avez déjà postulé à cette offre.'], 409);
            }

            // Candidature (le code sera généré automatiquement par le Model)
            $payload = [
                'offre_id'    => $data['offre_id'],
                'candidat_id' => $candidat->id,
                'statut'      => 'en_attente',
                'cv'          => $cvPath,
            ];

            if ($lmPath) {
                if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier')) {
                    $payload['lettre_motivation_fichier'] = $lmPath;
                } else {
                    $payload['lettre_motivation'] = '[file] ' . $lmPath;
                }
            } elseif (!empty($data['lettre_motivation'])) {
                $payload['lettre_motivation'] = $data['lettre_motivation'];
            }

            $cand = Candidature::create($payload);
            $cand->loadMissing('offre');

            // Envoie l'email de confirmation
            // Envoie l'email de confirmation
            $this->sendConfirmationEmail($cand, $userEmail);

            if ($user) {
                Notification::pushToUsers(
                    collect([$user]),
                    'Candidature envoyée',
                    "Votre candidature a bien été enregistrée.\n\nOffre : " . ($cand->offre?->titre ?? '—') .
                    "\nCode de suivi : {$cand->code}\nSuivre votre dossier : " . url('/suivi-candidature')
                );
            }

            // ✅ Publie UNIQUEMENT des endpoints API stables
            $cand->cv_url = $cand->cv ? url("api/candidatures/{$cand->id}/download/cv") : null;

            $hasLmFile = false;
            if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier') && !empty($cand->lettre_motivation_fichier)) {
                $hasLmFile = true;
            } elseif (!empty($cand->lettre_motivation) && Str::startsWith((string)$cand->lettre_motivation, '[file] ')) {
                $hasLmFile = true;
            }
            $cand->lm_url = $hasLmFile ? url("api/candidatures/{$cand->id}/download/lm") : null;

            return response()->json([
                'success' => true,
                'message' => 'Candidature envoyée avec succès. Un email de confirmation vous a été envoyé.',
                'code_suivi' => $cand->code,
                'data'    => $cand->load(['offre','candidat.user'])
            ], 201);
        });
    }

    // supprimer une candidature
    public function destroy($id)
    {
        $candidature = Candidature::find($id);
        if (!$candidature) {
            return response()->json(['success' => false, 'message' => 'Candidature non trouvée'], 404);
        }
        $candidature->delete();
        return response()->json(['success' => true, 'message' => 'Candidature supprimée']);
    }

    // supprimer plusieurs candidatures
    public function destroyMultiple(Request $request)
    {
        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['success' => false, 'message' => 'Aucun ID fourni'], 400);
        }
        $deletedCount = Candidature::whereIn('id', $ids)->delete();
        return response()->json(['success' => true, 'message' => "$deletedCount candidatures supprimées"]);
    }

    /**
     * NOUVELLE MÉTHODE: Recherche une candidature par son code de suivi
     * Endpoint: GET /api/candidatures/suivi/{code}
     */
    public function findByCode($code)
    {
        try {
            // 1) Validation du format: CAND-YYYY-XXXXXX
            if (!preg_match('/^CAND-\d{4}-[A-Z0-9]{6}$/', $code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format de code invalide. Le code doit être au format CAND-ANNÉE-XXXXXX (ex: CAND-2025-A1B2C3)'
                ], 400);
            }

            // 2) Chargement des relations (entreprise peut être string OU relation)
            $candidature = Candidature::with([
                'offre.entreprise',   // si tu as une relation entreprise() sur Offre
                'candidat.user',
            ])->where('code', $code)->first();

            if (!$candidature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune candidature trouvée avec ce code de suivi.'
                ], 404);
            }

            // 3) Normalisation des champs — safe pour les nulls
            $offre = $candidature->offre;
            $entreprise = null;

            // Si relation Entreprise:
            if ($offre && method_exists($offre, 'getRelation') && $offre->relationLoaded('entreprise') && $offre->entreprise) {
                // Adapte le nom si ton champ diffère (nom_entreprise vs name…)
                $entreprise = $offre->entreprise->nom_entreprise ?? $offre->entreprise->name ?? null;
            }
            // Sinon, si sauvegardé en string directement dans offres.entreprise :
            if (!$entreprise && $offre && isset($offre->entreprise)) {
                $entreprise = $offre->entreprise;
            }

            $user = $candidature->candidat?->user;

            // Endpoints stables de download
            $cvUrl = $candidature->cv ? url("api/candidatures/{$candidature->id}/download/cv") : null;
            $lmUrl = $this->getLmUrl($candidature);

            // 4) Payload pour le front (celui attendu par ton service Angular)
            $response = [
                'id'                => $candidature->id,
                'code_suivi'        => $candidature->code, // clé attendue par le front
                'statut'            => $candidature->statut,
                'message_statut'    => $candidature->message_statut ?? null,

                'date_candidature'  => optional($candidature->created_at)->format('Y-m-d H:i:s'),
                'date_mise_a_jour'  => optional($candidature->updated_at)->format('Y-m-d H:i:s'),

                // Optionnels si tu les stockes
                'date_examen'       => $candidature->date_examen ?? null,
                'date_entretien'    => $candidature->date_entretien ?? null,
                'date_decision'     => $candidature->date_decision ?? null,

                'offre' => $offre ? [
                    'id'           => $offre->id,
                    'titre'        => $offre->titre ?? null,
                    'entreprise'   => $entreprise,
                    'lieu'         => $offre->lieu ?? ($offre->localisation ?? null),
                    'type_contrat' => $offre->type_contrat ?? null,
                    'date_limite'  => $offre->date_limite ?? null,
                ] : null,

                'candidat' => [
                    'nom'       => $user->nom ?? null,
                    'prenom'    => $user->prenom ?? null,
                    'email'     => $user->email ?? null,
                    'telephone' => $user->telephone ?? null,
                ],

                'cv_url' => $cvUrl,
                'lm_url' => $lmUrl,
            ];

            // 5) Message statique si besoin (garde le tien si déjà présent en DB)
            if (!$response['message_statut']) {
                switch ($candidature->statut) {
                    case 'en_attente': $response['message_statut'] = "Votre candidature est en cours d'examen."; break;
                    case 'acceptee':   $response['message_statut'] = "Félicitations ! Votre candidature a été acceptée."; break;
                    case 'refusee':    $response['message_statut'] = "Votre candidature n'a pas été retenue."; break;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $response
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('findByCode error', ['code' => $code, 'e' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de la recherche de la candidature.'
            ], 500);
        }
    }

    /**
     * NOUVELLE MÉTHODE: Renvoie l'email de confirmation
     * Endpoint: POST /api/candidatures/renvoyer-email
     */
    public function resendEmail(Request $request)
    {
        $request->validate([
            'code_suivi' => 'required|string|regex:/^CAND-\d{4}-[A-Z0-9]{6}$/',
        ]);

        try {
            $candidature = Candidature::with(['candidat.user', 'offre.entreprise'])
                ->where('code', $request->code_suivi)
                ->first();

            if (!$candidature) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune candidature trouvée avec ce code.'
                ], 404);
            }

            $email = $candidature->candidat?->user?->email;
            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune adresse email associée à cette candidature.'
                ], 400);
            }

            $sent = $this->sendConfirmationEmail($candidature, $email);

            return $sent
                ? response()->json(['success' => true, 'message' => 'Email de confirmation renvoyé avec succès.'], 200)
                : response()->json(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard.'], 500);

        } catch (\Throwable $e) {
            \Log::error('resendEmail error', ['code' => $request->code_suivi, 'e' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de l\'envoi de l\'email.'
            ], 500);
        }
    }


    /**
     * Helper pour obtenir l'URL de la lettre de motivation
     */
    private function getLmUrl($candidature)
    {
        try {
            $path = null;

            // 1) Colonne dédiée si elle existe
            if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier')) {
                $path = $candidature->lettre_motivation_fichier ?: null;
            }

            // 2) Fallback "[file] path" si stockée en texte
            if (!$path && !empty($candidature->lettre_motivation)) {
                $txt = (string)$candidature->lettre_motivation;
                if (Str::startsWith($txt, '[file] ')) {
                    $maybe = trim(substr($txt, 7));
                    if ($maybe !== '') $path = $maybe;
                }
            }

            return $path ? url("api/candidatures/{$candidature->id}/download/lm") : null;

        } catch (\Throwable $e) {
            \Log::warning('getLmUrl error', ['id' => $candidature->id ?? null, 'e' => $e]);
            return null;
        }
    }

    public function index(Request $request)
    {
        try {
            Log::info('=== index() - Toutes les candidatures (Admin) ===');
            
            $user = $request->user();
            
            // Vérifier que c'est un admin
            if (!$user || ($user->role !== 'Administrateur' && $user->role !== 'admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé aux administrateurs'
                ], 403);
            }
            
            // Récupérer TOUTES les candidatures
            // ✅ CORRECTION : charger 'candidat' (qui est un User), pas 'candidat.user'
            $candidatures = Candidature::with([
                'offre:id,titre,type_contrat,localisation',
                'candidat:id,nom,prenom,email,telephone'  // ✅ Directement le User
            ])
            ->orderBy('created_at', 'desc')
            ->get();
            
            Log::info("Candidatures trouvées: {$candidatures->count()}");
            
            // Formater les données
            $formatted = $candidatures->map(function($candidature) {
                return [
                    'id' => $candidature->id,
                    'code' => $candidature->code,
                    'statut' => $candidature->statut,
                    'created_at' => $candidature->created_at,
                    
                    // Infos de l'offre
                    'offre_titre' => $candidature->offre?->titre ?? 'N/A',
                    'offre_type_contrat' => $candidature->offre?->type_contrat ?? 'N/A',
                    'offre_localisation' => $candidature->offre?->localisation ?? 'N/A',
                    
                    // ✅ Infos du candidat (directement depuis User)
                    'candidat_nom' => $candidature->candidat ? 
                        trim(($candidature->candidat->prenom ?? '') . ' ' . ($candidature->candidat->nom ?? '')) : 
                        'N/A',
                    'candidat_email' => $candidature->candidat?->email ?? 'N/A',
                    'candidat_telephone' => $candidature->candidat?->telephone ?? 'N/A',
                    
                    // Fichiers
                    'cv' => $candidature->cv,
                    'lettre_motivation' => $candidature->lettre_motivation,
                    'lettre_motivation_fichier' => $candidature->lettre_motivation_fichier,
                    
                    // URLs de téléchargement
                    'cv_url' => $candidature->cv ? 
                        url("api/candidatures/{$candidature->id}/download/cv") : null,
                    'lm_url' => ($candidature->lettre_motivation_fichier || 
                               (isset($candidature->lettre_motivation) && 
                                \Str::startsWith($candidature->lettre_motivation, '[file]'))) ? 
                        url("api/candidatures/{$candidature->id}/download/lm") : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formatted
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur index():', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function mesCandidatures(Request $request, $candidatParamId = null)
{
    // 1) Priorité au paramètre de route /mes-candidatures/{candidat}
    $candidatId = $candidatParamId;

    // 2) Sinon query string ?candidat_id=123
    if (!$candidatId) {
        $candidatId = $request->query('candidat_id');
    }

    // 3) Sinon déduire du user connecté (via Sanctum)
    if (!$candidatId && $request->user()) {
        $candidatId = optional($request->user()->candidat)->id;
    }

    if (!$candidatId) {
        return response()->json(['success' => false, 'message' => 'candidat_id requis'], 422);
    }

    $rows = \App\Models\Candidature::where('candidat_id', $candidatId)
        ->with(['offre'])
        ->get();
    
    // ⬇️ On charge aussi le candidat et son user
    $rows = Candidature::where('candidat_id', $candidatId)
        ->with(['candidat.user', 'offre'])
        ->get();

    return response()->json(['success' => true, 'data' => $rows]);
}


    public function updateStatut(Request $request, $id)
    {
        $c = Candidature::find($id);
        if (!$c) return response()->json(['success'=>false,'message'=>'Candidature non trouvée'],404);

        $request->validate(['statut'=>'required|in:en_attente,acceptee,refusee']);
        $c->update(['statut'=>$request->statut]);

        return response()->json(['success'=>true,'message'=>'Statut mis à jour','data'=>$c]);
    }

    public function getByOffre($offreId)
    {
        $rows = Candidature::where('offre_id',$offreId)->with(['candidat.user','offre'])->get();
        return response()->json(['success'=>true,'data'=>$rows]);
    }

    // ===== Helpers =====

    private function isTruncatedSexe(QueryException $e): bool
    {
        $m = $e->getMessage();
        return stripos($m, "Data truncated") !== false && stripos($m, "'sexe'") !== false;
    }

    private function mapSexeToString(?string $s): ?string
    {
        $s = strtoupper(trim((string)$s));
        if (in_array($s, ['M','H','HOMME','MASCULIN']))  return 'Masculin';
        if (in_array($s, ['F','FEMME','FÉMININ','FEMININ'])) return 'Feminin';
        if (in_array($s, ['X','AUTRE'])) return 'Autre';
        return null;
    }

    private function mapSexeToInt(?string $s): ?int
    {
        $s = strtoupper(trim((string)$s));
        if (in_array($s, ['M','H','HOMME','MASCULIN']))  return 1;
        if (in_array($s, ['F','FEMME','FÉMININ','FEMININ'])) return 2;
        if (in_array($s, ['X','AUTRE'])) return 0;
        return null;
    }

    // === Downloads par binding (facultatif si tu utilises /byId)
    public function downloadCv(Candidature $candidature): StreamedResponse
    {
        $path = $candidature->cv;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Fichier CV introuvable');
        return Storage::disk('public')->download($path, basename($path));
    }

    public function downloadLm(Candidature $candidature): StreamedResponse
    {
        $path = $candidature->lettre_motivation_fichier ?? null;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Fichier LM introuvable');
        return Storage::disk('public')->download($path, basename($path));
    }

    // === Downloads via id (endpoints stables pour le front)
    public function downloadCvById($id)
    {
        $cand = Candidature::find($id);
        if (!$cand) return response()->json(['success' => false, 'error' => 'candidature_not_found'], 404);

        $path = $cand->cv;
        if (!$path) return response()->json(['success' => false, 'error' => 'cv_missing'], 404);
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['success' => false, 'error' => 'cv_file_not_found', 'path' => $path], 404);
        }

        return Storage::disk('public')->download($path, basename($path));
    }

    public function downloadLmById($id)
    {
        $cand = Candidature::find($id);
        if (!$cand) return response()->json(['success' => false, 'error' => 'candidature_not_found'], 404);

        $path = null;

        // 1) Colonne dédiée si elle existe
        if (Schema::hasColumn('candidatures', 'lettre_motivation_fichier')) {
            $path = $cand->lettre_motivation_fichier ?: null;
        }

        // 2) Fallback "[file] path"
        if (!$path && !empty($cand->lettre_motivation)) {
            $txt = (string)$cand->lettre_motivation;
            if (Str::startsWith($txt, '[file] ')) {
                $maybe = trim(substr($txt, 7));
                if ($maybe !== '') $path = $maybe;
            }
        }

        if (!$path) return response()->json(['success' => false, 'error' => 'lm_missing'], 404);
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['success' => false, 'error' => 'lm_file_not_found', 'path' => $path], 404);
        }

        return Storage::disk('public')->download($path, basename($path));
    }

     public function candidaturesRecues(Request $request)
        {
            try {
                \Log::info('=== DEBUT candidaturesRecues ===');
                
                $user = $request->user();
                
                if (!$user) {
                    return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
                }
                
                if ($user->role !== 'Recruteur') {
                    return response()->json(['success' => false, 'message' => 'Accès réservé aux recruteurs'], 403);
                }
                
                \Log::info('Récupération des candidatures avec relations...');
                
                // Récupérer avec les relations
                $candidatures = Candidature::whereHas('offre', function($query) use ($user) {
                    $query->where('recruteur_id', $user->id);
                })
                ->with(['offre:id,titre,type_contrat,localisation']) // ✅ Charger offre
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
                
                \Log::info('Candidatures avec offre chargées: ' . $candidatures->count());
                
                // Formater les données
                $formatted = $candidatures->map(function($candidature) {
                    \Log::info("Traitement candidature ID: {$candidature->id}");
                    
                    // Récupérer le candidat (User)
                    $candidatUser = null;
                    try {
                        $candidatUser = \App\Models\User::find($candidature->candidat_id);
                        \Log::info("Candidat trouvé: " . ($candidatUser ? $candidatUser->email : 'NULL'));
                    } catch (\Exception $e) {
                        \Log::error("Erreur chargement candidat: " . $e->getMessage());
                    }
                    
                    return [
                        'id' => $candidature->id,
                        'code' => $candidature->code,
                        'statut' => $candidature->statut,
                        'created_at' => $candidature->created_at,
                        
                        // Infos de l'offre
                        'offre_titre' => $candidature->offre?->titre ?? 'N/A',
                        'offre_type_contrat' => $candidature->offre?->type_contrat ?? 'N/A',
                        'offre_localisation' => $candidature->offre?->localisation ?? 'N/A',
                        
                        // Infos du candidat (User)
                        'candidat_nom' => $candidatUser ? 
                            trim(($candidatUser->prenom ?? '') . ' ' . ($candidatUser->nom ?? '')) : 
                            'N/A',
                        'candidat_email' => $candidatUser?->email ?? 'N/A',
                        'candidat_telephone' => $candidatUser?->telephone ?? 'N/A',
                        
                        // Fichiers
                        'cv' => $candidature->cv,
                        'lettre_motivation' => $candidature->lettre_motivation,
                        'lettre_motivation_fichier' => $candidature->lettre_motivation_fichier,
                        
                        // URLs de téléchargement
                        'cv_url' => $candidature->cv ? 
                            url("api/candidatures/{$candidature->id}/download/cv") : null,
                        'lm_url' => ($candidature->lettre_motivation_fichier || 
                                (isset($candidature->lettre_motivation) && 
                                    \Str::startsWith($candidature->lettre_motivation, '[file]'))) ? 
                            url("api/candidatures/{$candidature->id}/download/lm") : null,
                    ];
                });
                
                \Log::info('=== FIN candidaturesRecues SUCCESS ===');
                
                return response()->json([
                    'success' => true,
                    'data' => $formatted
                ]);
                
            } catch (\Exception $e) {
                \Log::error('=== ERREUR candidaturesRecues ===');
                \Log::error('Message: ' . $e->getMessage());
                \Log::error('File: ' . $e->getFile());
                \Log::error('Line: ' . $e->getLine());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors du chargement',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }
        }

    
}