<?php
/**
 * MaréesLive — Horaires des marées en France
 * ─────────────────────────────────────────────────────────────────────────────
 * v3.7.0 — Intégration API WorldTides pour des données officielles
 */

define('VERSION',   '3.7.0');
define('SITE_NAME', 'MaréesLive');
define('WORLDTIDES_KEY', 'VOTRE_CLE_ICI'); // Remplacez par votre clé API WorldTides

$JOURS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$MOIS  = ['jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'];

// ── Ports Loire-Atlantique avec coordonnées exactes et marnage de référence ──
$PORTS = [
    'saint-nazaire' => [
        'name'       => 'Saint-Nazaire',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🚢',
        'lat'        => 47.2706,
        'lon'        => -2.2132,
        'ref_marnage' => 5.10,
        'range_low'  => 1.5,
        'range_high' => 9.5,
        'desc'       => 'Port maritime important',
    ],
    'la-baule' => [
        'name'       => 'La Baule',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🏖️',
        'lat'        => 47.2889,
        'lon'        => -2.3889,
        'ref_marnage' => 4.90,
        'range_low'  => 1.3,
        'range_high' => 8.8,
        'desc'       => 'Station balnéaire renommée',
    ],
    'pornichet' => [
        'name'       => 'Pornichet',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🏝️',
        'lat'        => 47.2658,
        'lon'        => -2.3397,
        'ref_marnage' => 4.90,
        'range_low'  => 1.4,
        'range_high' => 9.1,
        'desc'       => 'Ville balnéaire avec plage de sable fin',
    ],
    'le-croisic' => [
        'name'       => 'Le Croisic',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.2950,
        'lon'        => -2.5136,
        'ref_marnage' => 4.80,
        'range_low'  => 1.2,
        'range_high' => 8.5,
        'desc'       => 'Port pittoresque avec vue sur l\'île de Noirmoutier',
    ],
    'la-turballe' => [
        'name'       => 'La Turballe',
        'region'     => 'Loire-Atlantique',
        'icon'       => '⚓',
        'lat'        => 47.3489,
        'lon'        => -2.5158,
        'ref_marnage' => 4.75,
        'range_low'  => 1.1,
        'range_high' => 8.2,
        'desc'       => 'Port de pêche et de plaisance',
    ],
    'piriac-sur-mer' => [
        'name'       => 'Piriac-sur-Mer',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.3808,
        'lon'        => -2.5447,
        'ref_marnage' => 4.60,
        'range_low'  => 1.9,
        'range_high' => 10.8,
        'desc'       => 'Port de pêche avec vue sur l\'estuaire',
    ],
    'pornic' => [
        'name'       => 'Pornic',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.1111,
        'lon'        => -2.1011,
        'ref_marnage' => 4.50,
        'range_low'  => 1.7,
        'range_high' => 10.2,
        'desc'       => 'Port de pêche traditionnel',
    ],
    'saint-brevin' => [
        'name'       => 'Saint-Brévin',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌳',
        'lat'        => 47.2439,
        'lon'        => -2.1611,
        'ref_marnage' => 5.00,
        'range_low'  => 1.6,
        'range_high' => 9.8,
        'desc'       => 'Village de pêcheurs avec vue sur l\'estuaire',
    ],
    'noirmoutier' => [
        'name'       => 'Noirmoutier',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🏝️',
        'lat'        => 47.0003,
        'lon'        => -2.2508,
        'ref_marnage' => 4.20,
        'range_low'  => 1.0,
        'range_high' => 7.2,
        'desc'       => 'Île aux 80 km de côtes',
    ],
    "l-herbaudiere" => [
        'name'       => "L'Herbaudière",
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.0217,
        'lon'        => -2.3036,
        'ref_marnage' => 4.10,
        'range_low'  => 1.1,
        'range_high' => 7.5,
        'desc'       => 'Port de pêche traditionnel',
    ],
];

// ── Sélection du port ─────────────────────────────────────────────────────────
$port_key = $_GET['port'] ?? 'le-croisic';
if (!array_key_exists($port_key, $PORTS)) $port_key = 'le-croisic';
$port = $PORTS[$port_key];

// ── Fonctions utilitaires ─────────────────────────────────────────────────────
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function coeffClass(int $c): string {
    if ($c >= 100) return 'vive';
    if ($c >= 70)  return 'fort';
    if ($c >= 45)  return 'moyen';
    return 'morte';
}
function coeffLabel(int $c): string {
    if ($c >= 100) return 'Vives eaux';
    if ($c >= 70)  return 'Fort';
    if ($c >= 45)  return 'Moyen';
    return 'Mortes eaux';
}

