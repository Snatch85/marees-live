<?php
/**
 * MaréesLive — Horaires des marées en France
 * ─────────────────────────────────────────────────────────────────────────────
 * v3.3.0 — Design professionnel avec animations immersives et visualisations améliorées
 */

define('VERSION',   '3.3.0');
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
  --bg:       #030d1f;
  --surface:  #071428;
  --surface2: #0a1e3a;
  --surface3: #0f2a4d;
  --border:   rgba(255,255,255,.07);
  --text:     #e8f4ff;
  --muted:    #6b8caa;
  --cyan:     #06b6d4;
  --teal:     #0891b2;
  --blue:     #1d4ed8;
  --green:    #10b981;
  --amber:    #f59e0b;
  --red:      #ef4444;
  --purple:   #8b5cf6;
  --r:        14px;
  --font:     'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --mono:     'JetBrains Mono', 'Fira Code', monospace;
  --display:  'Playfair Display', serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  line-height: 1.6;
  background-image:
    radial-gradient(circle at 10% 20%, rgba(6, 182, 212, 0.1) 0%, transparent 20%),
    radial-gradient(circle at 90% 80%, rgba(139, 92, 246, 0.1) 0%, transparent 20%);
}

/* ── Header immersif ── */
.hero {
  position: relative;
  overflow: hidden;
  background: linear-gradient(180deg, #011533 0%, #030d1f 100%);
  padding: 3rem 2rem 0;
  min-height: 300px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
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
  background: linear-gradient(180deg, rgba(3, 13, 31, 0.7) 0%, rgba(3, 13, 31, 0.9) 100%);
  z-index: 1;
}
.hero-content {
  position: relative;
  z-index: 2;
  max-width: 1100px;
  margin: 0 auto;
}
.hero-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}
.site-brand {
  font-size: .78rem;
  font-weight: 7
0;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--cyan);
  margin-bottom: .4rem;
  display: flex;
  align-items: center;
  gap: .5rem;
}
.site-brand::before {
  content: "🌊";
  animation: wave 2s ease-in-out infinite;
}
@keyframes wave {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}
.hero-port {
  font-size: 3rem;
  font-weight: 800;
  line-height: 1.1;
  background: linear-gradient(135deg, #e8f4ff, var(--cyan));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  font-family: var(--display);
  margin-bottom: .5rem;
}
.hero-region {
  color: var(--muted);
  font-size: 1.1rem;
  margin-top: .25rem;
  max-width: 600px;
}
.hero-date {
  text-align: right;
  font-size: .85rem;
  color: var(--muted);
}
.hero-time {
  font-size: 2.5rem;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--cyan);
  text-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
  margin-bottom: .3rem;
}

/* Vagues animées améliorées */
.waves-wrap {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 120px;
  z-index: 1;
  overflow: hidden;
}
.waves-wrap svg {
  width: 200%;
  height: 100%;
  animation: wave 12s cubic-bezier(0.36, 0.45, 0.63, 0.53) infinite;
}
@keyframes wave {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.wave-path {
  fill: none;
  stroke: rgba(6, 182, 212, 0.2);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  animation: wavePath 8s ease-in-out infinite alternate;
}
@keyframes wavePath {
  0% { stroke-dasharray: 0 100%; opacity: 0.5; }
  100% { stroke-dasharray: 100% 0; opacity: 0.8; }
}

/* ── Sélecteur de ports ── */
.port-nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}
.port-nav-inner {
  display: flex;
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 2rem;
  gap: 0;
}
.port-btn {
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .85rem 1.2rem;
  font-size: .83rem;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
  border-bottom: 2px solid transparent;
  white-space: nowrap;
  transition: all .2s ease;
  position: relative;
}
.port-btn:hover {
  color: var(--text);
  transform: translateY(-1px);
}
.port-btn.active {
  color: var(--cyan);
  border-bottom-color: var(--cyan);
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(6, 182, 212, 0.2);
}
.port-btn::after {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 0;
  width: 0;
  height: 2px;
  background: var(--cyan);
  transition: width .2s ease;
}
.port-btn:hover::after, .port-btn.active::after {
  width: 100%;
}

/* ── Layout ── */
.main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* ── Glassmorphism ── */
.glass-card {
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--r);
  box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}
.glass-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

/* ── Cards ── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  transition: transform .2s ease, box-shadow .2s ease;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}
.card-header {
  padding: .9rem 1.4rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: .6rem;
  font-size: .82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--muted);
  background: var(--surface2);
}
.card-body { padding: 1.4rem; }

/* ── Grille du haut ── */
.top-grid {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 1.5rem;
}

@media (max-width: 700px) {
  .top-grid {
    grid-template-columns: 1fr;
  }
}

