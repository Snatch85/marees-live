<?php
/**
 * Script d'amélioration automatique — GitHub Actions
 * Appelé chaque nuit par le workflow automarees.yml
 */

$api_key = getenv('MISTRAL_API_KEY');
$file    = __DIR__ . '/../index.php';
$goal = <<<'DIRECTIVE'
========================================================
MISSION : Site de marées Loire-Atlantique avec données RÉELLES
========================================================

Tu améliores un site PHP de marées pour la Loire-Atlantique (44).
L'objectif principal EST D'INTÉGRER L'API WORLDTIDES pour avoir
de vraies données de marées officielles au lieu de calculs approximatifs.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. INTÉGRATION API WORLDTIDES (priorité absolue)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Endpoint :
  https://www.worldtides.info/api/v3

Paramètres GET disponibles :
  key           = clé API (obligatoire)
  lat           = latitude en degrés décimaux
  lon           = longitude en degrés décimaux
  date          = YYYY-MM-DD ou "today"
  days          = nombre de jours (1 à 7)
  datum         = référence verticale → utilise "LAT" (Lowest Astronomical Tide = zéro des cartes marines françaises)
  extremes      = retourne les hautes et basses mers
  heights       = retourne les hauteurs toutes les N secondes
  step          = intervalle en secondes pour heights (utilise 1800 = toutes les 30min)
  localtime     = inclut la date au format ISO 8601 local
  timezone      = inclut le fuseau horaire dans la réponse

URL complète pour 3 jours de données (hautes/basses mers + courbe) :
  https://www.worldtides.info/api/v3?extremes&heights&date=today&days=3&step=1800&datum=LAT&localtime&lat=LAT&lon=LON&key=CLE

Structure JSON retournée :
{
  "status": 200,
  "heights": [
    { "dt": 1714262400, "date": "2024-04-28T02:00+0200", "height": 2.34 },
    ...  (un point toutes les 30 min)
  ],
  "extremes": [
    { "dt": 1714262400, "date": "2024-04-28T02:48+0200", "height": 5.21, "type": "High" },
    { "dt": 1714276800, "date": "2024-04-28T06:40+0200", "height": 0.42, "type": "Low" },
    ...
  ]
}

Implémentation PHP à créer :
  define('WORLDTIDES_KEY', 'VOTRE_CLE_ICI');  ← placeholder, l'utilisateur la remplacera

  function fetchTides(float $lat, float $lon, int $days = 3): array {
      // Cache fichier 6h dans sys_get_temp_dir() pour économiser les crédits
      $cacheFile = sys_get_temp_dir() . '/tides_' . md5("$lat,$lon,$days") . '_' . date('YmdH') . '.json';
      if (file_exists($cacheFile)) return json_decode(file_get_contents($cacheFile), true);

      $url = "https://www.worldtides.info/api/v3?extremes&heights&date=today&days={$days}&step=1800&datum=LAT&localtime"
           . "&lat={$lat}&lon={$lon}&key=" . WORLDTIDES_KEY;

      $ch = curl_init($url);
      curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
      $raw = curl_exec($ch);
      curl_close($ch);

      $data = json_decode($raw, true);
      if (($data['status'] ?? 0) === 200) {
          file_put_contents($cacheFile, $raw);
      }
      return $data ?? [];
  }

Si WORLDTIDES_KEY === 'VOTRE_CLE_ICI' ou si l'API échoue → fallback sur le calcul sinusoïdal existant.
Affiche un bandeau jaune discret en haut : "Données estimées — configurez WORLDTIDES_KEY pour les données officielles".
Si l'API est configurée → affiche un bandeau vert : "Données officielles WorldTides".

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
2. PORTS LOIRE-ATLANTIQUE avec coordonnées GPS exactes
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Tous ces ports DOIVENT être présents avec leurs coordonnées exactes :

  'Saint-Nazaire'       => ['lat' => 47.2706, 'lon' => -2.2132, 'ref_marnage' => 5.10],
  'La Baule'            => ['lat' => 47.2889, 'lon' => -2.3889, 'ref_marnage' => 4.90],
  'Pornichet'           => ['lat' => 47.2658, 'lon' => -2.3397, 'ref_marnage' => 4.90],
  'Le Croisic'          => ['lat' => 47.2950, 'lon' => -2.5136, 'ref_marnage' => 4.80],
  'La Turballe'         => ['lat' => 47.3489, 'lon' => -2.5158, 'ref_marnage' => 4.75],
  'Piriac-sur-Mer'      => ['lat' => 47.3808, 'lon' => -2.5447, 'ref_marnage' => 4.60],
  'Pornic'              => ['lat' => 47.1111, 'lon' => -2.1011, 'ref_marnage' => 4.50],
  'Saint-Brévin'        => ['lat' => 47.2439, 'lon' => -2.1611, 'ref_marnage' => 5.00],
  'Noirmoutier'         => ['lat' => 47.0003, 'lon' => -2.2508, 'ref_marnage' => 4.20],
  "L'Herbaudière"       => ['lat' => 47.0217, 'lon' => -2.3036, 'ref_marnage' => 4.10],

Le champ ref_marnage est le marnage de référence en mètres (vive-eau moyenne).
Il sert à calculer le coefficient : coeff = round(($HM - $BM) / $ref_marnage * 95)
Coeff 20 = morte-eau faible, 95 = vive-eau moyenne, 120 = grande marée exceptionnelle.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3. AFFICHAGE DES DONNÉES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Pour chaque marée extrême (High/Low), afficher :
  - Heure locale (ex: 06h42)
  - Type : "↑ Haute Mer" ou "↓ Basse Mer"
  - Hauteur en mètres (ex: 5.21 m)
  - Coefficient calculé (affiché uniquement après une BM : coeff entre la PM précédente et cette BM)