// ── Fonctions de calcul et API ────────────────────────────────────────────────
function fetchWorldTidesData(float $lat, float $lon, int $days = 3): array {
    global $JOURS, $MOIS;
    global $WORLDTIDES_KEY;

    // Vérification de la clé API
    if ($WORLDTIDES_KEY === 'VOTRE_CLE_ICI') {
        return ['status' => 'error', 'message' => 'Clé API WorldTides non configurée'];
    }

    $cacheFile = sys_get_temp_dir() . '/tides_' . md5("$lat,$lon,$days") . '_' . date('YmdH') . '.json';
    $cacheTime = 6 * 3600; // Cache de 6 heures

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (isset($data['status']) && $data['status'] === 200) {
            return $data;
        }
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['status' => 'error', 'message' => 'Erreur API WorldTides: HTTP ' . $httpCode];
    }

    $data = json_decode($raw, true);
    if (($data['status'] ?? 0) !== 200) {
        return ['status' => 'error', 'message' => 'Erreur API WorldTides: ' . ($data['message'] ?? 'Réponse invalide')];
    }

    file_put_contents($cacheFile, $raw);
    return $data;
}

function processTideData(array $apiData, array $port): array {
    global $JOURS, $MOIS;
    $days = [];
    $today_ts = mktime(0, 0, 0);

    if (isset($apiData['extremes'])) {
        $tides = $apiData['extremes'];
        $current_day = null;
        $last_high = null;

        foreach ($tides as $tide) {
            $ts = $tide['dt'];
            $day_start = mktime(0, 0, 0, date('n', $ts), date('j', $ts), date('Y', $ts));

            if ($current_day === null || $day_start !== $current_day) {
                $dow = (int) date('w', $day_start);
                $day_n = (int) date('j', $day_start);
                $mon_n = (int) date('n', $day_start) - 1;
                $label = match((int)($day_start - $today_ts) / 86400) {
                    0 => "Aujourd'hui",
                    1 => 'Demain',
                    default => $JOURS[$dow] . ' ' . $day_n . ' ' . $MOIS[$mon_n],
                };

                if ($current_day !== null) {
                    $days[] = $current_day_data;
                }

                $current_day = $day_start;
                $current_day_data = [
                    'ts' => $day_start,
                    'label' => $label,
                    'coeff' => isset($tide['coefficient']) ? (int)$tide['coefficient'] : 70,
                    'tides' => []
                ];
            }

            $tide_entry = [
                'ts' => $ts,
                'time' => date('H:i', $ts),
                'height' => round($tide['height'], 2),
                'type' => $tide['type'] === 'High' ? 'high' : 'low',
                'coeff' => isset($tide['coefficient']) ? (int)$tide['coefficient'] : 70
            ];

            // Calcul du coefficient entre deux marées
            if ($tide_entry['type'] === 'low' && $last_high !== null) {
                $coeff = calculateCoefficient($last_high['height'], $tide_entry['height'], $port['ref_marnage']);
                $tide_entry['coeff'] = $coeff;
            }

            if ($tide_entry['type'] === 'high') {
                $last_high = $tide_entry;
            }

            $current_day_data['tides'][] = $tide_entry;
        }

        if ($current_day !== null) {
            $days[] = $current_day_data;
        }
    }

    // Compléter avec des données simulées si l'API échoue
    if (empty($days)) {
        for ($d = 0; $d < 7; $d++) {
            $ts = $today_ts + $d * 86400;
            $dow = (int) date('w', $ts);
            $day_n = (int) date('j', $ts);
            $mon_n = (int) date('n', $ts) - 1;
            $label = match($d) {
                0 => "Aujourd'hui",
                1 => 'Demain',
                default => $JOURS[$dow] . ' ' . $day_n . ' ' . $MOIS[$mon_n],
            };

            $days[] = [
                'ts' => $ts,
                'label' => $label,
                'coeff' => dayCoeff($ts, $port),
                'tides' => dailyTides($ts, $port)
            ];
        }
    }

    return $days;
}

function calculateCoefficient(float $high, float $low, float $ref_marnage): int {
    $marnage = $high - $low;
    $coeff = round(($marnage / $ref_marnage) * 95);
    return max(20, min(120, $coeff));
}

// Hauteur de l'eau à un instant donné (modèle sinusoïdal simplifié)
function tideAt(int $ts, array $p): float {
    $T = 44700;
    $phi = fmod($ts + ($p['phase'] ?? 0) * 60, $T);
    if ($phi < 0) $phi += $T;
    $mid = ($p['range_low'] + $p['range_high']) / 2;
    $amp = ($p['range_high'] - $p['range_low']) / 2;
    return round($mid - $amp * cos($phi / $T * 2 * M_PI), 2);
}

// Trouver les pleine/basse mers d'une journée
function dailyTides(int $day_start, array $p): array {
    $tides = [];
    $prev = tideAt($day_start, $p);
    $going_up = null;

    for ($i = 1; $i <= 1440; $i++) {
        $ts = $day_start + $i * 60;
        $h = tideAt($ts, $p);
        $up = ($h > $prev);

        if ($going_up !== null && $up !== $going_up) {
            $te = $ts - 30;
            $tides[] = [
                'ts' => $te,
                'time' => date('H:i', $te),
                'height' => tideAt($te, $p),
                'type' => $going_up ? 'high' : 'low',
            ];
        }

        $prev = $h;
        $going_up = $up;
    }

    return $tides;
}