/* ── Coefficient ── */
.coeff-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  padding: 2rem 1rem;
  text-align: center;
  position: relative;
  background: radial-gradient(circle at center, rgba(6, 182, 212, 0.1) 0%, transparent 70%);
}
.coeff-number {
  font-size: 5rem;
  font-weight: 900;
  font-family: var(--mono);
  line-height: 1;
  background: linear-gradient(135deg, #06b6d4, #0891b2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  text-shadow: 0 0 10px rgba(6, 182, 212, 0.3);
  animation: pulse 2s infinite alternate;
}
@keyframes pulse {
  0% { transform: scale(1); }
  100% { transform: scale(1.02); }
}
.coeff-label {
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  padding: .3rem .9rem;
  border-radius: 99px;
  margin-top: .4rem;
  background: rgba(6, 182, 212, 0.2);
  color: var(--cyan);
  border: 1px solid rgba(6, 182, 212, 0.3);
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.1);
}
.coeff-title {
  font-size: .75rem;
  color: var(--muted);
  margin-top: .2rem;
  position: relative;
}
.coeff-title::after {
  content: '';
  position: absolute;
  bottom: -4px;
  left: 50%;
  transform: translateX(-50%);
  width: 20px;
  height: 2px;
  background: var(--cyan);
  border-radius: 1px;
}

