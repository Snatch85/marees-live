<?php
/**
 * Marées Loire-Atlantique — Données officielles WorldTides + calculs
 * Version améliorée avec intégration API WorldTides et gestion complète des ports
 */

define('VERSION',    '3.0.0');
define('API_URL',    'https://api.mistral.ai/v1/chat/completions');
define('DB_FILE',    __DIR__ . '/chat.sqlite');
define('MAX_TOKENS', 4096);
define('WORLDTIDES_KEY', 'VOTRE_CLE_ICI'); // À remplacer par l'utilisateur

// ── Modèles disponibles ──────────────────────────────────────────────────────
$MODELS = [
    'mistral-large-latest'  => ['label' => 'Mistral Large',  'desc' => 'Plus intelligent'],
    'mistral-small-latest'  => ['label' => 'Mistral Small',  'desc' => 'Plus rapide'],
    'codestral-latest'      => ['label' => 'Codestral',      'desc' => 'Expert code'],
];

// ── Personnalités ────────────────────────────────────────────────────────────
$PERSONAS = [
    'assistant' => [
        'label'  => 'Assistant général',
        'icon'   => '🤖',
        'prompt' => 'Tu es un assistant IA intelligent, précis et bienveillant. Tu réponds en français par défaut. Tu structures tes réponses avec des titres, listes et code quand c\'est utile.',
    ],
    'dev' => [
        'label'  => 'Développeur PHP',
        'icon'   => '💻',
        'prompt' => 'Tu es un expert PHP/JS/SQL senior. Tu fournis du code propre, commenté et fonctionnel. Tu expliques chaque décision technique. Tu signales les failles de sécurité.',
    ],
    'marin' => [
        'label'  => 'Expert marées',
        'icon'   => '🌊',
        'prompt' => 'Tu es un expert des marées, de la navigation et de la pêche en Loire-Atlantique. Tu connais les ports, coefficients, spots de pêche à pied, et conditions météo marines.',
    ],
    'science' => [
        'label'  => 'Chercheur scientifique',
        'icon'   => '🔬',
        'prompt' => 'Tu es un chercheur biomédicale expert. Tu analyses les études scientifiques, expliques les mécanismes biologiques et cites tes sources. Tu restes factuel et nuancé.',
    ],
];

// ── Ports Loire-Atlantique avec coordonnées GPS exactes ──────────────────────
$PORTS = [
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
];

session_start();

// ── Base de données SQLite ───────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT NOT NULL DEFAULT 'Nouvelle conversation',
            model      TEXT NOT NULL DEFAULT 'mistral-large-latest',
            persona    TEXT NOT NULL DEFAULT 'assistant',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS messages (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            role            TEXT NOT NULL,
            content         TEXT NOT NULL,
            created_at      TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        );
    ");
    return $pdo;
}

// ── Fonction pour récupérer les marées via API WorldTides avec cache ─────────
function fetchTides(float $lat, float $lon, int $days = 3): array {
    if (WORLDTIDES_KEY === 'VOTRE_CLE_ICI') {
        return ['error' => 'Clé API non configurée'];
    }

    $cacheFile = sys_get_temp_dir() . '/tides_' . md5("$lat,$lon,$days") . '_' . date('YmdH') . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 6*3600)) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    $url = "https://www.worldtides.info/api/v3?extremes&heights&date=today&days={$days}&step=1800&datum=LAT&localtime"
         . "&lat={$lat}&lon={$lon}&key=" . WORLDTIDES_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => true
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) {
        return ['error' => 'API WorldTides non disponible', 'http' => $http];
    }

    $data = json_decode($raw, true);
    if (($data['status'] ?? 0) === 200) {
        file_put_contents($cacheFile, $raw);
        return $data;
    }
    return ['error' => 'Données invalides', 'raw' => $raw];
}