// Coefficient simulé (cycle lunaire ~29.5 jours)
function dayCoeff(int $day_start, array $p): int {
    $synodic = 29.53 * 86400;
    $ref = mktime(0, 0, 0, 1, 6, 2024);
    $phase = fmod($day_start - $ref, $synodic) / $synodic * 2 * M_PI;
    return (int) max(20, min(120, ($p['coeff_base'] ?? 70) + sin($phase) * 38));
}

// Points SVG de la courbe
function curvePoints(int $day_start, array $p, int $W = 960, int $H = 160): array {
    $pad = 14;
    $rng = $p['range_high'] - $p['range_low'];
    $pts = [];

    for ($i = 0; $i <= 288; $i++) {
        $h = tideAt($day_start + $i * 300, $p);
        $x = round($i / 288 * $W, 1);
        $y = round($H - $pad - ($h - $p['range_low']) / $rng * ($H - 2 * $pad), 1);
        $pts[] = "$x,$y";
    }

    return $pts;
}

// ── Préparer les données ──────────────────────────────────────────────────────
$today_ts = mktime(0, 0, 0);
$apiData = fetchWorldTidesData($port['lat'], $port['lon']);
$days = processTideData($apiData, $port);

$today_data = $days[0] ?? [
    'ts' => $today_ts,
    'label' => "Aujourd'hui",
    'coeff' => dayCoeff($today_ts, $port),
    'tides' => dailyTides($today_ts, $port)
];

$curve_pts = curvePoints($today_ts, $port);
$svg_path = 'M ' . implode(' L ', $curve_pts);

// Position du curseur "maintenant"
$now_frac = min(1.0, max(0.0, (time() - $today_ts) / 86400));
$now_x = round($now_frac * 960, 1);
$now_h = isset($apiData['heights']) ? ($apiData['heights'][0]['height'] ?? tideAt(time(), $port)) : tideAt(time(), $port);
$prev_h = isset($apiData['heights']) ? ($apiData['heights'][0]['height'] ?? tideAt(time() - 300, $port)) - 0.1 : tideAt(time() - 300, $port);
$rng = $port['range_high'] - $port['range_low'];
$now_y = round(160 - 14 - ($now_h - $port['range_low']) / $rng * 132, 1);

$date_label = $JOURS[(int) date('w')] . ' ' . date('j') . ' ' . $MOIS[(int) date('n') - 1] . ' ' . date('Y');

// ── Graphique 30 jours ────────────────────────────────────────────────────────
function generate30DayGraph(array $port): string {
    $today = mktime(0, 0, 0);
    $points = [];
    $max_coeff = 20;
    $min_coeff = 120;

    for ($i = 0; $i < 30; $i++) {
        $ts = $today + $i * 86400;
        $coeff = dayCoeff($ts, $port);
        $max_coeff = max($max_coeff, $coeff);
        $min_coeff = min($min_coeff, $coeff);
        $points[] = $coeff;
    }

    $range = $max_coeff - $min_coeff;
    $height = 100;
    $width = 300;
    $svg = '<svg viewBox="0 0 ' . ($width + 20) . ' ' . ($height + 40) . '" xmlns="http://www.w3.org/2000/svg">';

    // Grille
    for ($i = 0; $i <= 5; $i++) {
        $y = $height - ($i / 5 * $height) + 20;
        $val = round($min_coeff + ($i / 5 * $range));
        $svg .= '<line x1="10" y1="' . $y . '" x2="' . ($width + 10) . '" y2="' . $y . '" stroke="rgba(255,255,255,.1)" stroke-width="1"/>';
        $svg .= '<text x="5" y="' . ($y + 4) . '" font-size="9" fill="rgba(107,140,170,.7)" text-anchor="end">' . $val . '</text>';
    }

    // Ligne
    $path = 'M 10,' . (20 + $height - (($points[0] - $min_coeff) / $range * $height));
    for ($i = 1; $i < 30; $i++) {
        $x = 10 + ($i / 29 * $width);
        $y = 20 + $height - (($points[$i] - $min_coeff) / $range * $height);
        $path .= ' L ' . $x . ',' . $y;
    }

    $svg .= '<path d="' . $path . '" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linejoin="round"/>';

    // Points
    for ($i = 0; $i < 30; $i++) {
        $x = 10 + ($i / 29 * $width);
        $y = 20 + $height - (($points[$i] - $min_coeff) / $range * $height);
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="2" fill="#06b6d4"/>';
    }

    // Aujourd'hui
    $today_x = 10;
    $today_y = 20 + $height - (($points[0] - $min_coeff) / $range * $height);
    $svg .= '<circle cx="' . $today_x . '" cy="' . $today_y . '" r="4" fill="none" stroke="#ffffff" stroke-width="2"/>';
    $svg .= '<text x="' . ($today_x + 15) . '" y="' . ($today_y - 5) . '" font-size="9" fill="#ffffff">Aujourd\'hui</text>';

    $svg .= '</svg>';
    return $svg;
}

$graph30Days = generate30DayGraph($port);