.coeff-vive  .coeff-number { color: var(--red); background: linear-gradient(135deg, #ef4444, #dc2626); text-shadow: 0 0 10px rgba(239, 68, 68, 0.3); }
.coeff-vive  .coeff-label  { background: rgba(239, 68, 68, 0.2); color: var(--red); border: 1px solid rgba(239, 68, 68, 0.3); box-shadow: 0 0 8px rgba(239, 68, 68, 0.1); }
.coeff-fort  .coeff-number { color: var(--amber); background: linear-gradient(135deg, #f59e0b, #d97706); text-shadow: 0 0 10px rgba(245, 158, 11, 0.3); }
.coeff-fort  .coeff-label  { background: rgba(245, 158, 11, 0.2); color: var(--amber); border: 1px solid rgba(245, 158, 11, 0.3); box-shadow: 0 0 8px rgba(245, 158, 11, 0.1); }
.coeff-moyen .coeff-number { color: var(--cyan); }
.coeff-moyen .coeff-label  { background: rgba(6, 182, 212, 0.2); color: var(--cyan); border: 1px solid rgba(6, 182, 212, 0.3); }
.coeff-morte .coeff-number { color: var(--green); background: linear-gradient(135deg, #10b981, #059669); text-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }
.coeff-morte .coeff-label  { background: rgba(16, 185, 129, 0.2); color: var(--green); border: 1px solid rgba(16, 185, 129, 0.3); box-shadow: 0 0 8px rgba(16, 185, 129, 0.1); }

/* ── Jauge circulaire du coefficient ── */
.coeff-gauge-container {
  width: 100%;
  max-width: 200px;
  margin: 0 auto;
  position: relative;
}
.coeff-gauge {
  width: 100%;
  height: auto;
  margin-bottom: 1rem;
}
.coeff-gauge-info {
  display: flex;
  justify-content: space-between;
  font-size: .75rem;
  color: var(--muted);
  margin-top: .5rem;
}
.coeff-gauge-info span {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .2rem;
}
.coeff-gauge-info .pip {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
}
.coeff-gauge-info .pip-vive { background: var(--red); }
.coeff-gauge-info .pip-fort { background: var(--amber); }
.coeff-gauge-info .pip-moyen { background: var(--cyan); }
.coeff-gauge-info .pip-morte { background: var(--green); }

/* ── Barre de progression de la marée ── */
.tide-progress-container {
  width: 100%;
  margin: 1rem 0;
}
.tide-progress {
  height: 20px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  overflow: hidden;
  position: relative;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.2);
}
.tide-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #06b6d4, #0891b2);
  border-radius: 10px;
  transition: width 0.5s ease;
  position: relative;
}
.tide-progress-bar::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 2px;
  background: rgba(255, 255, 255, 0.3);
}
.tide-progress-info {
  display: flex;
  justify-content: space-between;
  font-size: .75rem;
  color: var(--muted);
  margin-top: .3rem;
}
.tide-progress-info span {
  display: flex;
  align-items: center;
  gap: .3rem;
}
.tide-progress-info .arrow {
  font-size: .8rem;
  animation: pulse 1.5s infinite alternate;
}

/* ── Marées du jour ── */
.tides-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 1rem;
  height: 100%;
  align-content: center;
}
.tide-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.1rem 1rem;
  text-align: center;
  position: relative;
  transition: all .2s ease;
  overflow: hidden;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.tide-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
  border-color: var(--cyan);
}
.tide-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--cyan), transparent);
  opacity: 0;
  transition: opacity .2s ease;
}
.tide-item:hover::before {
  opacity: 1;
}
.tide-badge {
  font-size: .62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .1em;
  padding: .2rem .6rem;
  border-radius: 99px;
  margin-bottom: .5rem;
  display: inline-block;
  background: rgba(6, 182, 212, 0.2);
  color: var(--cyan);
  border: 1px solid rgba(6, 182, 212, 0.3);
}
.tide-high .tide-badge {
  background: rgba(6, 182, 212, 0.2);
  color: var(--cyan);
  border: 1px solid rgba(6, 182, 212, 0.3);
  animation: badgePulse 2s infinite;
}
.tide-low .tide-badge {
  background: rgba(245, 158, 11, 0.2);
  color: var(--amber);
  border: 1px solid rgba(245, 158, 11, 0.3);
}
@keyframes badgePulse {
  0% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(6, 182, 212, 0); }
  100% { box-shadow: 0 0 0 0 rgba(6, 182, 212, 0); }
}
.tide-time {
  font-size: 1.6rem;
  font-weight: 800;
  font-family: var(--mono);
  line-height: 1;
  color: var(--text);
  background: linear-gradient(135deg, #e8f4ff, var(--text));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.tide-height {
  font-size: .82rem;
  color: var(--muted);
  margin-top: .3rem;
  font-family: var(--mono);
}
.tide-high .tide-height {
  color: var(--cyan);
  font-weight: 600;
}
.tide-low .tide-height {
  color: var(--amber);
  font-weight: 600;
}

/* ── Courbe ── */
.curve-wrap {
  position: relative;
  width: 100%;
  overflow: hidden;
  border-radius: var(--r);
  background: var(--surface2);
  padding: 1rem;
  box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
}
.curve-svg {
  width: 100%;
  height: auto;
  display: block;
  filter:
svg {
    drop-shadow(0 0 8px rgba(6, 182, 212, 0.3));
  }
  .curve-wrap .axis-hours {
    display: flex;
    justify-content: space-between;
    padding: .4rem 0 0;
    font-size: .7rem;
    color: var(--muted);
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

  /* Animation de la courbe */
  .curve-path {
    stroke-dasharray: 1000;
    stroke-dashoffset: 1000;
    animation: drawCurve 2s ease-out forwards;
  }
  @keyframes drawCurve {
    to { stroke-dashoffset: 0; }
  }

  /* Animation des points de marée */
  .tide-point {
    opacity: 0;
    animation: fadeIn 0.5s ease-out forwards;
  }
  @keyframes fadeIn {
    to { opacity: 1; }
  }
  .tide-point:nth-child(1) { animation-delay: 0.2s; }
  .tide-point:nth-child(2) { animation-delay: 0.4s; }
  .tide-point:nth-child(3) { animation-delay: 0.6s; }
  .tide-point:nth-child(4) { animation-delay: 0.8s; }

  /* Animation du curseur maintenant */
  .now-cursor {
    opacity: 0;
    animation: fadeIn 0.5s ease-out 1s forwards;
  }
  .now-text {
    opacity: 0;
    animation: fadeIn 0.5s ease-out 1.2s forwards;
  }

  /* ── Tableau 7 jours ── */
  .days-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: var(--r);
    overflow: hidden;
    background: var(--surface2);
  }
  .days-table tr {
    border-bottom: 1px solid var(--border);
    transition: background .2s ease;
  }
  .days-table tr:last-child {
    border-bottom: none;
  }
  .days-table tr:hover td {
    background: var(--surface3);
  }
  .days-table td {
    padding: .85rem 1rem;
    font-size: .85rem;
    vertical-align: middle;
    position: relative;
  }
  .td-day {
    font-weight: 700;
    min-width: 120px;
    color: var(--text);
    position: relative;
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
    min-width: 110px;
    text-align: center;
  }
  .coeff-pip {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    font-family: var(--mono);
    font-weight: 700;
    font-size: .9rem;
    padding: .3rem .6rem;
    border-radius: 8px;
    background: rgba(6, 182, 212, 0.1);
  }
  .pip {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 4px rgba(0, 0, 0, 0.2);
  }
  .pip-vive { background: var(--red); }
  .pip-fort { background: var(--amber); }
  .pip-moyen { background: var(--cyan); }
  .pip-morte { background: var(--green); }
  .td-tides {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    justify-content: flex-end;
  }
  .mini-tide {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .3rem .7rem;
    min-width: 68px;
    transition: all .2s ease;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  }
  .mini-tide:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    border-color: var(--cyan);
  }
  .mini-tide-type {
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: .2rem;
  }
  .mini-tide.high .mini-tide-type {
    color: var(--cyan);
    background: rgba(6, 182, 212, 0.1);
    padding: .1rem .3rem;
    border-radius: 4px;
  }
  .mini-tide.low .mini-tide-type {
    color: var(--amber);
    background: rgba(245, 158, 11, 0.1);
    padding: .1rem .3rem;
    border-radius: 4px;
  }
  .mini-tide-time {
    font-size: .82rem;
    font-weight: 700;
    font-family: var(--mono);
    color: var(--text);
  }
  .mini-tide-h {
    font-size: .7rem;
    color: var(--muted);
    font-family: var(--mono);
  }

  /* ── Graphique 30 jours ── */
  .graph-30days {
    background: var(--surface2);
    border-radius: var(--r);
    padding: 1.5rem;
    margin-top: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }
  .graph-30days-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }
  .graph-30days-title {
    font-size: .82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
  }
  .graph-30days-subtitle {
    font-size: .75rem;
    color: var(--muted);
    margin-top: .2rem;
  }
  .graph-30days-container {
    width: 100%;
    overflow-x: auto;
    padding-bottom: .5rem;
  }
  .graph-30days-svg {
    min-width: 320px;
    width: 100%;
    height: 140px;
  }

  /* ── Section "Bon à savoir" ── */
  .know-card {
    background: var(--surface2);
    border-radius: var(--r);
    padding: 1.5rem;
    margin-top: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }
  .know-card-header {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--cyan);
    margin-bottom: 1rem;
    position: relative;
    padding-bottom: .5rem;
  }
  .know-card-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 2px;
    background: var(--cyan);
  }
  .know-content {
    display: flex;
    gap: 2rem;
    align-items: flex-start;
  }
  .know-image {
    flex: 1;
    min-width: 200px;
    border-radius: var(--r);
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }
  .know-image img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.5s ease;
  }
  .know-image:hover img {
    transform: scale(1.05);
  }
  .know-text {
    flex: 2;
    font-size: .95rem;
    line-height: 1.6;
  }
  .know-text p {
    margin-bottom: 1rem;
  }
  .know-text strong {
    color: var(--cyan);
    font-weight: 600;
  }
  .know-fact {
    background: rgba(6, 182, 212, 0.1);
    border-left: 3px solid var(--cyan);
    padding: 1rem;
    margin: 1.5rem 0;
    border-radius: 0 var(--r) var(--r) 0;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  /* ── API Status ── */
  .api-status {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    padding: .3rem .6rem;
    border-radius: 6px;
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    background: var(--surface);
    border: 1px solid var(--border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    z-index: 100;
  }
  .api-status.success {
    color: var(--green);
    border-color: rgba(16, 185, 129, 0.3);
  }
  .api-status.error {
    color: var(--red);
    border-color: rgba(239, 68, 68, 0.3);
  }
  .api-status.warning {
    color: var(--amber);
    border-color: rgba(245, 158, 11, 0.3);
  }

  /* ── Carte interactive ── */
  .map-card {
    background: var(--surface2);
    border-radius: var(--r);
    padding: 1.5rem;
    margin-top: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  }
  .map-card-header {
    font-size: .82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }
  .interactive-map {
    width: 100%;
    height: auto;
    border-radius: var(--r);
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }
  .port-marker {
    transition: all 0.3s ease;
    cursor: pointer;
  }
  .port-marker:hover {
    r: 10;
    stroke-width: 3;
  }
  .port-marker.active {
    r: 10;
    stroke-width: 3;
    fill: var(--cyan);
  }
  .port-label {
    transition: opacity 0.3s ease;
    opacity: 0;
    pointer-events: none;
  }
  .port-marker:hover + .port-label,
  .port-marker.active + .port-label {
    opacity: 1;
  }

  /* ── Badge "Meilleur moment pour pêcher" ── */
  .fishing-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(245, 158, 11, 0.1);
    border-left: 3px solid var(--amber);
    padding: 1rem;
    border-radius: 0 var(--r) var(--r) 0;
    margin-top: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  .fishing-badge-icon {
    font-size: 2rem;
    animation: float 3s ease-in-out infinite;
  }
  .fishing-badge-content {
    flex: 1;
  }
  .fishing-badge-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--amber);
    margin-bottom: .3rem;
  }
  .fishing-badge-time {
    font-size: 1.5rem;
    font-weight: 800;
    font-family: var(--mono);
    color: var(--text);
    margin-bottom: .2rem;
  }
  .fishing-badge-info {
    font-size: .8rem;
    color: var(--muted);
  }

  /* ── Badge "Meilleur moment pour surfer" ── */
  .surfing-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(6, 182, 212, 0.1);
    border-left: 3px solid var(--cyan);
    padding: 1rem;
    border-radius: 0 var(--r) var(--r) 0;
    margin-top: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  .surfing-badge-icon {
    font-size: 2rem;
    animation: float 3s ease-in-out infinite;
  }
  .surfing-badge-content {
    flex: 1;
  }
  .surfing-badge-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--cyan);
    margin-bottom: .3rem;
  }
  .surfing-badge-time {
    font-size: 1.5rem;
    font-weight: 800;
    font-family: var(--mono);
    color: var(--text);
    margin-bottom: .2rem;
  }
  .surfing-badge-info {
    font-size: .8rem;
    color: var(--muted);
  }

  /* ── Badge "Meilleur moment pour naviguer" ── */
  .navigation-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(29, 78, 216, 0.1);
    border-left: 3px solid var(--blue);
    padding: 1rem;
    border-radius: 0 var(--r) var(--r) 0;
    margin-top: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }
  .navigation-badge-icon {
    font-size: 2rem;
    animation: float 3s ease-in-out infinite;
  }
  .navigation-badge-content {
    flex: 1;
  }
  .navigation-badge-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--blue);
    margin-bottom: .3rem;
  }
  .navigation-badge-time {
    font-size: 1.5rem;
    font-weight: 800;
    font-family: var(--mono);
    color: var(--text);
    margin-bottom: .2rem;
  }
  .navigation-badge-info {
    font-size: .8rem;
    color: var(--muted);
  }

  /* ── Footer ── */
  footer {
    text-align: center;
    padding: 2rem;
    font-size: .75rem;
    color: var(--muted);
    border-top: 1px solid var(--border);
    margin-top: 2rem;
    position: relative;
  }
  footer strong {
    color: var(--cyan);
    font-weight: 600;
  }
  footer a {
    color: var(--cyan);
    text-decoration: none;
    transition: color .2s ease;
  }
  footer a:hover {
    color: #06b6d4;
    text-decoration: underline;
  }
  footer::before {
    content: '';
    position: absolute;
   
top: -1px;
    left: 0;
    width: 100%;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--cyan), transparent);
  }

  /* ── Responsive ── */
  @media (max-width: 700px) {
    .top-grid { grid-template-columns: 1fr; }
    .hero-port { font-size: 2.2rem; }
    .main { padding: 1rem; }
    .hero { padding: 1.5rem 1rem 0; }
    .port-nav-inner { padding: 0 1rem; }
    .coeff-number { font-size: 3.5rem; }
    .tide-time { font-size: 1.3rem; }
    .hero-time { font-size: 1.5rem; }
    .know-content { flex-direction: column; }
    .know-image { min-width: 100%; }
  }

  /* Animations supplémentaires */
  @keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
  }
  .float-animation {
    animation: float 3s ease-in-out infinite;
  }
