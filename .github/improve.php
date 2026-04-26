<?php
/**
 * Script d'amélioration automatique — GitHub Actions
 * Appelé chaque nuit par le workflow automarees.yml
 */

$api_key = getenv('MISTRAL_API_KEY');
$file    = __DIR__ . '/../index.php';
$goal    = 'Améliore le design et les fonctionnalités du site de marées en Loire-Atlantique. '
         . 'Rends-le plus beau, plus professionnel et plus utile. '
         . 'Ajoute des fonctionnalités pertinentes pour les marins, pêcheurs et touristes. '
         . 'Conserve toutes les fonctionnalités existantes. '
         . 'Incrémente le numéro de VERSION.';

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