// ── Fonction pour générer la jauge circulaire du coefficient ─────────────────
function generateCoeffGauge(int $coeff): string {
    $angle = ($coeff / 120) * 270 - 135;
    $x = 50 + 40 * cos(deg2rad($angle));
    $y = 50 + 40 * sin(deg2rad($angle));

    $svg = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="coeff-gauge">';
    $svg .= '<defs>
                <linearGradient id="gaugeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#06b6d4" />
                    <stop offset="100%" stop-color="#0891b2" />
                </linearGradient>
                <filter id="glow" x="-30%" y="-30%" width="160%" height="160%">
                    <feGaussianBlur stdDeviation="2" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
            </defs>';

    // Arrière-plan
    $svg .= '<circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,.05)" stroke-width="2"/>';

    // Graduations
    for ($i = 0; $i <= 120; $i += 10) {
        $grad_angle = ($i / 120) * 270 - 135;
        $grad_x1 = 50 + 40 * cos(deg2rad($grad_angle));
        $grad_y1 = 50 + 40 * sin(deg2rad($grad_angle));
        $grad_x2 = 50 + 35 * cos(deg2rad($grad_angle));
        $grad_y2 = 50 + 35 * sin(deg2rad($grad_angle));

        $opacity = $i <= $coeff ? '1' : '0.2';
        $svg .= '<line x1="' . $grad_x1 . '" y1="' . $grad_y1 . '" x2="' . $grad_x2 . '" y2="' . $grad_y2 . '" stroke="rgba(6,182,212,' . $opacity . ')" stroke-width="1"/>';
    }

    // Arc de progression
    $svg .= '<path d="M50,50 L' . (50 + 40 * cos(deg2rad(-135))) . ',' . (50 + 40 * sin(deg2rad(-135))) . ' A40,40 0 ' . ($angle > -45 ? '1' : '0') . ',1 ' . $x . ',' . $y . ' Z"
             fill="url(#gaugeGrad)" opacity="0.2"/>';

    // Ligne de progression
    $svg .= '<path d="M50,50 L' . $x . ',' . $y . '" stroke="url(#gaugeGrad)" stroke-width="3" stroke-linecap="round" filter="url(#glow)"/>';

    // Pointeur
    $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="4" fill="#ffffff" stroke="#06b6d4" stroke-width="2" filter="url(#glow)"/>';

    // Valeur
    $svg .= '<text x="50" y="50" font-size="12" font-weight="bold" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">' . $coeff . '</text>';

    $svg .= '</svg>';
    return $svg;
}

$coeffGauge = generateCoeffGauge($today_data['coeff']);

// ── Fonction pour générer la carte interactive ─────────────────────────────────
function generateInteractiveMap(array $ports, string $active_port): string {
    $svg = '<svg viewBox="0 0 800 400" xmlns="http://www.w3.org/2000/svg" class="interactive-map">';

    // Fond de la carte
    $svg .= '<rect width="100%" height="100%" fill="#ffffff" rx="10"/>';

    // Ligne de côte
    $svg .= '<path d="M50,200 Q150,100 250,150 Q350,200 450,100 Q550,150 650,120 Q750,100 750,200"
             fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-dasharray="5,3"/>';

    // Ports
    foreach ($ports as $key => $port) {
        // Calcul des coordonnées SVG basées sur les coordonnées géographiques
        $x = 50 + ($port['lon'] + 2.5444) * 100; // Ajustement pour centrer la carte
        $y = 200 - ($port['lat'] - 47.1128) * 100; // Ajustement pour centrer la carte
        $is_active = $key === $active_port;

        $svg .= '<a href="?port=' . esc($key) . '">';
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($is_active ? '8' : '6') . '"
                    fill="' . ($is_active ? '#06b6d4' : '#000000') . '"
                    stroke="#ffffff" stroke-width="2"
                    class="port-marker ' . ($is_active ? 'active' : '') . '"/>';
        $svg .= '<text x="' . $x . '" y="' . ($y - 15) . '"
                    font-size="10" fill="#000000" text-anchor="middle"
                    class="port-label">' . esc($port['name']) . '</text>';
        $svg .= '</a>';
    }

    $svg .= '</svg>';
    return $svg;
}

$interactiveMap = generateInteractiveMap($PORTS, $port_key);