</style>
</head>
<body>

<!-- ── HERO ──────────────────────────────────────────────────────────────────── -->
<header class="hero">
  <div class="hero-content">
    <div class="hero-top">
      <div>
        <div class="site-brand"><?= SITE_NAME ?> · v<?= VERSION ?></div>
        <h1 class="hero-port float-animation"><?= esc($port['icon']) ?> <?= esc($port['name']) ?></h1>
        <p class="hero-region"><?= esc($port['region']) ?> — <?= esc($port['desc']) ?></p>
      </div>
      <div class="hero-date">
        <div class="hero-time" id="live-time"><?= date('H:i:s') ?></div>
        <div><?= esc($date_label) ?></div>
      </div>
    </div>
  </div>
  <!-- Vagues animées améliorées -->
  <div class="waves-wrap">
    <svg viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path class="wave-path" d="M0,60 C150,100 300,20 450,60 C600,100 750,20 900,60 C1050,100 1200,20 1350,60 C1500,100 1650,20 1800,60 C1950,100 2100,20 2250,60 C2400,100 2550,20 2700,60 C2850,100 2880,60 2880,60 L0,60 Z" />
      <path class="wave-path" d="M0,50 C200,90 400,10 600,50 C800,90 1000,10 1200,50 C1400,90 1600,10 1800,50 C2000,90 2200,10 2400,50 C2600,90 2800,50 2880,50 L0,50 Z" style="animation-delay: -2s" />
      <path class="wave-path" d="M0,40 C250,80 500,0 750,40 C1000,80 1250,0 1500,40 C1750,80 2000,0 2250,40 C2500,80 2750,40 2880,40 L0,40 Z" style="animation-delay: -4s" />
    </svg>
  </div>