// ── Fonction pour calculer les coefficients de marée (fallback) ─────────────
function calculateTideCoefficient(float $lat, float $lon, string $date): array {
    // Calcul sinusoïdal simplifié (approximation)
    $port = null;
    foreach ($GLOBALS['PORTS'] as $name => $data) {
        if (abs($data['lat'] - $lat) < 0.01 && abs($data['lon'] - $lon) < 0.01) {
            $port = $data;
            break;
        }
    }
    if (!$port) return [];

    $ref_marnage = $port['ref_marnage'];
    $coeff = 70; // Coefficient moyen par défaut

    // Génération de données fictives pour 24h (toutes les 30 min)
    $tides = [];
    $base_time = strtotime($date . ' 00:00:00');
    for ($i = 0; $i < 48; $i++) {
        $time = $base_time + $i * 1800;
        $hour = date('H', $time);
        $minute = date('i', $time);
        $t = ($hour * 60 + $minute) / (24*60);
        $height = 2.5 + 1.5 * sin(2 * M_PI * $t) * sin(2 * M_PI * $t * 1.1); // Double sinusoïde
        $tides[] = [
            'dt' => $time,
            'date' => date('Y-m-d\TH:i:sP', $time),
            'height' => round($height, 2)
        ];
    }

    // Détection des extrêmes
    $extremes = [];
    for ($i = 1; $i < count($tides) - 1; $i++) {
        $prev = $tides[$i-1]['height'];
        $curr = $tides[$i]['height'];
        $next = $tides[$i+1]['height'];

        if (($prev < $curr && $curr > $next) || ($prev > $curr && $curr < $next)) {
            $type = ($curr > $prev) ? 'High' : 'Low';
            $extremes[] = [
                'dt' => $tides[$i]['dt'],
                'date' => $tides[$i]['date'],
                'height' => round($curr, 2),
                'type' => $type
            ];
        }
    }

    return [
        'heights' => $tides,
        'extremes' => $extremes,
        'coefficient' => $coeff,
        'port' => $port['lat'] . ',' . $port['lon']
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return floor($diff/60) . ' min';
    if ($diff < 86400)  return floor($diff/3600) . 'h';
    if ($diff < 604800) return floor($diff/86400) . 'j';
    return date('d/m', strtotime($dt));
}

function md(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    // Blocs de code avec langage
    $s = preg_replace_callback('/```(\w*)\n?([\s\S]*?)```/m', function($m) {
        $lang = h($m[1] ?: 'code');
        $code = $m[2];
        return '<div class="code-block"><div class="code-header"><span class="code-lang">' . $lang . '</span>'
             . '<button class="copy-btn" onclick="copyCode(this)">📋 Copier</button></div>'
             . '<pre><code class="lang-' . $lang . '">' . $code . '</code></pre></div>';
    }, $s);
    // Code inline
    $s = preg_replace('/`([^`\n]+)`/', '<code class="inline-code">$1</code>', $s);
    // Titres
    $s = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $s);
    $s = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $s);
    $s = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $s);
    // Gras / italique
    $s = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/',     '<strong>$1</strong>',          $s);
    $s = preg_replace('/\*(.+?)\*/',         '<em>$1</em>',                  $s);
    // Citations
    $s = preg_replace('/^&gt; (.+)$/m', '<blockquote>$1</blockquote>', $s);
    // Listes à puces
    $s = preg_replace('/^[-*•] (.+)$/m', '<li>$1</li>', $s);
    $s = preg_replace('/(<li>[\s\S]*?<\/li>\n?)+/', '<ul>$0</ul>', $s);
    // Listes numérotées
    $s = preg_replace('/^\d+\. (.+)$/m', '<oli>$1</oli>', $s);
    $s = preg_replace('/(<oli>[\s\S]*?<\/oli>\n?)+/', '<ol>$0</ol>', $s);
    $s = str_replace(['<oli>', '</oli>'], ['<li>', '</li>'], $s);
    // Séparateurs
    $s = preg_replace('/^---$/m', '<hr>', $s);
    // Tableaux
    $s = preg_replace_callback('/(\|.+\|\n)+/', function($m) {
        $rows = array_filter(explode("\n", trim($m[0])));
        $html = '<table>';
        foreach ($rows as $i => $row) {
            if (preg_match('/^\|[-| :]+\|$/', $row)) continue;
            $cells = array_slice(explode('|', $row), 1, -1);
            $tag = $i === 0 ? 'th' : 'td';
            $html .= '<tr>' . implode('', array_map(fn($c) => "<{$tag}>" . trim($c) . "</{$tag}>", $cells)) . '</tr>';
        }
        return $html . '</table>';
    }, $s);
    // Paragraphes
    $blocks = preg_split('/\n{2,}/', $s);
    $s = implode("\n", array_map(function($b) {
        $b = trim($b);
        if (!$b) return '';
        if (preg_match('/^<(h[1-3]|ul|ol|blockquote|pre|div|table|hr)/', $b)) return $b;
        return '<p>' . str_replace("\n", '<br>', $b) . '</p>';
    }, $blocks));
    return $s;
}

// ── Actions AJAX / POST ──────────────────────────────────────────────────────
$api_key = $_SESSION['api_key'] ?? '';