// ── Fonction pour générer la barre de progression de la marée ─────────────────
function generateTideProgressBar(array $today_data, array $port): string {
    global $now_h, $prev_h;
    $html = '<div class="tide-progress-container">';
    $html .= '<div class="tide-progress">';
    $html .= '<div class="tide-progress-bar" style="width: ' . (($now_h - $port['range_low']) / ($port['range_high'] - $port['range_low']) * 100) . '%"></div>';
    $html .= '</div>';
    $html .= '<div class="tide-progress-info">';
    $html .= '<span><span class="arrow">' . ($now_h > $prev_h ? '↑' : '↓') . '</span> ' . number_format($now_h, 2) . ' m</span>';
    $html .= '<span>' . number_format($port['range_low'], 2) . ' m</span>';
    $html .= '<span>' . number_format($port['range_high'], 2) . ' m</span>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

// ── Fonction pour générer le badge "Meilleur moment pour pêcher" ──────────────
function generateFishingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;
    $best_coeff = 0;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low') {
            $coeff = $tide['coeff'] ?? 0;
            if ($best_tide === null || $coeff > $best_coeff) {
                $best_tide = $tide;
                $best_time = $tide['time'];
                $best_coeff = $coeff;
            }
        }
    }

    if ($best_tide === null) {
        return '<div class="fishing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="fishing-badge">';
    $html .= '<div class="fishing-badge-icon">🎣</div>';
    $html .= '<div class="fishing-badge-content">';
    $html .= '<div class="fishing-badge-title">Meilleur moment pour pêcher</div>';
    $html .= '<div class="fishing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="fishing-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';

    if ($best_coeff > 70) {
        $start_time = date('H:i', strtotime($best_time) - 2 * 3600);
        $end_time = date('H:i', strtotime($best_time) + 2 * 3600);
        $badge_class = $best_coeff > 95 ? 'green' : ($best_coeff >= 70 ? 'orange' : 'gray');

        $html .= '<div class="fishing-badge-ideal">';
        $html .= '<span class="badge-label">Créneau idéal :</span>';
        $html .= '<span class="badge-time">' . esc($start_time) . ' → ' . esc($end_time) . '</span>';
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$fishingBadge = generateFishingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour surfer" ──────────────
function generateSurfingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high') {
            if ($best_tide === null || $tide['height'] > $best_tide['height']) {
                $best_tide = $tide;
                $best_time = $tide['time'];
            }
        }
    }

    if ($best_tide === null) {
        return '<div class="surfing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="surfing-badge">';
    $html .= '<div class="surfing-badge-icon">🏄</div>';
    $html .= '<div class="surfing-badge-content">';
    $html .= '<div class="surfing-badge-title">Meilleur moment pour surfer</div>';
    $html .= '<div class="surfing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="surfing-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$surfingBadge = generateSurfingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour naviguer" ────────────
function generateNavigationBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low') {
            if ($best_tide === null || $tide['height'] < $best_tide['height']) {
                $best_tide = $tide;
                $best_time = $tide['time'];
            }
        }
    }

    if ($best_tide === null) {
        return '<div class="navigation-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="navigation-badge">';
    $html .= '<div class="navigation-badge-icon">⛵</div>';
    $html .= '<div class="navigation-badge-content">';
    $html .= '<div class="navigation-badge-title">Meilleur moment pour naviguer</div>';
    $html .= '<div class="navigation-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="navigation-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$navigationBadge = generateNavigationBadge($today_data);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= SITE_NAME ?> — <?= esc($port['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #0a0e1a;
  --bg-gradient: linear-gradient(135deg, #0a0e1a 0%, #1a202c 100%);
  --surface: #1a202c;
  --surface2: #2d3748;
  --surface3: #4a5568;
  --border: rgba(255, 255, 255, 0.1);
  --border-hover: rgba(0, 212, 255, 0.3);
  --text: #e2e8f0;
  --text-light: #a0aec0;
  --text-lighter: #718096;
  --muted: #94a3b8;
  --cyan: #00d4ff;
  --cyan-light: #67e8f9;
  --cyan-dark: #0077b6;
  --cyan-darker: #003049;
  --blue: #3b82f6;
  --blue-dark: #1d4ed8;
  --green: #10b981;
  --green-dark: #059669;
  --amber: #f59e0b;
  --amber-light: #fbbf24;
  --amber-dark: #d97706;
  --red: #ef4444;
  --red-dark: #dc2626;
  --purple: #8b5cf6;
  --r: 16px;
  --r-lg: 24px;
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.1);
  --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 20px 25px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 25px 50px rgba(0, 0, 0, 0.25);
  --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --mono: 'JetBrains Mono', 'Fira Code', monospace;
  --display: 'Playfair Display', serif;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html {
  scroll-behavior: smooth;
}

body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  line-height: 1.6;
  background-image: var(--bg-gradient);
  background-attachment: fixed;
}

/* ── Layout principal ── */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

/* ── Header immersif ── */
.hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(180deg, rgba(10, 14, 26, 0.9) 0%, rgba(26, 32, 44, 0.8) 100%);
  padding: 4rem 0 6rem;
  min-height: 400px;
  box-shadow: var(--shadow-lg);
  background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
}

.hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(180deg, rgba(10, 14, 26, 0.9) 0%, rgba(26, 32, 44, 0.8) 100%);
  z-index: 1;
}

.hero-content {
  position: relative;
  z-index: 2;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 2rem;
}

.hero-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 2rem;
}

.site-brand {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--cyan);
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  background: rgba(0, 212, 255, 0.1);
  padding: 0.6rem 1.2rem;
  border-radius: 50px;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(0, 212, 255, 0.2);
  box-shadow: var(--shadow-sm);
}

.site-brand::before {
  content: "🌊";
  animation: wave 2s ease-in-out infinite;
}