</header>

<!-- ── NAVIGATION PORTS ───────────────────────────────────────────────────────── -->
<nav class="port-nav">
  <div class="port-nav-inner">
    <?php foreach ($PORTS as $k => $p): ?>
    <a href="?port=<?= esc($k) ?>" class="port-btn <?= $k === $port_key ? 'active' : '' ?>">
      <?= $p['icon'] ?> <?= esc($p['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- ── CONTENU ───────────────────────────────────────────────────────────────── -->
<main class="main">

  <!-- Carte interactive -->
  <div class="card glass-card map-card">
    <div class="map-card-header">🌊 Carte des ports de Loire-Atlantique</div>
    <?= $interactiveMap ?>
  </div>

  <!-- Coefficient + Marées du jour -->
  <div class="top-grid">
    <!-- Coefficient -->
    <div class="card glass-card">
      <div class="card-header">📊 Coefficient de marée</div>
      <div class="coeff-card coeff-<?= coeffClass($today_data['coeff']) ?>">
        <div class="coeff-gauge-container">
          <?= $coeffGauge ?>
          <div class="coeff-gauge-info">
            <span><span class="pip pip-morte"></span>Mortes</span>
            <span><span class="pip pip-moyen"></span>Moyen</span>
            <span><span class="pip pip-fort"></span>Fort</span>
            <span><span class="pip pip-vive"></span>Vives</span>
          </div>
        </div>
        <div class="coeff-number"><?= $today_data['coeff'] ?></div>
        <div class="coeff-label"><?= coeffLabel($today_data['coeff']) ?></div>
        <div class="coeff-title">Aujourd'hui</div>
      </div>
    </div>

    <!-- Marées du jour -->
    <div class="card glass-card">
      <div class="card-header">🌊 Marées d'aujourd'hui</div>
      <div class="card-body">
        <div class="tides-grid">
          <?php if (!empty($today_data['tides'])): ?>
            <?php foreach ($today_data['tides'] as $t): ?>
            <div class="tide-item tide-<?= $t['type'] ?>">
              <div class="tide-badge"><?= $t['type'] === 'high' ? 'Pleine mer' : 'Basse mer' ?></div>
              <div class="tide-time"><?= esc($t['time']) ?></div>
              <div class="tide-height"><?= number_format($t['height'], 2) ?> m</div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="color:var(--muted);font-size:.85rem;grid-column:1/-1;text-align:center;padding:1rem;">
              Aucune donnée de marée disponible pour aujourd'hui.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Barre de progression de la marée actuelle -->
  <div class="card glass-card">
    <div class="card-header">🌊 Niveau de la mer maintenant</div>
    <div class="card-body">
      <?= generateTideProgressBar($today_data, $port) ?>
    </div>
  </div>

  <!-- Badges d'activité -->
  <div class="activity-badges">
    <?= $fishingBadge ?>
    <?= $surfingBadge ?>
    <?= $navigationBadge ?>
  </div>

  <!-- Courbe de marée -->
  <div class="card glass-card">
    <div class="card-header">📈 Courbe de marée — Aujourd'hui</div>
    <div class="card-body">
      <div class="curve-wrap">
        <svg class="curve-svg" viewBox="0 0 960 160" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="curveGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#06b6d4" stop-opacity=".35"/>
              <stop offset="100%" stop-color="#06b6d4" stop-opacity=".02"/>
            </linearGradient>
            <linearGradient id="lineGrad" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0%" stop-color="#0891b2"/>
              <stop offset="50%" stop-color="#06b6d4"/>
              <stop offset="100%" stop-color="#0891b2"/>
            </linearGradient>
            <filter id="glow" x="-30%" y="-30%" width="160%" height="160%">
              <feGaussianBlur stdDeviation="4" result="blur"/>
              <feComposite in="SourceGraphic" in2="blur" operator="over"/>
            </filter>
          </defs>

          <!-- Grille horizontale -->
          <?php
          $rng_svg = $port['range_high'] - $port['range_low'];
          $steps = 4;
          for ($s = 0; $s <= $steps; $s++):
              $y_g = round(14 + $s / $steps * 132, 1);
              $h_g = round($port['range_high'] - $s / $steps * $rng_svg, 1);
          ?>
          <line x1="0" y1="<?= $y_g ?>" x2="960" y2="<?= $y_g ?>" stroke="rgba(255,255,255,.05)" stroke-width="1"/>
          <text x="4" y="<?= $y_g - 3 ?>" font-size="9" fill="rgba(107,140,170,.7)" font-family="var(--mono)"><?= $h_g ?>m</text>
          <?php endfor; ?>

          <!-- Lignes verticales (heures) -->
          <?php for ($hr = 0; $hr <= 24; $hr += 3): ?>
          <line x1="<?= round($hr / 24 * 960) ?>" y1="0" x2="<?= round($hr / 24 * 960) ?>" y2="160"
                stroke="rgba(255,255,255,.04)" stroke-width="1"/>
          <?php endfor; ?>

          <!-- Aire sous la courbe -->
          <path d="<?= $svg_path ?> L960,160 L0,160 Z" fill="url(#curveGrad)"/>

          <!-- Ligne de la courbe -->
          <path class="curve-path" d="<?= $svg_path ?>" fill="none" stroke="url(#lineGrad)" stroke-width="2.5" stroke-linejoin="round"/>

          <!-- Points des marées -->
          <?php foreach ($today_data['tides'] as $i => $t):
              $tx = round(($t['ts'] - $today_ts) / 86400 * 960, 1);
              $ty = round(14 + (1 - ($t['height'] - $port['range_low']) / $rng_svg) * 132, 1);
              $is_high = $t['type'] === 'high';
          ?>
          <g class="tide-point" style="animation-delay: <?= $i * 0.2 ?>s">
            <circle cx="<?= $tx ?>" cy="<?= $ty ?>" r="5" fill="<?= $is_high ? '#06b6d4' : '#f59e0b' ?>" stroke="var(--bg)" stroke-width="2"/>
            <text x="<?= $tx ?>" y="<?= $is_high ? $ty - 9 : $ty + 16 ?>"
                  font-size="9" fill="<?= $is_high ? '#06b6d4' : '#f59e0b' ?>"
                  text-anchor="middle" font-family="var(--mono)"><?= $t['time'] ?></text>
          </g>
          <?php endforeach; ?>

          <!-- Curseur "maintenant" -->
          <?php if ($now_frac > 0 && $now_frac < 1): ?>
          <g class="now-cursor">
            <line x1="<?= $now_x ?>" y1="0" x2="<?= $now_x ?>" y2="160"
                  stroke="#ffffff" stroke-width="1" stroke-dasharray="3,3" opacity=".4"/>
            <circle cx="<?= $now_x ?>" cy="<?= $now_y ?>" r="6" fill="#ffffff" opacity=".9"/>
            <circle cx="<?= $now_x ?>" cy="<?= $now_y ?>" r="3" fill="var(--cyan)"/>
          </g>
          <text class="now-text" x="<?= $now_x + 8 ?>" y="<?= $now_y - 6 ?>"
                font-size="9" fill="rgba(255,255,255,.7)" font-family="var(--mono)">maintenant</text>
          <?php endif; ?>
        </svg>

        <!-- Labels des heures -->
        <div class="axis-hours">
          <?php for ($hr = 0; $hr <= 24; $hr += 3): ?>
          <span><?= $hr ?>h</span>
          <?php endfor; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Graphique 30 jours -->
  <div class="card glass-card graph-30days">
    <div class="graph-30days-header">
      <div>
        <div class="graph-30days-title">📊 Évolution du coefficient sur 30 jours</div>
        <div class="graph-30days-subtitle">Prévisions des coefficients de marée</div>
      </div>
    </div>
    <div class="graph-30days-container">
      <?= $graph30Days ?>
    </div>
  </div>

  <!-- Section "Bon à savoir" -->
  <div class="card glass-card know-card">
    <div class="know-card-header">🌊 Bon à savoir sur les marées</div>
    <div class="know-content">
      <div class="know-image">
        <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80" alt="Marée en Loire-Atlantique">
      </div>
      <div class="know-text">
        <p><strong>Le coefficient de marée</strong> est un indicateur qui mesure l'intensité des marées. Il varie entre 0 et 120, où 120 correspond à des marées particulièrement fortes.</p>

        <div class="know-fact">
          <p>Les marées sont principalement causées par la gravité combinée de la Lune et du Soleil, ainsi que par les forces centrifuges générées par la rotation de la Terre.</p>
        </div>

        <p>Lorsqu'un coefficient est élevé (supérieur à 70), on parle de <strong>marées vives</strong>. Ces marées peuvent causer des inondations côtières, surtout lors des tempêtes.</p>

        <div class="know-fact">
          <p>Le coefficient de marée suit un cycle lunaire d'environ 29,5 jours, correspondant aux phases de la Lune. Les pleines lunes et les nouvelles lunes correspondent généralement aux coefficients les plus élevés.</p>
        </div>

        <p>Les marées sont mesurées en mètres par rapport au niveau moyen de la mer. La hauteur de la mer varie tout au long de la journée en fonction des marées.</p>

        <div class="know-fact">
          <p>Les ports comme <?= esc($port['name']) ?>, situé en <?= esc($port['region']) ?>, ont des caractéristiques spécifiques de marées en raison de leur emplacement géographique et de la configuration du fond marin.</p>
        </div>

        <p>Pour naviguer en sécurité dans les zones côtières, il est important de connaître les horaires et les hauteurs des marées, surtout lors des périodes de fortes marées.</p>
      </div>
    </div>
  </div>

  <!-- 7 jours -->
  <div class="card glass-card">
    <div class="card-header">📅 Prévisions — 7 jours</div>
    <table class="days-table">
      <?php foreach ($days as $i => $day): ?>
      <tr>
        <td class="td-day"><?= esc($day['label']) ?></td>
        <td class="td-coeff">
          <div class="coeff-pip">
            <span class="pip pip-<?= coeffClass($day['coeff']) ?>"></span>
            <span style="color:var(--text)"><?= $day['coeff'] ?></span>
          </div>
        </td>
        <td>
          <div class="td-tides">
            <?php if (!empty($day['tides'])): ?>
              <?php foreach ($day['tides'] as $t): ?>
              <div class="mini-tide <?= $t['type'] ?>">
                <span class="mini-tide-type"><?= $t['type'] === 'high' ? 'PM' : 'BM' ?></span>
                <span class="mini-tide-time"><?= esc($t['time']) ?></span>
                <span class="mini-tide-h"><?= number_format($t['height'], 1) ?>m</span>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="color:var(--muted);font-size:.75rem;">Aucune donnée</div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

</main>

<!-- Statut API -->
<div class="api-status <?= isset($apiData['status']) && $apiData['status'] === 'error' ? 'error' : 'success' ?>">
  <?= isset($apiData['status']) && $apiData['status'] === 'error' ? 'Données simulées' : 'Données API' ?>
</div>

<footer>
  <strong><?= SITE_NAME ?></strong> v<?= VERSION ?> — Données marées pour <?= esc($port['name']) ?><br>
  <?php if (isset($apiData['status']) && $apiData['status'] === 'error'): ?>
    Mode démonstration (données simulées). <a href="https://www.worldtides.info/developer" target="_blank">Obtenez une clé API</a> pour des données réelles.
  <?php else: ?>
    Données fournies par <a href="https://www.worldtides.info" target="_blank">WorldTides</a>
  <?php endif; ?><br>
  Généré le <?= date('d/m/Y à H:i:s') ?>
</footer>

<script>
// Horloge en temps réel
function tick() {
  const d = new Date();
  const h = String(d.getHours()).padStart(2,'0');
  const m = String(d.getMinutes()).padStart(2,'0');
  const s = String(d.getSeconds()).padStart(2,'0');
  const el = document.getElementById('live-time');
  if (el) el.textContent = h + ':' + m + ':' + s;
}
setInterval(tick, 1000);
tick();

// Animation des vagues
document.addEventListener('DOMContentLoaded', function() {
  const waves = document.querySelectorAll('.wave-path');
  waves.forEach((wave, index) => {
    wave.style.animationDelay = `-${index * 2}s`;
  });

  // Animation de la barre de progression de la marée
  const tideProgressBar = document.querySelector('.tide-progress-bar');
  if (tideProgressBar) {
    const width = tideProgressBar.style.width;
    tideProgressBar.style.width = '0%';
    setTimeout(() => {
      tideProgressBar.style.width = width;
    }, 100);
  }
});

// Animation des cartes au survol
const cards = document.querySelectorAll('.card');
cards.forEach(card => {
  card.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-5px)';
  });
  card.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0)';
  });
});

