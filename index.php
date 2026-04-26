<?php
/**
 * MaréesLive — Horaires des marées en France
 * ─────────────────────────────────────────────────────────────────────────────
 * v3.4.0 — Design professionnel avec animations immersives et visualisations améliorées
 */

define('VERSION',   '3.4.0');
define('SITE_NAME', 'MaréesLive');
define('API_KEY',   'YOUR_WORLDTIDES_API_KEY'); // Remplacez par votre clé API WorldTides

$JOURS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$MOIS  = ['jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'];

// ── Ports français ────────────────────────────────────────────────────────────
$PORTS = [
    'le-croisic' => [
        'name'       => 'Le Croisic',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.2989,
        'lon'        => -2.5128,
        'range_low'  => 1.2,
        'range_high' => 8.5,
        'desc'       => 'Port pittoresque avec vue sur l\'île de Noirmoutier',
    ],
    'la-turballe' => [
        'name'       => 'La Turballe',
        'region'     => 'Loire-Atlantique',
        'icon'       => '⚓',
        'lat'        => 47.3467,
        'lon'        => -2.5097,
        'range_low'  => 1.1,
        'range_high' => 8.2,
        'desc'       => 'Port de pêche et de plaisance',
    ],
    'la-baule' => [
        'name'       => 'La Baule',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🏖️',
        'lat'        => 47.2854,
        'lon'        => -2.3967,
        'range_low'  => 1.3,
        'range_high' => 8.8,
        'desc'       => 'Station balnéaire renommée',
    ],
    'pornichet' => [
        'name'       => 'Pornichet',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🏝️',
        'lat'        => 47.2628,
        'lon'        => -2.3408,
        'range_low'  => 1.4,
        'range_high' => 9.1,
        'desc'       => 'Ville balnéaire avec plage de sable fin',
    ],
    'saint-nazaire' => [
        'name'       => 'Saint-Nazaire',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🚢',
        'lat'        => 47.2736,
        'lon'        => -2.2137,
        'range_low'  => 1.5,
        'range_high' => 9.5,
        'desc'       => 'Port maritime important',
    ],
    'saint-brevin-les-pins' => [
        'name'       => 'Saint-Brevin-les-Pins',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌳',
        'lat'        => 47.2431,
        'lon'        => -2.1681,
        'range_low'  => 1.6,
        'range_high' => 9.8,
        'desc'       => 'Village de pêcheurs avec vue sur l\'estuaire',
    ],
    'pornic' => [
        'name'       => 'Pornic',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.1128,
        'lon'        => -2.1008,
        'range_low'  => 1.7,
        'range_high' => 10.2,
        'desc'       => 'Port de pêche traditionnel',
    ],
    'prefailles' => [
        'name'       => 'Préfailles',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.1389,
        'lon'        => -2.2131,
        'range_low'  => 1.8,
        'range_high' => 10.5,
        'desc'       => 'Port de pêche avec vue sur l\'estuaire',
    ],
    'piriac-sur-mer' => [
        'name'       => 'Piriac-sur-Mer',
        'region'     => 'Loire-Atlantique',
        'icon'       => '🌊',
        'lat'        => 47.3797,
        'lon'        => -2.5444,
        'range_low'  => 1.9,
        'range_high' => 10.8,
        'desc'       => 'Port de pêche avec vue sur l\'estuaire',
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
function fetchWorldTidesData(float $lat, float $lon, int $start, int $days = 7): array {
    global $API_KEY;
    $url = "https://www.worldtides.info/api/v3?extremes&lat={$lat}&lon={$lon}&start={$start}&length={$days}day&key={$API_KEY}";
    $cache_file = __DIR__ . '/cache/' . md5($url) . '.json';
    $cache_time = 3600; // 1 heure

    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_time)) {
        return json_decode(file_get_contents($cache_file), true);
    }

    $response = @file_get_contents($url);
    if ($response === false) {
        return ['status' => 'error', 'message' => 'Impossible de se connecter à l\'API WorldTides'];
    }

    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === 'error') {
        return $data;
    }

    file_put_contents($cache_file, $response);
    return $data;
}