@keyframes wave {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  25% { transform: translateY(-3px) rotate(5deg); }
  75% { transform: translateY(-3px) rotate(-5deg); }
}

.hero-port {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.1;
  background: linear-gradient(135deg, var(--text), var(--cyan));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-shadow: 0 2px 8px rgba(0, 212, 255, 0.2);
  font-family: var(--display);
  margin-bottom: 0.5rem;
  position: relative;
  display: inline-block;
}

.hero-port::after {
  content: attr(data-icon);
  position: absolute;
  right: -2rem;
  top: 0.2rem;
  font-size: 2.5rem;
  animation: float 3s ease-in-out infinite;
}

.hero-region {
  color: var(--text-light);
  font-size: 1.2rem;
  margin-top: 0.25rem;
  max-width: 600px;
  font-weight: 500;
}

.hero-date {
  text-align: right;
  font-size: 0.9rem;
  color: var(--text-light);
  background: rgba(255, 255, 255, 0.1);
  padding: 0.6rem 1.2rem;
  border-radius: 50px;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: var(--shadow-sm);
}

.hero-time {
  font-size: 2.8rem;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--cyan);
  text-shadow: 0 0 12px rgba(0, 212, 255, 0.3);
  margin-bottom: 0.3rem;
  background: rgba(0, 212, 255, 0.1);
  padding: 0.6rem 1.2rem;
  border-radius: 12px;
  display: inline-block;
  box-shadow: var(--shadow-sm);
}

/* ── Vagues animées ── */
.waves {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 150px;
  z-index: 1;
  overflow: hidden;
}

.waves svg {
  width: 200%;
  height: 100%;
  animation: wave 15s cubic-bezier(0.36, 0.45, 0.63, 0.53) infinite;
}

.wave-path {
  fill: none;
  stroke: rgba(0, 212, 255, 0.15);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  animation: wavePath 10s ease-in-out infinite alternate;
}

@keyframes wavePath {
  0% {
    stroke-dasharray: 0 100%;
    opacity: 0.3;
    stroke: rgba(0, 212, 255, 0.1);
  }
  100% {
    stroke-dasharray: 100% 0;
    opacity: 0.6;
    stroke: rgba(0, 212, 255, 0.25);
  }
}

/* ── Navigation des ports ── */
.port-nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  box-shadow: var(--shadow);
  position: sticky;
  top: 0;
  z-index: 100;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.port-nav-inner {
  display: flex;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
  gap: 0;
}

.port-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-light);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  white-space: nowrap;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
}

.port-btn:hover {
  color: var(--text);
  transform: translateY(-2px);
  background: rgba(0, 212, 255, 0.05);
}

.port-btn.active {
  color: var(--cyan);
  border-bottom-color: var(--cyan);
  transform: translateY(-2px);
  background: rgba(0, 212, 255, 0.1);
  box-shadow: 0 2px 8px rgba(0, 212, 255, 0.1);
}

.port-btn::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--cyan);
  transition: width 0.3s ease;
}

.port-btn:hover::after, .port-btn.active::after {
  width: 100%;
}

/* ── Cartes principales ── */
.card {
  background: var(--surface);
  border-radius: var(--r);
  overflow: hidden;
  transition: all 0.3s ease;
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
}

.card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
  border-color: var(--border-hover);
}

.glass-card {
  background: rgba(26, 32, 44, 0.8);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.glass-card:hover {
  transform: translateY(-6px);
  box-shadow: var(--shadow-lg);
  border-color: rgba(0, 212, 255, 0.3);
}

.card-header {
  padding: 1.2rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 0.7rem;
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--text-light);
  background: var(--surface2);
  position: relative;
}

.card-header::after {
  content: '';
  position: absolute;
  left: 1.5rem;
  bottom: 0;
  width: 40px;
  height: 2px;
  background: var(--cyan);
  border-radius: 1px;
}

.card-body {
  padding: 1.5rem;
}

/* ── Grille du haut ── */
.top-grid {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 2rem;
  margin: 2rem 0;
}

@media (max-width: 768px) {
  .top-grid {
    grid-template-columns: 1fr;
  }
}

/* ── Carte du coefficient ── */
.coeff-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.8rem;
  padding: 2rem 1rem;
  text-align: center;
  position: relative;
  background: radial-gradient(circle at center, rgba(0, 212, 255, 0.08) 0%, transparent 70%);
  border-radius: var(--r);
}

.coeff-number {
  font-size: 5rem;
  font-weight: 900;
  font-family: var(--mono);
  line-height: 1;
  background: linear-gradient(135deg, var(--cyan), var(--cyan-dark));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-shadow: 0 0 12px rgba(0, 212, 255, 0.2);
  animation: pulse 2.5s infinite alternate;
}

@keyframes pulse {
  0% {
    transform: scale(1);
    text-shadow: 0 0 12px rgba(0, 212, 255, 0.2);
  }
  100% {
    transform: scale(1.03);
    text-shadow: 0 0 20px rgba(0, 212, 255, 0.3);
  }
}