// Animation de la jauge du coefficient
function animateGauge() {
  const gauge = document.querySelector('.coeff-gauge');
  if (!gauge) return;

  const path = gauge.querySelector('path[d^="M50,50"]');
  if (!path) return;

  const length = path.getTotalLength();
  path.style.strokeDasharray = length;
  path.style.strokeDashoffset = length;

  let start = null;
  const duration = 2000;

  function step(timestamp) {
    if (!start) start = timestamp;
    const progress = timestamp - start;
    const percent = Math.min(progress / duration, 1);

    path.style.strokeDashoffset = length * (1 - percent);

    if (percent < 1) {
      window.requestAnimationFrame(step);
    }
  }

  window.requestAnimationFrame(step);
}

// Animation de la courbe de marée
function animateTideCurve() {
  const curvePath = document.querySelector('.curve-path');
  if (!curvePath) return;

  const length = curvePath.getTotalLength();
  curvePath.style.strokeDasharray = length;
  curvePath.style.strokeDashoffset = length;

  let start = null;
  const duration = 2000;

  function step(timestamp) {
    if (!start) start = timestamp;
    const progress = timestamp - start;
    const percent = Math.min(progress / duration, 1);

    curvePath.style.strokeDashoffset = length * (1 - percent);

    if (percent < 1) {
      window.requestAnimationFrame(step);
    }
  }

  window.requestAnimationFrame(step);
}

// Animation des points de marée
function animateTidePoints() {
  const points = document.querySelectorAll('.tide-point');
  points.forEach((point, index) => {
    point.style.animationDelay = `${index * 0.2}s`;
  });
}

// Animation de la carte interactive
function animateInteractiveMap() {
  const markers = document.querySelectorAll('.port-marker');
  markers.forEach((marker, index) => {
    marker.addEventListener('mouseenter', function() {
      const label = this.nextElementSibling;
      if (label && label.classList.contains('port-label')) {
        label.style.opacity = '1';
      }
    });

    marker.addEventListener('mouseleave', function() {
      if (!this.classList.contains('active')) {
        const label = this.nextElementSibling;
        if (label && label.classList.contains('port-label')) {
          label.style.opacity = '0';
        }
      }
    });
  });
}

// Animation des badges d'activité
function animateActivityBadges() {
  const badges = document.querySelectorAll('.fishing-badge, .surfing-badge, .navigation-badge');
  badges.forEach((badge, index) => {
    badge.style.animationDelay = `${index * 0.3}s`;
  });
}

// Appeler les animations après le chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
  animateGauge();
  animateTideCurve();
  animateTidePoints();
  animateInteractiveMap();
  animateActivityBadges();
});
</script>
</body>
</html>