// Enregistrer la clé API
if (isset($_POST['set_key'])) {
    $_SESSION['api_key'] = trim($_POST['api_key']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// API JSON
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_GET['api'];

    // Créer une conversation
    if ($act === 'new_conv') {
        $model   = $_POST['model']   ?? 'mistral-large-latest';
        $persona = $_POST['persona'] ?? 'assistant';
        db()->prepare("INSERT INTO conversations (model, persona) VALUES (?,?)")->execute([$model, $persona]);
        $id = db()->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    // Supprimer une conversation
    if ($act === 'del_conv') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare("DELETE FROM conversations WHERE id=?")->execute([$id]);
        db()->prepare("DELETE FROM messages WHERE conversation_id=?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Renommer
    if ($act === 'rename') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = substr(trim($_POST['title'] ?? ''), 0, 80);
        db()->prepare("UPDATE conversations SET title=?, updated_at=datetime('now') WHERE id=?")->execute([$title, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Charger les messages d'une conversation
    if ($act === 'load') {
        $id   = (int)($_GET['id'] ?? 0);
        $conv = db()->prepare("SELECT * FROM conversations WHERE id=?");
        $conv->execute([$id]);
        $conv = $conv->fetch(PDO::FETCH_ASSOC);
        if (!$conv) { echo json_encode(['success' => false]); exit; }
        $msgs = db()->prepare("SELECT * FROM messages WHERE conversation_id=? ORDER BY id");
        $msgs->execute([$id]);
        echo json_encode(['success' => true, 'conv' => $conv, 'messages' => $msgs->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Envoyer un message
    if ($act === 'send') {
        $conv_id = (int)($_POST['conv_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if (!$conv_id || !$content || !$api_key) {
            echo json_encode(['success' => false, 'error' => 'Paramètres manquants ou clé API non configurée']);
            exit;
        }

        // Récupérer la conversation
        $conv = db()->prepare("SELECT * FROM conversations WHERE id=?");
        $conv->execute([$conv_id]);
        $conv = $conv->fetch(PDO::FETCH_ASSOC);
        if (!$conv) { echo json_encode(['success' => false, 'error' => 'Conversation introuvable']); exit; }

        // Sauvegarder le message utilisateur
        db()->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?,?,?)")
            ->execute([$conv_id, 'user', $content]);

        // Auto-titre si premier message
        $count = db()->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id=?");
        $count->execute([$conv_id]);
        if ($count->fetchColumn() <= 1) {
            $title = mb_substr($content, 0, 50);
            db()->prepare("UPDATE conversations SET title=?, updated_at=datetime('now') WHERE id=?")
                ->execute([$title, $conv_id]);
        } else {
            db()->prepare("UPDATE conversations SET updated_at=datetime('now') WHERE id=?")->execute([$conv_id]);
        }

        // Construire les messages pour l'API
        global $MODELS, $PERSONAS;
        $persona     = $PERSONAS[$conv['persona']] ?? $PERSONAS['assistant'];
        $api_messages = [['role' => 'system', 'content' => $persona['prompt']]];
        $history = db()->prepare("SELECT role, content FROM messages WHERE conversation_id=? ORDER BY id");
        $history->execute([$conv_id]);
        foreach ($history->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $api_messages[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        // Appel Mistral
        $payload = json_encode([
            'model'       => $conv['model'],
            'messages'    => $api_messages,
            'max_tokens'  => MAX_TOKENS,
            'temperature' => 0.7,
        ]);
        $ch = curl_init(API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $raw  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($cerr) { echo json_encode(['success' => false, 'error' => 'Réseau : ' . $cerr]); exit; }
        if ($http >= 400) {
            $d = json_decode($raw, true);
            echo json_encode(['success' => false, 'error' => 'API ' . $http . ' : ' . ($d['message'] ?? substr($raw,0,200))]);
            exit;
        }

        $data  = json_decode($raw, true);
        $reply = trim($data['choices'][0]['message']['content'] ?? '');
        if (!$reply) { echo json_encode(['success' => false, 'error' => 'Réponse vide']); exit; }

        // Sauvegarder la réponse
        db()->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?,?,?)")
            ->execute([$conv_id, 'assistant', $reply]);

        echo json_encode(['success' => true, 'reply' => $reply]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    exit;
}

// ── Charger les conversations pour la sidebar ─────────────────────────────────
$conversations = db()->query("SELECT * FROM conversations ORDER BY updated_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// ── Gestion des données de marée ─────────────────────────────────────────────
$current_port = $_COOKIE['marées_port'] ?? 'Saint-Nazaire';
$tide_data = [];
$source_type = 'estimated'; // 'official' ou 'estimated'

if (isset($_GET['port']) && isset($PORTS[$_GET['port']])) {
    $current_port = $_GET['port'];
    setcookie('marées_port', $current_port, time() + 30*24*3600, '/');
}

$port_data = $PORTS[$current_port] ?? reset($PORTS);
$tide_data = fetchTides($port_data['lat'], $port_data['lon'], 3);

if (isset($tide_data['error']) || $tide_data === [] || WORLDTIDES_KEY === 'VOTRE_CLE_ICI') {
    $tide_data = calculateTideCoefficient($port_data['lat'], $port_data['lon'], date('Y-m-d'));
    $source_type = 'estimated';
} else {
    $source_type = 'official';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marées Loire-Atlantique | Données <?= $source_type === 'official' ? 'officielles' : 'estimées' ?></title>
<style>
:root{
  --bg:#0a0e1a;--surface:#121622;--border:#1e2432;--text:#e2e8f0;
  --muted:#94a3b8;--accent:#00d4ff;--accent2:#0077b6;--success:#10b981;
  --warning:#f59e0b;--danger:#ef4444;--r:8px;--shadow:0 4px 12px rgba(0,0,0,.3);
  --font:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  --mono:'JetBrains Mono','Fira Code','Cascadia Code',monospace;
}
html,body{height:100%;margin:0;font-family:var(--font)}
body{background:var(--bg);color:var(--text);display:flex}

/* ━━ HEADER AVERTISSEMENT ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.banner{
  background:<?= $source_type === 'official' ? 'linear-gradient(90deg,#006994,#00d4ff)' : '#facc15' ?>;
  color:#fff;text-align:center;padding:.6rem;font-size:.85rem;font-weight:600;
  position:relative;z-index:100;
}
.banner .source-badge{
  display:inline-block;padding:.2rem .6rem;border-radius:12px;font-size:.7rem;
  margin-left:.6rem;background:rgba(255,255,255,.2);
}

/* ━━ SIDEBAR ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.sidebar{
  width:280px;background:var(--surface);border-right:1px solid var(--border);
  display:flex;flex-direction:column;height:100vh;flex-shrink:0;
}
.sidebar-top{padding:1rem;border-bottom:1px solid var(--border)}
.sidebar-logo{display:flex;align-items:center;gap:.6rem;padding:.5rem 0 1rem}
.sidebar-logo-icon{
  width:32px;height:32px;background:var(--accent);border-radius:8px;
  display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:#0a0e1a;
}
.sidebar-logo span{font-size:.95rem;font-weight:600;color:var(--text)}
.sidebar-logo small{font-size:.65rem;color:var(--muted);display:block;line-height:1}

.port-select{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:var(--r);
  padding:.6rem .9rem;font-family:var(--font);font-size:.85rem;color:var(--text);margin-bottom:1rem}
.port-select:focus{outline:none;border-color:var(--accent)}

.sidebar-section{padding:.5rem .75rem .25rem;font-size:.65rem;font-weight:600;
  color:var(--muted);text-transform:uppercase;letter-spacing:.08em}

.conv-list{flex:1;overflow-y:auto;padding:.25rem .5rem}
.conv-list::-webkit-scrollbar{width:3px}
.conv-list::-webkit-scrollbar-thumb{background:#334155;border-radius:2px}

.conv-item{
  display:flex;align-items:center;gap:.5rem;padding:.5rem .65rem;
  border-radius:var(--r);cursor:pointer;transition:.1s;
  position:relative;group:true;
}
.conv-item:hover{background:#1e2432}
.conv-item.active{background:#334155}
.conv-icon{font-size:.85rem;flex-shrink:0;opacity:.7}
.conv-title{font-size:.8rem;color:#cbd5e1;flex:1;overflow:hidden;
  white-space:nowrap;text-overflow:ellipsis;line-height:1.4}
.conv-time{font-size:.62rem;color:var(--muted);flex-shrink:0}
.conv-del{
  display:none;background:none;border:none;color:var(--muted);cursor:pointer;
  font-size:.75rem;padding:.1rem .3rem;border-radius:4px;flex-shrink:0;
}
.conv-item:hover .conv-del{display:block}
.conv-del:hover{color:#f87171;background:rgba(248,113,113,.1)}

.sidebar-bottom{padding:.75rem;border-top:1px solid var(--border)}
.api-status{
  display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;
  background:#1e2432;border-radius:var(--r);font-size:.75rem;cursor:pointer;transition:.1s;
}
.api-status:hover{background:#334155}
.api-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.api-dot.ok{background:#22c55e;box-shadow:0 0 6px rgba(34,197,94,.4)}
.api-dot.no{background:#ef4444}
.api-label{color:#cbd5e1;flex:1}
.api-edit{color:var(--muted);font-size:.65rem}

/* ━━ MAIN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
.main{flex:1;display:flex;flex-direction:column;height:100vh;overflow:hidden}

/* ── Topbar ── */
.topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:.7rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-shrink:0;
}
.topbar-title{font-size:.9rem;font-weight:600;color:var(--text);flex:1;
  overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
.model-select{
  background:var(--bg);border:1px solid var(--border);border-radius:6px;
  padding:.3rem .6rem;font-size:.78rem;color:var(--muted);font-family:var(--font);cursor:pointer;
}
.persona-select{
  background:var(--bg);border:1px solid var(--border);border-radius:6px;
  padding:.3rem .6rem;font-size:.78rem;color:var(--muted);font-family:var(--font);cursor:pointer;
}

/* ── Clé API popup ── */
.key-popup{
  position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);
  display:flex;align-items:center;justify-content:center;z-index:999;
}
.key-box{
  background:var(--surface);border-radius:12px;padding:2rem;width:440px;
  box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.key-box h2{font-size:1.1rem;margin-bottom:.4rem;color:var(--text)}
.key-box p{font-size:.85rem;color:var(--muted);margin-bottom:1.2rem;line-height:1.5}
.key-input-row{display:flex;gap:.5rem}
.key-input{
  flex:1;border:1px solid var(--border);border-radius:8px;padding:.6rem .9rem;
  font-family:var(--font);font-size:.9rem;color:var(--text);background:var(--bg);
}
.key-input:focus{outline:none;border-color:var(--accent)}
.key-save-btn{
  background:var(--accent);border:none;color:#0a0e1a;padding:.6rem 1.2rem;
  border-radius:8px;font-weight:600;font-size:.88rem;cursor:pointer;font-family:var(--font);
  white-space:nowrap;
}
.key-save-btn:hover{background:var(--accent2)}
.key-box button[style]{margin-top:.8rem;background:none;border:none;color:var(--muted);cursor:pointer;font-size:.85rem}

/* ── Ports marées ── */
.ports-section{padding:1rem 1.5rem}
.port-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  padding:1rem;display:flex;align-items:center;gap:.8rem;margin-bottom:.6rem;
  cursor:pointer;transition:.15s;
}
.port-card:hover{border-color:var(--accent)}
.port-card.active{border-color:var(--accent);background:rgba(0,212,255,.08)}
.port-icon{width:40px;height:40px;background:var(--accent);border-radius:8px;
  display:flex;align-items:center;justify-content:center;font-size:1.2rem}
.port-info h3{font-size:.95rem;margin-bottom:.1rem;color:var(--text)}
.port-info p{font-size:.75rem;color:var(--muted)}

/* ── Cartes marées ── */
.tides-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.2rem;padding:1rem 1.5rem}
.tide-card{
  background:var(--surface);border:1px solid var(--border);border-radius:var(--r);
  padding:1.2rem;box-shadow:var(--shadow);
}
.tide-card-header{
  display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;
}
.tide-card-title{
  font-size:1.1rem;font-weight:600;color:var(--text);
}
.tide-card-date{font-size:.75rem;color:var(--muted)}
.tide-extremes{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1.2rem}
.extreme-item{
  background:rgba(0,212,255,.1);border:1px solid var(--accent);border-radius:var(--r);
  padding:.6rem;text-align:center;
}
.extreme-icon{font-size:1.4rem;margin-bottom:.3rem}
.extreme-time{font-size:.85rem;font-weight:600;color:var(--text)}
.extreme-type{font-size:.7rem;color:var(--muted)}
.extreme-height{font-size:.9rem;font-weight:700;color:var(--accent)}

/* Courbe SVG */
.tide-svg-container{margin-top:1rem;height:200px;background:linear-gradient(to bottom,#0a0e1a 0%,#121622 100%);border-radius:var(--r)}
.tide-svg{width:100%;height:100%;display:block}

/* Pêche à pied */
.fishing-section{padding:1rem 1.5rem}
.fishing-badge{
  display:inline-block;padding:.4rem .8rem;border-radius:12px;font-size:.75rem;
  font-weight:600;margin-bottom:.6rem;
}
.fishing-badge.green{background:#10b981;color:#fff}
.fishing-badge.orange{background:#f59e0b;color:#fff}
.fishing-badge.gray{background:#64748b;color:#fff}
.fishing-info{font-size:.85rem;color:var(--muted);line-height:1.6}

/* Welcome */
.welcome{
  flex:1;display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:1.5rem;padding:2rem;text-align:center;
}
.welcome-logo{
  width:56px;height:56px;background:var(--accent);border-radius:14px;
  display:flex;align-items:center;justify-content:center;font-size:1.6rem;
  box-shadow:0 8px 24px rgba(0,212,255,.3);
}
.welcome h1{font-size:1.6rem;font-weight:700;color:var(--text)}
.welcome p{font-size:.9rem;color:var(--muted);max-width:420px;line-height:1.6}
.suggestions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:center;max-width:600px}
.suggestion{
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  padding:.65rem 1rem;font-size:.82rem;color:var(--text);cursor:pointer;
  transition:.15s;text-align:left;display:flex;align-items:center;gap:.5rem;
  box-shadow:0 2px 8px rgba(0,0,0,.2);
}
.suggestion:hover{border-color:var(--accent);background:rgba(0,212,255,.08);transform:translateY(-1px)}

/* Chat */
.chat-area{flex:1;overflow-y:auto;padding:1.5rem 0;scroll-behavior:smooth}
.chat-area::-webkit-scrollbar{width:4px}
.chat-area::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

.msg-group{max-width:760px;margin:0 auto;padding:.25rem 1.5rem}

.msg{display:flex;gap:1rem;padding:.5rem 0;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}

.msg-avatar{
  width:32px;height:32px;border-radius:8px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:.85rem;margin-top:2px;
}
.msg-avatar.user{background:#334155;color:var(--text)}
.msg-avatar.ai{background:var(--accent);color:#0a0e1a}

.msg-content{flex:1;min-width:0}
.msg-name{font-size:.75rem;font-weight:600;color:var(--muted);margin-bottom:.3rem}
.msg-text{font-size:.9rem;line-height:1.75;color:var(--text)}

/* Markdown */
.msg-text h1{font-size:1.2rem;font-weight:700;margin:1rem 0 .4rem;color:var(--text)}
.msg-text h2{font-size:1.05rem;font-weight:700;margin:.9rem 0 .35rem;color:var(--text);
  border-bottom:1px solid var(--border);padding-bottom:.25rem}
.msg-text h3{font-size:.95rem;font-weight:700;margin:.8rem 0 .3rem;color:var(--text)}
.msg-text p{margin-bottom:.7rem}
.msg-text p:last-child{margin-bottom:0}
.msg-text ul,.msg-text ol{padding-left:1.5rem;margin:.4rem 0 .7rem}
.msg-text li{margin-bottom:.2rem}
.msg-text strong{font-weight:700}
.msg-text em{font-style:italic;color:var(--muted)}
.msg-text hr{border:none;border-top:1px solid var(--border);margin:.8rem 0}
.msg-text blockquote{
  border-left:3px solid var(--accent);padding:.4rem .9rem;
  background:rgba(0,212,255,.08);border-radius:0 6px 6px 0;margin:.5rem 0;
  color:var(--muted);font-style:italic;
}
.msg-text table{width:100%;border-collapse:collapse;margin:.6rem 0;font-size:.85rem}
.msg-text th{background:var(--bg);border:1px solid var(--border);padding:.4rem .7rem;
  font-weight:600;text-align:left;color:var(--text)}
.msg-text td{border:1px solid var(--border);padding:.35rem .7rem;color:var(--text)}
.msg-text tr:nth-child(even) td{background:#1e2432}

.inline-code{
  font-family:var(--mono);font-size:.82em;background:#1e2432;
  border:1px solid var(--border);padding:.1rem .35rem;border-radius:4px;color:var(--accent);
}

.code-block{border-radius:10px;overflow:hidden;margin:.6rem 0;border:1px solid #313244}
.code-header{
  background:#181825;display:flex;align-items:center;
  padding:.45rem .9rem;border-bottom:1px solid #313244;
}
.code-lang{font-family:var(--mono);font-size:.7rem;color:#6c7086;text-transform:uppercase;letter-spacing:.06em;flex:1}
.copy-btn{
  background:none;border:1px solid #45475a;color:#6c7086;
  border-radius:5px;padding:.2rem .55rem;font-size:.68rem;cursor:pointer;
  font-family:var(--font);transition:.15s;
}
.copy-btn:hover{border-color:#00d4ff;color:#00d4ff}
.code-block pre{background:var(--surface);padding:.9rem 1rem;overflow-x:auto;margin:0}
.code-block pre code{
  font-family:var(--mono);font-size:.82rem;color:var(--text);
  line-height:1.65;white-space:pre;
}

/* Thinking */
.thinking-dots{display:inline-flex;gap:4px;padding:.4rem 0}
.thinking-dots span{
  width:7px;height:7px;background:var(--accent);border-radius:50%;opacity:.4;
  animation:dot .9s infinite;
}
.thinking-dots span:nth-child(2){animation-delay:.2s}
.thinking-dots span:nth-child(3){animation-delay:.4s}
@keyframes dot{0%,60%,100%{opacity:.4;transform:scale(1)}30%{opacity:1;transform:scale(1.2)}}

/* Input */
.input-zone{
  border-top:1px solid var(--border);background:var(--surface);
  padding:1rem 1.5rem 1.2rem;flex-shrink:0;
}
.input-inner{
  max-width:760px;margin:0 auto;
  background:var(--bg);border:1.5px solid var(--border);
  border-radius:12px;transition:.15s;
  box-shadow:0 2px 8px rgba(0,0,0,.1);
}
.input-inner:focus-within{border-color:var(--accent);box-shadow:0 2px 12px rgba(0,212,255,.2)}
.input-inner textarea{
  width:100%;background:none;border:none;
  padding:.85rem 1rem .4rem;color:var(--text);font-family:var(--font);
  font-size:.9rem;resize:none;outline:none;line-height:1.5;
  min-height:52px;max-height:200px;display:block;
}
.input-inner textarea::placeholder{color:#64748b}
.input-toolbar{
  display:flex;align-items:center;padding:.4rem .7rem;gap:.5rem;
}
.input-hint-txt{font-size:.7rem;color:#64748b;flex:1}
.send-btn{
  background:var(--accent);border:none;color:#0a0e1a;
  width:34px;height:34px;border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:1rem;transition:.15s;flex-shrink:0;
}
.send-btn:hover:not(:disabled){background:var(--accent2);transform:translateY(-1px)}
.send-btn:disabled{opacity:.35;cursor:not-allowed;transform:none}

@media(max-width:768px){
  .sidebar{display:none}
  .msg-group{padding:.25rem 1rem}
  .tides-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<!-- ━━ BANNER AVERTISSEMENT ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="banner">
  Données <?= $source_type === 'official' ? 'officielles WorldTides' : 'estimées (sinusoïdal)' ?>
  <span class="source-badge"><?= $source_type === 'official' ? '✅ API réelle' : '⚠️ Données calculées' ?></span>
</div>

<!-- ━━ SIDEBAR ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<nav class="sidebar">
  <div class="sidebar-top">
    <div class="sidebar-logo">
      <div class="sidebar-logo-icon">🌊</div>
      <div><span>Marées 44</span><small>Loire-Atlantique v<?= VERSION ?></small></div>
    </div>
    <select class="port-select" id="portSelect" onchange="changePort(this.value)">
      <?php foreach ($PORTS as $name => $data): ?>
      <option value="<?= h($name) ?>" <?= $name === $current_port ? 'selected' : '' ?>>
        <?= h($name) ?> (<?= h($data['ref_marnage']) ?>m)
      </option>
      <?php endforeach; ?>
    </select>
    <button class="new-chat-btn" onclick="newConv()" style="margin-top:.5rem">
      ✏️ Nouvelle discussion
    </button>
  </div>

  <div style="flex:1;overflow:hidden;display:flex;flex-direction:column">
    <?php if (!empty($conversations)): ?>
    <div class="sidebar-section">Conversations</div>
    <?php endif; ?>
    <div class="conv-list" id="convList">
      <?php foreach ($conversations as $c): ?>
      <div class="conv-item" id="ci-<?= $c['id'] ?>" onclick="loadConv(<?= $c['id'] ?>)">
        <span class="conv-icon">💬</span>
        <span class="conv-title"><?= h($c['title']) ?></span>
        <span class="conv-time"><?= timeAgo($c['updated_at']) ?></span>
        <button class="conv-del" onclick="event.stopPropagation();delConv(<?= $c['id'] ?>)" title="Supprimer">✕</button>
      </div>
      <?php endforeach; ?>
      <?php if (empty($conversations)): ?>
      <div style="padding:1.5rem .75rem;font-size:.78rem;color:var(--muted);text-align:center;line-height:1.6">
        Aucune discussion.<br>Cliquez sur « Nouvelle discussion »
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="sidebar-bottom">
    <div class="api-status" onclick="showKeyPopup()">
      <div class="api-dot <?= $api_key ? 'ok' : 'no' ?>"></div>
      <span class="api-label"><?= $api_key ? 'Clé API Mistral configurée' : 'Clé API Mistral manquante' ?></span>
      <span class="api-edit">✏️</span>
    </div>
  </div>
</nav>

<!-- ━━ MAIN ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar" id="topbar">
    <div class="topbar-title" id="topbarTitle">Marées <?= h($current_port) ?></div>
    <select class="model-select" id="modelSelect" onchange="updateConvModel()">
      <?php foreach ($MODELS as $k => $m): ?>
      <option value="<?= h($k) ?>"><?= h($m['label']) ?> — <?= h($m['desc']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="persona-select" id="personaSelect" onchange="updateConvPersona()">
      <?php foreach ($PERSONAS as $k => $p): ?>
      <option value="<?= h($k) ?>"><?= h($p['icon']) ?> <?= h($p['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Section ports -->
  <div class="ports-section">
    <h2 style="margin-bottom:.8rem;font-size:1rem;color:var(--text)">Ports de Loire-Atlantique</h2>
    <?php foreach ($PORTS as $name => $data): ?>
    <a href="?port=<?= urlencode($name) ?>" class="port-card <?= $name === $current_port ? 'active' : '' ?>">
      <div class="port-icon">🚢</div>
      <div class="port-info">
        <h3><?= h($name) ?></h3>
        <p><?= h($data['ref_marnage']) ?>m de marnage de référence</p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Cartes marées -->
  <div class="tides-grid" id="tidesGrid">
    <?php for ($day = 0; $day < 3; $day++): ?>
    <?php
    $date = date('Y-m-d', strtotime("+$day days"));
    $day_tides = array_filter($tide_data['heights'] ?? [], function($h) use ($date) {
        return substr($h['date'], 0, 10) === $date;
    });
    $day_extremes = array_filter($tide_data['extremes'] ?? [], function($e) use ($date) {
        return substr($e['date'], 0, 10) === $date;
    });
    $max_height = max(array_column($day_tides, 'height')) ?? 5;
    $min_height = min(array_column($day_tides, 'height')) ?? 0;
    ?>
    <div class="tide-card">
      <div class="tide-card-header">
        <div>
          <div class="tide-card-title"><?= h(ucfirst(strftime('%A', strtotime($date)))) ?></div>
          <div class="tide-card-date"><?= h(date('d/m/Y', strtotime($date))) ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:.75rem;color:var(--muted)">Marnage : <?= h($port_data['ref_marnage']) ?>m</div>
          <?php if (isset($tide_data['coefficient'])): ?>
          <div style="font-size:.9rem;font-weight:700;color:var(--accent)">Coeff <?= h($tide_data['coefficient']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="tide-extremes">
        <?php foreach ($day_extremes as $extreme): ?>
        <div class="extreme-item">
          <div class="extreme-icon"><?= $extreme['type'] === 'High' ? '↑' : '↓' ?></div>
          <div class="extreme-time"><?= date('H\\hi', $extreme['dt']) ?></div>
          <div class="extreme-type"><?= $extreme['type'] === 'High' ? 'Haute Mer' : 'Basse Mer' ?></div>
          <div class="extreme-height"><?= h($extreme['height']) ?> m</div>
          <?php if ($extreme['type'] === 'Low' && isset($tide_data['coefficient'])): ?>
          <div style="font-size:.65rem;color:var(--muted);margin-top:.3rem">
            Coeff <?= h($tide_data['coefficient']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Courbe SVG -->
      <div class="tide-svg-container">
        <svg class="tide-svg" viewBox="0 0 400 <?= $max_height * 40 + 20 ?>" preserveAspectRatio="none">
          <defs>
            <linearGradient id="tideGradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stop-color="#006994" />
              <stop offset="100%" stop-color="#00d4ff" />
            </linearGradient>
          </defs>
          <!-- Axe X (temps) -->
          <line x1="0" y1="0" x2="400" y2="0" stroke="#334155" stroke-width="1" />
          <!-- Axe Y (hauteur) -->
          <line x1="0" y1="0" x2="0" y2="<?= $max_height * 40 + 20 ?>" stroke="#334155" stroke-width="1" />
          <!-- Graduations Y -->
          <?php for ($h = 0; $h <= ceil($max_height) + 0.5; $h += 0.5): ?>
          <text x="-5" y="<?= ($max_height - $h) * 40 + 10 ?>" fill="#64748b" font-size="8"><?= h($h) ?>m</text>
          <line x1="0" y1="<?= ($max_height - $h) * 40 ?>" x2="400" y2="<?= ($max_height - $h) * 40 ?>" stroke="#334155" stroke-width="0.5" />
          <?php endfor; ?>
          <!-- Courbe -->
          <path d="
            <?php
            $points = [];
            foreach ($day_tides as $tide) {
                $x = ($tide['dt'] - strtotime($date)) / 1800 * 2;
                $y = ($max_height - $tide['height']) * 40;
                $points[] = "$x,$y";
            }
            echo 'M ' . implode(' L ', $points);
            ?>
          " fill="url(#tideGradient)" opacity="0.7" />
          <!-- Points extrêmes -->
          <?php foreach ($day_extremes as $extreme): ?>
          <?php
          $x = ($extreme['dt'] - strtotime($date)) / 1800 * 2;
          $y = ($max_height - $extreme['height']) * 40;
          ?>
          <circle cx="<?= $x ?>" cy="<?= $y ?>" r="4" fill="#00d4ff" />
          <text x="<?= $x ?>" y="<?= $y - 8 ?>" text-anchor="middle" fill="#fff" font-size="9"><?= h($extreme['height']) ?>m</text>
          <?php endforeach; ?>
        </svg>
      </div>

      <!-- Pêche à pied -->
      <?php
      $fishing_coeff = $tide_data['coefficient'] ?? 70;
      $fishing_badge = 'gray';
      if ($fishing_coeff > 95) $fishing_badge = 'green';
      elseif ($fishing_coeff >= 70) $fishing_badge = 'orange';
      ?>
      <?php if ($fishing_coeff >= 70): ?>
      <div class="fishing-section">
        <span class="fishing-badge <?= $fishing_badge ?>">
          🎣 Pêche à pied recommandée (coeff <?= h($fishing_coeff) ?>)
        </span>
        <div class="fishing-info">
          Créneau idéal : 2h avant BM jusqu'à 2h après BM.<br>
          <?php
          $low_tides = array_filter($day_extremes, fn($e) => $e['type'] === 'Low');
          if (!empty($low_tides)) {
              $first_low = reset($low_tides);
              $start = date('H\\hi', max($first_low['dt'] - 2*3600, strtotime($date)));
              $end = date('H\\hi', min($first_low['dt'] + 2*3600, strtotime($date . ' 23:59:59')));
              echo "Créneau pêche : $start → $end";
          }
          ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>

  <!-- Zone chat ou welcome -->
  <div id="welcomeScreen" class="welcome">
    <div class="welcome-logo">🌊</div>
    <h1>Marées Loire-Atlantique</h1>
    <p>Consultez les horaires et hauteurs de marée pour <?= h($current_port) ?>.<br>
    Données <?= $source_type === 'official' ? 'officielles' : 'estimées' ?> mises à jour <?= $source_type === 'official' ? 'via WorldTides' : 'par calcul sinusoïdal' ?>.</p>
    <div class="suggestions">
      <div class="suggestion" onclick="quickStart('Quels sont les meilleurs spots de pêche à pied à <?= h($current_port) ?> ?')">🦀 <div><strong>Pêche à pied</strong><br><small><?= h($current_port) ?></small></div></div>
      <div class="suggestion" onclick="quickStart('Explique-moi comment lire une carte des marées')">📚 <div><strong>Lire une carte</strong><br><small>Guide pratique</small></div></div>
      <div class="suggestion" onclick="quickStart('Quelle est la hauteur d\\'eau idéale pour naviguer à <?= h($current_port) ?> aujourd\\'hui ?')">⛵ <div><strong>Navigation</strong><br><small>Conditions actuelles</small></div></div>
      <div class="suggestion" onclick="quickStart('Quels sont les coefficients de marée pour les 3 prochains jours ?')">📊 <div><strong>Coefficients</strong><br><small>3 jours à venir</small></div></div>
    </div>
  </div>

  <div id="chatScreen" style="display:none;flex:1;overflow:hidden;display:none;flex-direction:column">
    <div class="chat-area" id="chatArea">
      <div class="msg-group" id="msgContainer"></div>
    </div>
  </div>

  <!-- Input -->
  <div class="input-zone">
    <div class="input-inner">
      <textarea id="msgInput" rows="1"
        placeholder="Envoyer un message… (Entrée pour envoyer, Maj+Entrée pour nouvelle ligne)"
        onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
      <div class="input-toolbar">
        <span class="input-hint-txt" id="convInfo">Crée ou sélectionne une discussion</span>
        <button class="send-btn" id="sendBtn" onclick="sendMsg()" disabled title="Envoyer (Entrée)">➤</button>
      </div>
    </div>
  </div>
</div>

<!-- ━━ POPUP CLÉ API ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="key-popup" id="keyPopup" style="display:<?= $api_key ? 'none' : 'flex' ?>">
  <div class="key-box">
    <h2>🔑 Configurer la clé API Mistral</h2>
    <p>Colle ta clé API Mistral pour activer l'assistant IA. Elle sera sauvegardée en session sur ton PC local.</p>
    <form method="post" class="key-input-row">
      <input type="password" name="api_key" class="key-input" id="keyInput"
        placeholder="Colle ta clé ici..." autocomplete="off">
      <button type="submit" name="set_key" value="1" class="key-save-btn">Sauvegarder</button>
    </form>
    <?php if ($api_key): ?>
    <button onclick="hideKeyPopup()" style="margin-top:.8rem;background:none;border:none;color:var(--muted);cursor:pointer;font-size:.85rem">Annuler</button>
    <?php endif; ?>
  </div>
</div>

<script>
const API_KEY_SET = <?= $api_key ? 'true' : 'false' ?>;
let currentConvId = null;
let sending = false;

// ── Changement de port ───────────────────────────────────────────────────────
function changePort(port) {
    window.location.href = '?port=' + encodeURIComponent(port);
}

// ── Popup clé API ────────────────────────────────────────────────────────────
function showKeyPopup() { document.getElementById('keyPopup').style.display='flex'; }
function hideKeyPopup() { document.getElementById('keyPopup').style.display='none'; }

// ── Nouvelle conversation ────────────────────────────────────────────────────
async function newConv() {
    const model   = document.getElementById('modelSelect').value;
    const persona = document.getElementById('personaSelect').value;
    const r = await fetch('?api=new_conv', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `model=${encodeURIComponent(model)}&persona=${encodeURIComponent(persona)}`
    });
    const d = await r.json();
    if (d.success) {
        addConvToSidebar(d.id, 'Nouvelle discussion');
        await loadConv(d.id);
    }
}

function addConvToSidebar(id, title) {
    const list = document.getElementById('convList');
    const empty = list.querySelector('[style]');
    if (empty) empty.remove();
    const el = document.createElement('div');
    el.className = 'conv-item';
    el.id = 'ci-' + id;
    el.onclick = () => loadConv(id);
    el.innerHTML = `<span class="conv-icon">💬</span>
      <span class="conv-title">${esc(title)}</span>
      <span class="conv-time">À l'instant</span>
      <button class="conv-del" onclick="event.stopPropagation();delConv(${id})" title="Supprimer">✕</button>`;
    list.prepend(el);
}

// ── Charger une conversation ─────────────────────────────────────────────────
async function loadConv(id) {
    const r = await fetch(`?api=load&id=${id}`);
    const d = await r.json();
    if (!d.success) return;

    currentConvId = id;

    // Activer dans la sidebar
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    const ci = document.getElementById('ci-' + id);
    if (ci) ci.classList.add('active');

    // Topbar
    document.getElementById('topbarTitle').textContent = d.conv.title;
    document.getElementById('modelSelect').value   = d.conv.model   || 'mistral-large-latest';
    document.getElementById('personaSelect').value = d.conv.persona || 'assistant';

    // Afficher les messages
    document.getElementById('welcomeScreen').style.display = 'none';
    const cs = document.getElementById('chatScreen');
    cs.style.display = 'flex';
    const container = document.getElementById('msgContainer');
    container.innerHTML = '';
    for (const m of d.messages) appendMessage(m.role, m.content, false);

    // Input
    document.getElementById('convInfo').textContent = d.conv.model;
    document.getElementById('sendBtn').disabled = !API_KEY_SET;
    document.getElementById('msgInput').focus();

    scrollBottom();
}

// ── Supprimer une conversation ───────────────────────────────────────────────
async function delConv(id) {
    if (!confirm('Supprimer cette discussion ?')) return;
    await fetch('?api=del_conv', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`id=${id}`
    });
    const el = document.getElementById('ci-' + id);
    if (el) el.remove();
    if (currentConvId === id) {
        currentConvId = null;
        document.getElementById('chatScreen').style.display = 'none';
        document.getElementById('welcomeScreen').style.display = 'flex';
        document.getElementById('topbarTitle').textContent = 'Marées Loire-Atlantique';
        document.getElementById('sendBtn').disabled = true;
    }
}

// ── Envoyer un message ───────────────────────────────────────────────────────
async function sendMsg() {
    if (sending || !currentConvId) return;
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if (!msg) return;

    sending = true;
    input.value = '';
    input.style.height = 'auto';
    document.getElementById('sendBtn').disabled = true;

    appendMessage('user', msg);

    // Indicateur "en train de réfléchir"
    const thinkId = 'think-' + Date.now();
    appendThinking(thinkId);
    scrollBottom();

    try {
        const r = await fetch('?api=send', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`conv_id=${currentConvId}&content=${encodeURIComponent(msg)}`
        });
        const d = await r.json();
        removeThinking(thinkId);

        if (d.success) {
            appendMessage('assistant', d.reply);
            // Mettre à jour le titre dans la sidebar
            const ci = document.getElementById('ci-' + currentConvId);
            if (ci) {
                const titleEl = ci.querySelector('.conv-title');
                if (titleEl && titleEl.textContent === 'Nouvelle discussion') {
                    titleEl.textContent = msg.length > 40 ? msg.slice(0,40)+'…' : msg;
                    document.getElementById('topbarTitle').textContent = titleEl.textContent;
                }
                const timeEl = ci.querySelector('.conv-time');
                if (timeEl) timeEl.textContent = 'À l\'instant';
            }
        } else {
            appendError(d.error || 'Erreur inconnue');
        }
    } catch(e) {
        removeThinking(thinkId);
        appendError('Erreur réseau : ' + e.message);
    }

    sending = false;
    document.getElementById('sendBtn').disabled = !API_KEY_SET;
    scrollBottom();
}

// ── Afficher un message ──────────────────────────────────────────────────────
function appendMessage(role, content, scroll=true) {
    const container = document.getElementById('msgContainer');
    const div = document.createElement('div');
    div.className = 'msg';
    const name   = role === 'user' ? 'Vous' : 'ClaudeLocal';
    const avatar = role === 'user' ? '👤' : 'C';
    const avClass = role === 'user' ? 'user' : 'ai';
    const rendered = role === 'user'
        ? `<p>${esc(content).replace(/\n/g,'<br>')}</p>`
        : renderMd(content);

    div.innerHTML = `
      <div class="msg-avatar ${avClass}">${avatar}</div>
      <div class="msg-content">
        <div class="msg-name">${name}</div>
        <div class="msg-text">${rendered}</div>
      </div>`;
    container.appendChild(div);
    if (scroll) scrollBottom();
}

function appendThinking(id) {
    const container = document.getElementById('msgContainer');
    const div = document.createElement('div');
    div.className = 'msg'; div.id = id;
    div.innerHTML = `<div class="msg-avatar ai">C</div>
      <div class="msg-content">
        <div class="msg-name">ClaudeLocal</div>
        <div class="msg-text"><div class="thinking-dots"><span></span><span></span><span></span></div></div>
      </div>`;
    container.appendChild(div);
}
function removeThinking(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}
function appendError(msg) {
    const container = document.getElementById('msgContainer');
    const div = document.createElement('div');
    div.style.cssText = 'max-width:760px;margin:0 auto;padding:.5rem 1.5rem';