function processTideData(array $apiData, array $port): array {
    $days = [];
    $today_ts = mktime(0, 0, 0);

    if (isset($apiData['extremes'])) {
        $tides = $apiData['extremes'];
        $current_day = null;

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

            $current_day_data['tides'][] = [
                'ts' => $ts,
                'time' => date('H:i', $ts),
                'height' => round($tide['height'], 2),
                'type' => $tide['type'] === 'High' ? 'high' : 'low',
                'coeff' => isset($tide['coefficient']) ? (int)$tide['coefficient'] : 70
            ];
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
$apiData = fetchWorldTidesData($port['lat'], $port['lon'], $today_ts);
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
$now_h = isset($apiData['heights']) ? $apiData['heights'][0]['height'] : tideAt(time(), $port);
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
    $svg .= '<rect width="100%" height="100%" fill="#0a1628" rx="10"/>';

    // Ligne de côte
    $svg .= '<path d="M50,200 Q150,100 250,150 Q350,200 450,100 Q550,150 650,120 Q750,100 750,200"
             fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-dasharray="5,3"/>';

    // Ports
    foreach ($ports as $key => $port) {
        $x = 50 + ($port['lon'] + 2.5444) * 100; // Ajustement pour centrer la carte
        $y = 200 - ($port['lat'] - 47.1128) * 100; // Ajustement pour centrer la carte
        $is_active = $key === $active_port;

        $svg .= '<a href="?port=' . esc($key) . '">';
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($is_active ? '8' : '6') . '"
                    fill="' . ($is_active ? '#06b6d4' : '#ffffff') . '"
                    stroke="#0a1628" stroke-width="2"
                    class="port-marker ' . ($is_active ? 'active' : '') . '"/>';
        $svg .= '<text x="' . $x . '" y="' . ($y - 15) . '"
                    font-size="10" fill="#ffffff" text-anchor="middle"
                    class="port-label">' . esc($port['name']) . '</text>';
        $svg .= '</a>';
    }

    $svg .= '</svg>';
    return $svg;
}

$interactiveMap = generateInteractiveMap($PORTS, $port_key);

// ── Fonction pour générer la barre de progression de la marée ─────────────────
function generateTideProgressBar(array $today_data, array $port): string {
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

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
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
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
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
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
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

// ── Fonction pour générer le badge "Meilleur moment pour observer les oiseaux" ────────────
function generateBirdwatchingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="birdwatching-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="birdwatching-badge">';
    $html .= '<div class="birdwatching-badge-icon">🦅</div>';
    $html .= '<div class="birdwatching-badge-content">';
    $html .= '<div class="birdwatching-badge-title">Meilleur moment pour observer les oiseaux</div>';
    $html .= '<div class="birdwatching-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="birdwatching-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$birdwatchingBadge = generateBirdwatchingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour photographier" ────────────
function generatePhotographyBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="photography-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="photography-badge">';
    $html .= '<div class="photography-badge-icon">📷</div>';
    $html .= '<div class="photography-badge-content">';
    $html .= '<div class="photography-badge-title">Meilleur moment pour photographier</div>';
    $html .= '<div class="photography-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="photography-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$photographyBadge = generatePhotographyBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak" ────────────
function generateKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="kayaking-badge">';
    $html .= '<div class="kayaking-badge-icon">🛶</div>';
    $html .= '<div class="kayaking-badge-content">';
    $html .= '<div class="kayaking-badge-title">Meilleur moment pour faire du kayak</div>';
    $html .= '<div class="kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$kayakingBadge = generateKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du vélo" ────────────
function generateCyclingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if
if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="cycling-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="cycling-badge">';
    $html .= '<div class="cycling-badge-icon">🚴</div>';
    $html .= '<div class="cycling-badge-content">';
    $html .= '<div class="cycling-badge-title">Meilleur moment pour faire du vélo</div>';
    $html .= '<div class="cycling-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="cycling-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$cyclingBadge = generateCyclingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du jogging" ────────────
function generateJoggingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="jogging-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="jogging-badge">';
    $html .= '<div class="jogging-badge-icon">🏃</div>';
    $html .= '<div class="jogging-badge-content">';
    $html .= '<div class="jogging-badge-title">Meilleur moment pour faire du jogging</div>';
    $html .= '<div class="jogging-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="jogging-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$joggingBadge = generateJoggingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du skate" ────────────
function generateSkateboardingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="skateboarding-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="skateboarding-badge">';
    $html .= '<div class="skateboarding-badge-icon">🛹</div>';
    $html .= '<div class="skateboarding-badge-content">';
    $html .= '<div class="skateboarding-badge-title">Meilleur moment pour faire du skate</div>';
    $html .= '<div class="skateboarding-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="skateboarding-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$skateboardingBadge = generateSkateboardingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du surf" ────────────
function generateSurfingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="surfing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="surfing-badge">';
    $html .= '<div class="surfing-badge-icon">🏄</div>';
    $html .= '<div class="surfing-badge-content">';
    $html .= '<div class="surfing-badge-title">Meilleur moment pour faire du surf</div>';
    $html .= '<div class="surfing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="surfing-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$surfingBadge = generateSurfingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kitesurf" ────────────
function generateKitesurfingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="kitesurfing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="kitesurfing-badge">';
    $html .= '<div class="kitesurfing-badge-icon">🪁</div>';
    $html .= '<div class="kitesurfing-badge-content">';
    $html .= '<div class="kitesurfing-badge-title">Meilleur moment pour faire du kitesurf</div>';
    $html .= '<div class="kitesurfing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="kitesurfing-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$kitesurfingBadge = generateKitesurfingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du windsurf" ────────────
function generateWindsurfingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="windsurfing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="windsurfing-badge">';
    $html .= '<div class="windsurfing-badge-icon">🏄‍♂️</div>';
    $html .= '<div class="windsurfing-badge-content">';
    $html .= '<div class="windsurfing-badge-title">Meilleur moment pour faire du windsurf</div>';
    $html .= '<div class="windsurfing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="windsurfing-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$windsurfingBadge = generateWindsurfingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du paddle" ────────────
function generatePaddleboardingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'high' && ($best_tide === null || $tide['height'] > $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="paddleboarding-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="paddleboarding-badge">';
    $html .= '<div class="paddleboarding-badge-icon">🛶</div>';
    $html .= '<div class="paddleboarding-badge-content">';
    $html .= '<div class="paddleboarding-badge-title">Meilleur moment pour faire du paddle</div>';
    $html .= '<div class="paddleboarding-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="paddleboarding-badge-info">Pleine mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$paddleboardingBadge = generatePaddleboardingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du snorkeling" ────────────
function generateSnorkelingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="snorkeling-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="snorkeling-badge">';
    $html .= '<div class="snorkeling-badge-icon">🤿</div>';
    $html .= '<div class="snorkeling-badge-content">';
    $html .= '<div class="snorkeling-badge-title">Meilleur moment pour faire du snorkeling</div>';
    $html .= '<div class="snorkeling-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="snorkeling-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$snorkelingBadge = generateSnorkelingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du plongée" ────────────
function generateScubaDivingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="scuba-diving-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="scuba-diving-badge">';
    $html .= '<div class="scuba-diving-badge-icon">🤿</div>';
    $html .= '<div class="scuba-diving-badge-content">';
    $html .= '<div class="scuba-diving-badge-title">Meilleur moment pour faire du plongée</div>';
    $html .= '<div class="scuba-diving-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="scuba-diving-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$scubaDivingBadge = generateScubaDivingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du bateau" ────────────
function generateBoatingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="boating-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="boating-badge">';
    $html .= '<div class="boating-badge-icon">⛵</div>';
    $html .= '<div class="boating-badge-content">';
    $html .= '<div class="boating-badge-title">Meilleur moment pour faire du bateau</div>';
    $html .= '<div class="boating-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="boating-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$boatingBadge = generateBoatingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du pédalo" ────────────
function generatePedaloBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="pedalo-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="pedalo-badge">';
    $html .= '<div class="pedalo-badge-icon">🛶</div>';
    $html .= '<div class="pedalo-badge-content">';
    $html .= '<div class="pedalo-badge-title">Meilleur moment pour faire du pédalo</div>';
    $html .= '<div class="pedalo-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="pedalo-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$pedaloBadge = generatePedaloBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du canoë" ────────────
function generateCanoeingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="canoeing-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="canoeing-badge">';
    $html .= '<div class="canoeing-badge-icon">🛶</div>';
    $html .= '<div class="canoeing-badge-content">';
    $html .= '<div class="canoeing-badge-title">Meilleur moment pour faire du canoë</div>';
    $html .= '<div class="canoeing-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="canoeing-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$canoeingBadge = generateCanoeingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du rafting" ────────────
function generateRaftingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="rafting-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="rafting-badge">';
    $html .= '<div class="rafting-badge-icon">🛶</div>';
    $html .= '<div class="rafting-badge-content">';
    $html .= '<div class="rafting-badge-title">Meilleur moment pour faire du rafting</div>';
    $html .= '<div class="rafting-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="rafting-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$raftingBadge = generateRaftingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de rivière" ────────────
function generateRiverKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="river-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="river-kayaking-badge">';
    $html .= '<div class="river-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="river-kayaking-badge-content">';
    $html .= '<div class="river-kayaking-badge-title">Meilleur moment pour faire du kayak de rivière</div>';
    $html .= '<div class="river-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="river-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$riverKayakingBadge = generateRiverKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-k
if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div
if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div
if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div
if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div>';
    }

    $html = '<div class="sea-kayaking-badge">';
    $html .= '<div class="sea-kayaking-badge-icon">🛶</div>';
    $html .= '<div class="sea-kayaking-badge-content">';
    $html .= '<div class="sea-kayaking-badge-title">Meilleur moment pour faire du kayak de mer</div>';
    $html .= '<div class="sea-kayaking-badge-time">' . esc($best_time) . '</div>';
    $html .= '<div class="sea-kayaking-badge-info">Basse mer à ' . number_format($best_tide['height'], 2) . ' m</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

$seaKayakingBadge = generateSeaKayakingBadge($today_data);

// ── Fonction pour générer le badge "Meilleur moment pour faire du kayak de mer" ────────────
function generateSeaKayakingBadge(array $today_data): string {
    $best_tide = null;
    $best_time = null;

    foreach ($today_data['tides'] as $tide) {
        if ($tide['type'] === 'low' && ($best_tide === null || $tide['height'] < $best_tide['height'])) {
            $best_tide = $tide;
            $best_time = $tide['time'];
        }
    }

    if ($best_tide === null) {
        return '<div class="sea-kayaking-badge">Aucune donnée disponible</div