Courbe SVG :
  - Utiliser les points "heights" (toutes les 30 min) pour tracer la courbe réelle
  - Axe Y : de 0m à max_height + 0.5m
  - Colorier la zone sous la courbe en dégradé bleu (#006994 → #00d4ff)
  - Marquer les extrêmes par des points et étiquettes

Section "Pêche à pied" (si coeff > 70) :
  - Créneau idéal : 2h avant BM jusqu'à 2h après BM
  - Afficher : "Créneau pêche : 04h40 → 08h40" (exemple)
  - Badge coloré selon le coeff : vert > 95, orange 70-95, gris < 70

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
4. DESIGN & UX
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- Sélecteur de port en haut, mémorisé via localStorage
- Thème sombre marin : background #0a0e1a, accents #00d4ff et #0077b6
- Cartes pour chaque jour (aujourd'hui, demain, après-demain)
- Responsive mobile : une colonne sur mobile, grille sur desktop
- Icônes SVG inline pour montée ↑ et descente ↓
- Bandeau source de données (vert = API réelle, jaune = estimé)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
5. RÈGLES ABSOLUES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Fichier PHP UNIQUE auto-suffisant (pas de require, pas de Composer).
2. Conserve TOUTES les fonctionnalités existantes sans rien casser.
3. Le code doit être COMPLET du <?php initial jusqu'à la dernière ligne HTML.
4. NE JAMAIS tronquer ou résumer le code — retourner le fichier entier.
5. Incrémente le numéro de VERSION dans le define().
6. Si l'API WorldTides n'est pas encore intégrée → c'est la priorité numéro 1.
   Si elle est déjà intégrée → améliore l'affichage ou ajoute un port manquant.
DIRECTIVE;


// Modèle et timeout adaptés à la taille du fichier
$model   = 'mistral-small-latest'; // Plus rapide pour les gros fichiers
$timeout = 300;                     // 5 minutes max

// ── Vérifications ─────────────────────────────────────────────────────────────
if (!$api_key) {
    echo "❌ MISTRAL_API_KEY non définie dans les secrets GitHub\n";
    echo "   → Va dans ton repo GitHub > Settings > Secrets > Actions > New secret\n";
    exit(1);
}

if (!file_exists($file)) {
    echo "❌ index.php introuvable à : $file\n";
    exit(1);
}

$source = file_get_contents($file);
echo "📄 Fichier lu : " . number_format(strlen($source)) . " caractères, "
   . count(file($file)) . " lignes\n";

// ── Appel Mistral ─────────────────────────────────────────────────────────────
echo "🤖 Envoi à Mistral Large...\n";

$payload = [
    'model'       => $model,
    'temperature' => 0.2,
    'max_tokens'  => 16000,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => implode("\n", [
                'Tu es un expert PHP senior. Tu reçois un fichier PHP complet et tu dois retourner une version améliorée COMPLÈTE.',
                '',
                'RÈGLES ABSOLUES :',
                '1. Retourne UNIQUEMENT le code PHP complet, sans texte avant ni après.',
                '2. Commence toujours par <?php',
                '3. Ne coupe JAMAIS le code — retourne le fichier entier jusqu\'à la dernière ligne.',
                '4. Conserve toutes les fonctionnalités existantes.',
                '5. Incrémente VERSION dans le define().',
            ]),
        ],
        [
            'role'    => 'user',
            'content' => "Objectif : $goal\n\nVoici le code complet de index.php :\n\n```php\n$source\n```",
        ],
    ],
];

$ch = curl_init('https://api.mistral.ai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => 15,
]);

$raw      = curl_exec($ch);
$http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo "❌ Erreur réseau : $curl_err\n";
    exit(1);
}

if ($http >= 400) {
    echo "❌ Erreur API Mistral (HTTP $http) :\n$raw\n";
    exit(1);
}

$json = json_decode($raw, true);
$text = $json['choices'][0]['message']['content'] ?? '';

if (!$text) {
    echo "❌ Réponse vide de Mistral\n";
    exit(1);
}

echo "✅ Réponse reçue : " . number_format(strlen($text)) . " caractères\n";

// ── Extraire le code PHP ──────────────────────────────────────────────────────
$improved = $text;
if (preg_match('/```php\s*([\s\S]+?)\s*```/i', $text, $m)) {
    $improved = trim($m[1]);
} elseif (preg_match('/```\s*([\s\S]+?)\s*```/i', $text, $m) && str_contains($m[1], '<?php')) {
    $improved = trim($m[1]);
} elseif (str_contains($text, '<?php')) {
    $improved = trim(substr($text, strpos($text, '<?php')));
}

if (!str_contains($improved, '<?php')) {
    echo "❌ Code PHP invalide — le fichier n'a pas été modifié\n";
    exit(1);
}

// ── Vérification syntaxique avant d'écraser ──────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'autocode_') . '.php';
file_put_contents($tmp, $improved);
exec("php -l " . escapeshellarg($tmp) . " 2>&1", $lint_out, $lint_code);
unlink($tmp);

if ($lint_code !== 0) {
    echo "❌ Erreur de syntaxe PHP détectée — le fichier original est conservé\n";
    echo implode("\n", $lint_out) . "\n";
    exit(1);
}
echo "✅ Syntaxe PHP valide\n";

// ── Écrire le fichier amélioré ────────────────────────────────────────────────
file_put_contents($file, $improved);
$new_lines = count(file($file));
echo "🌊 index.php amélioré avec succès !\n";
echo "   → " . number_format(strlen($improved)) . " caractères, $new_lines lignes\n";