.coeff-label {
  font-size: 0.85rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 0.4rem 1.2rem;
  border-radius: 50px;
  margin-top: 0.4rem;
  background: rgba(0, 212, 255, 0.15);
  color: var(--cyan-dark);
  border: 1px solid rgba(0, 212, 255, 0.3);
  box-shadow: 0 0 12px rgba(0, 212, 255, 0.08);
  transition: all 0.3s ease;
}

.coeff-label:hover {
  background: rgba(0, 212, 255, 0.25);
  transform: translateY(-1px);
}

.coeff-title {
  font-size: 0.8rem;
  color: var(--text-light);
  margin-top: 0.3rem;
  position: relative;
  padding-bottom: 0.3rem;
}

.coeff-title::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 24px;
  height: 2px;
  background: var(--cyan);
  border-radius: 1px;
}

.coeff-vive .coeff-number {
  background: linear-gradient(135deg, var(--red), var(--red-dark));
  text-shadow: 0 0 12px rgba(239, 68, 68, 0.2);
}

.coeff-vive .coeff-label {
  background: rgba(239, 68, 68, 0.15);
  color: var(--red-dark);
  border: 1px solid rgba(239, 68, 68, 0.3);
  box-shadow: 0 0 12px rgba(239, 68, 68, 0.08);
}

.coeff-fort .coeff-number {
  background: linear-gradient(135deg, var(--amber), var(--amber-dark));
  text-shadow: 0 0 12px rgba(245, 158, 11, 0.2);
}

.coeff-fort .coeff-label {
  background: rgba(245, 158, 11, 0.15);
  color: var(--amber-dark);
  border: 1px solid rgba(245, 158, 11, 0.3);
  box-shadow: 0 0 12px rgba(245, 158, 11, 0.08);
}

.coeff-moyen .coeff-number {
  background: linear-gradient(135deg, var(--cyan), var(--cyan-dark));
}

.coeff-moyen .coeff-label {
  background: rgba(0, 212, 255, 0.15);
  color: var(--cyan-dark);
  border: 1px solid rgba(0, 212, 255, 0.3);
}

.coeff-morte .coeff-number {
  background: linear-gradient(135deg, var(--green), var(--green-dark));
  text-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
}

.coeff-morte .coeff-label {
  background: rgba(16, 185, 129, 0.15);
  color: var(--green-dark);
  border: 1px solid rgba(16, 185, 129, 0.3);
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.08);
}

/* ── Jauge circulaire ── */
.coeff-gauge-container {
  width: 100%;
  max-width: 220px;
  margin: 0 auto;
  position: relative;
}

.coeff-gauge {
  width: 100%;
  height: auto;
  margin-bottom: 1.5rem;
  transform: rotate(-90deg);
}

.coeff-gauge-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  color: var(--text-light);
  margin-top: 1rem;
}

.coeff-gauge-info span {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
}

.coeff-gauge-info .pip {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.coeff-gauge-info .pip-vive { background: var(--red); }
.coeff-gauge-info .pip-fort { background: var(--amber); }
.coeff-gauge-info .pip-moyen { background: var(--cyan); }
.coeff-gauge-info .pip-morte { background: var(--green); }

/* ── Barre de progression de la marée ── */
.tide-progress-container {
  width: 100%;
  margin: 1.5rem 0;
}

.tide-progress {
  height: 24px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.tide-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, var(--cyan), var(--cyan-dark));
  border-radius: 12px;
  transition: width 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
  position: relative;
  box-shadow: 0 0 12px rgba(0, 212, 255, 0.2);
}

.tide-progress-bar::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 2px;
  background: rgba(255, 255, 255, 0.5);
  box-shadow: 0 0 6px rgba(0, 212, 255, 0.5);
}

.tide-progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  color: var(--text-light);
  margin-top: 0.4rem;
  font-family: var(--mono);
}

.tide-progress-info span {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.tide-progress-info .arrow {
  font-size: 0.9rem;
  animation: pulse 1.5s infinite alternate;
  color: var(--cyan);
}

/* ── Marées du jour ── */
.tides-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 1.2rem;
  height: 100%;
  align-content: center;
}

.tide-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1.3rem 1rem;
  text-align: center;
  position: relative;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}

.tide-item:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-md);
  border-color: var(--cyan);
}

.tide-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, transparent, var(--cyan), transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.tide-item:hover::before {
  opacity: 1;
}

.tide-badge {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 0.3rem 0.8rem;
  border-radius: 50px;
  margin-bottom: 0.6rem;
  display: inline-block;
  background: rgba(0, 212, 255, 0.1);
  color: var(--cyan);
  border: 1px solid rgba(0, 212, 255, 0.2);
  transition: all 0.3s ease;
}

.tide-high .tide-badge {
  background: rgba(0, 212, 255, 0.15);
  color: var(--cyan);
  border: 1px solid rgba(0, 212, 255, 0.3);
  animation: badgePulse 2.5s infinite;
}

.tide-low .tide-badge {
  background: rgba(245, 158, 11, 0.15);
  color: var(--amber);
  border: 1px solid rgba(245, 158, 11, 0.3);
}

