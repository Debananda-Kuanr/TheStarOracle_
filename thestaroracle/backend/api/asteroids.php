<?php
/**
 * Star Oracle - NASA Asteroid Data API
 * Fetches and processes asteroid data from NASA NeoWs API
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

handleCORS();

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    jsonResponse(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
}

// NASA API request
$apiUrl = NASA_NEO_BASE_URL . "?start_date={$startDate}&end_date={$endDate}&api_key=" . NASA_API_KEY;

// Fetch data from NASA
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    jsonResponse(['success' => false, 'error' => 'Failed to fetch data from NASA API: ' . $error], 500);
}

if ($httpCode !== 200) {
    jsonResponse(['success' => false, 'error' => 'NASA API returned error code: ' . $httpCode], 500);
}

$data = json_decode($response, true);

if (!$data) {
    jsonResponse(['success' => false, 'error' => 'Failed to parse NASA API response'], 500);
}

// Process and format the data
$asteroids = [];
$stats = [
    'total_count' => $data['element_count'] ?? 0,
    'hazardous_count' => 0,
    'closest_asteroid' => null,
    'fastest_asteroid' => null,
    'largest_asteroid' => null
];

$closestDistance = PHP_FLOAT_MAX;
$highestVelocity = 0;
$largestDiameter = 0;

if (isset($data['near_earth_objects'])) {
    foreach ($data['near_earth_objects'] as $date => $dailyAsteroids) {
        foreach ($dailyAsteroids as $asteroid) {
            $closeApproach = $asteroid['close_approach_data'][0] ?? null;
            
            $processedAsteroid = [
                'id' => $asteroid['id'],
                'name' => $asteroid['name'],
                'nasa_jpl_url' => $asteroid['nasa_jpl_url'],
                'is_hazardous' => $asteroid['is_potentially_hazardous_asteroid'],
                'diameter' => [
                    'min_km' => $asteroid['estimated_diameter']['kilometers']['estimated_diameter_min'],
                    'max_km' => $asteroid['estimated_diameter']['kilometers']['estimated_diameter_max'],
                    'min_m' => $asteroid['estimated_diameter']['meters']['estimated_diameter_min'],
                    'max_m' => $asteroid['estimated_diameter']['meters']['estimated_diameter_max']
                ],
                'close_approach' => null
            ];
            
            if ($closeApproach) {
                $distance = (float)$closeApproach['miss_distance']['kilometers'];
                $velocity = (float)$closeApproach['relative_velocity']['kilometers_per_hour'];
                
                $processedAsteroid['close_approach'] = [
                    'date' => $closeApproach['close_approach_date'],
                    'date_full' => $closeApproach['close_approach_date_full'],
                    'velocity_kmh' => $velocity,
                    'velocity_kms' => (float)$closeApproach['relative_velocity']['kilometers_per_second'],
                    'distance_km' => $distance,
                    'distance_lunar' => (float)$closeApproach['miss_distance']['lunar'],
                    'distance_au' => (float)$closeApproach['miss_distance']['astronomical'],
                    'orbiting_body' => $closeApproach['orbiting_body']
                ];
                
                // Update statistics
                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $stats['closest_asteroid'] = [
                        'id' => $asteroid['id'],
                        'name' => $asteroid['name'],
                        'distance_km' => $distance,
                        'distance_lunar' => (float)$closeApproach['miss_distance']['lunar']
                    ];
                }
                
                if ($velocity > $highestVelocity) {
                    $highestVelocity = $velocity;
                    $stats['fastest_asteroid'] = [
                        'id' => $asteroid['id'],
                        'name' => $asteroid['name'],
                        'velocity_kmh' => $velocity,
                        'velocity_kms' => (float)$closeApproach['relative_velocity']['kilometers_per_second']
                    ];
                }
            }
            
            $avgDiameter = ($processedAsteroid['diameter']['min_km'] + $processedAsteroid['diameter']['max_km']) / 2;
            if ($avgDiameter > $largestDiameter) {
                $largestDiameter = $avgDiameter;
                $stats['largest_asteroid'] = [
                    'id' => $asteroid['id'],
                    'name' => $asteroid['name'],
                    'diameter_km' => $avgDiameter
                ];
            }
            
            if ($asteroid['is_potentially_hazardous_asteroid']) {
                $stats['hazardous_count']++;
            }
            
            // Calculate risk score (custom algorithm)
            $processedAsteroid['risk_score'] = calculateRiskScore($processedAsteroid);
            
            $asteroids[] = $processedAsteroid;
        }
    }
}

// Sort by risk score (highest first)
usort($asteroids, function($a, $b) {
    return $b['risk_score'] - $a['risk_score'];
});

jsonResponse([
    'success' => true,
    'date_range' => [
        'start' => $startDate,
        'end' => $endDate
    ],
    'stats' => $stats,
    'asteroids' => $asteroids
]);

/**
 * Calculate risk score for an asteroid
 * @param array $asteroid
 * @return int
 */
function calculateRiskScore($asteroid) {
    $score = 0;
    
    // Hazardous flag adds 40 points
    if ($asteroid['is_hazardous']) {
        $score += 40;
    }
    
    // Distance factor (closer = higher risk)
    if (isset($asteroid['close_approach']['distance_km'])) {
        $distance = $asteroid['close_approach']['distance_km'];
        if ($distance < 1000000) {
            $score += 30;
        } elseif ($distance < 5000000) {
            $score += 20;
        } elseif ($distance < 10000000) {
            $score += 10;
        }
    }
    
    // Size factor
    $avgDiameter = ($asteroid['diameter']['min_km'] + $asteroid['diameter']['max_km']) / 2;
    if ($avgDiameter > 1) {
        $score += 20;
    } elseif ($avgDiameter > 0.5) {
        $score += 15;
    } elseif ($avgDiameter > 0.1) {
        $score += 10;
    } elseif ($avgDiameter > 0.05) {
        $score += 5;
    }
    
    // Velocity factor
    if (isset($asteroid['close_approach']['velocity_kmh'])) {
        $velocity = $asteroid['close_approach']['velocity_kmh'];
        if ($velocity > 100000) {
            $score += 10;
        } elseif ($velocity > 50000) {
            $score += 5;
        }
    }
    
    return min($score, 100); // Cap at 100
}