@keyframes badgePulse {
  0% {
    box-shadow: 0 0 0 0 rgba(0, 212, 255, 0.2);
    transform: scale(1);
  }
  70% {
    box-shadow: 0 0 0 12px rgba(0, 212, 255, 0);
    transform: scale(1.05);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(0, 212, 255, 0);
    transform: scale(1);
  }
}

.tide-time {
  font-size: 1.8rem;
  font-weight: 800;
  font-family: var(--mono);
  line-height: 1;
  color: var(--text);
  background: linear-gradient(135deg, var(--text), var(--cyan));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 0.3rem;
}

.tide-height {
  font-size: 0.9rem;
  color: var(--text-light);
  margin-top: 0.3rem;
  font-family: var(--mono);
  font-weight: 500;
}

.tide-high .tide-height {
  color: var(--cyan);
}

.tide-low .tide-height {
  color: var(--amber);
}

/* ── Courbe des marées ── */
.curve-wrap {
  position: relative;
  width: 100%;
  overflow: hidden;
  border-radius: var(--r);
  background: var(--surface2);
  padding: 1.5rem;
  box-shadow: inset 0 0 12px rgba(0, 0, 0, 0.2);
  border: 1px solid var(--border);
  margin-top: 1.5rem;
}

.curve-svg {
  width: 100%;
  height: auto;
  display: block;
  filter: drop-shadow(0 0 12px rgba(0, 212, 255, 0.1));
}

.curve-wrap .axis-hours {
  display: flex;
  justify-content: space-between;
  padding: 0.6rem 0 0;
  font-size: 0.75rem;
  color: var(--text-light);
  font-family: var(--mono);
  position: relative;
}

.curve-wrap .axis-hours::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--border), transparent);
}

/* Animations de la courbe */
.curve-path {
  stroke-dasharray: 1000;
  stroke-dashoffset: 1000;
  animation: drawCurve 2.5s ease-out forwards;
}

@keyframes drawCurve {
  to { stroke-dashoffset: 0; }
}

.tide-point {
  opacity: 0;
  animation: fadeIn 0.6s ease-out forwards;
}

@keyframes fadeIn {
  to { opacity: 1; }
}

.tide-point:nth-child(1) { animation-delay: 0.3s; }
.tide-point:nth-child(2) { animation-delay: 0.6s; }
.tide-point:nth-child(3) { animation-delay: 0.9s; }
.tide-point:nth-child(4) { animation-delay: 1.2s; }

.now-cursor {
  opacity: 0;
  animation: fadeIn 0.5s ease-out 1.5s forwards;
}

.now-text {
  opacity: 0;
  animation: fadeIn 0.5s ease-out 1.7s forwards;
}

/* ── Tableau 7 jours ── */
.days-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  border-radius: var(--r);
  overflow: hidden;
  background: var(--surface2);
  border: 1px solid var(--border);
}

.days-table tr {
  border-bottom: 1px solid var(--border);
  transition: background 0.2s ease;
}

.days-table tr:last-child {
  border-bottom: none;
}

.days-table tr:hover td {
  background: var(--surface3);
}

.days-table td {
  padding: 1rem 1.2rem;
  font-size: 0.9rem;
  vertical-align: middle;
  position: relative;
}

.td-day {
  font-weight: 700;
  min-width: 130px;
  color: var(--text);
  position: relative;
  font-family: var(--font);
}

.td-day::after {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 1px;
  height: 60%;
  background: var(--border);
}

.td-coeff {
  min-width: 120px;
  text-align: center;
}

.coeff-pip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-family: var(--mono);
  font-weight: 700;
  font-size: 0.95rem;
  padding: 0.4rem 0.8rem;
  border-radius: 10px;
  background: rgba(0, 212, 255, 0.08);
  transition: all 0.3s ease;
}

.coeff-pip:hover {
  background: rgba(0, 212, 255, 0.15);
  transform: translateY(-1px);
}

.pip {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.1);
}

.pip-vive { background: var(--red); }
.pip-fort { background: var(--amber); }
.pip-moyen { background: var(--cyan); }
.pip-morte { background: var(--green); }

.td-tides {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  justify-content: flex-end;
}

.mini-tide {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0.4rem 0.8rem;
  min-width: 72px;
  transition: all 0.3s ease;
  box-shadow: var(--shadow-sm);
}

.mini-tide:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow);
  border-color: var(--cyan);
  background: rgba(0, 212, 255, 0.05);
}

.mini-tide-type {
  font-size: 0.62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 0.3rem;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
}

.mini-tide.high .mini-tide-type {
  color: var(--cyan);
  background: rgba(0, 212, 255, 0.1);
}

.mini-tide.low .mini-tide-type {
  color: var(--amber);
  background: rgba(245, 158, 11, 0.1);
}

.mini-tide-time {
  font-size: 0.85rem;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--text);
}

.mini-tide-h {
  font-size: 0.75rem;
  color: var(--text-light);
  font-family: var(--mono);
}

/* ──