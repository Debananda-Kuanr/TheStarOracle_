<?php
/**
 * Star Oracle - Researcher API
 * Handles researcher-specific operations: notes, sessions, data export, watchlist
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Authenticate user
    $user = authenticateUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Require researcher role
    if ($user['role'] !== 'researcher' && $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Researcher access required']);
        exit;
    }

    $user_id = $user['id'];
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    // Get researcher record
    $stmt = $pdo->prepare("SELECT * FROM researchers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $researcher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$researcher) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Researcher profile not found']);
        exit;
    }

    $researcher_id = $researcher['id'];

    // =============================================
    // ROUTE: Get researcher profile
    // =============================================
    if ($method === 'GET' && $action === 'profile') {
        echo json_encode([
            'success' => true,
            'data' => [
                'user' => $user,
                'researcher' => $researcher
            ]
        ]);
        exit;
    }

    // =============================================
    // ROUTE: Get research notes
    // =============================================
    if ($method === 'GET' && $action === 'notes') {
        $asteroid_id = $_GET['asteroid_id'] ?? null;

        if ($asteroid_id) {
            $stmt = $pdo->prepare("SELECT * FROM research_notes WHERE researcher_id = ? AND asteroid_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$researcher_id, $asteroid_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM research_notes WHERE researcher_id = ? ORDER BY updated_at DESC LIMIT 50");
            $stmt->execute([$researcher_id]);
        }
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $notes]);
        exit;
    }

    // =============================================
    // ROUTE: Create/update research note
    // =============================================
    if ($method === 'POST' && $action === 'notes') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['asteroid_id']) || empty($data['title']) || empty($data['content'])) {
            throw new Exception('Missing required fields: asteroid_id, title, content');
        }

        $note_id = $data['note_id'] ?? null;
        $risk_override = isset($data['risk_override']) ? (int)$data['risk_override'] : null;

        if ($note_id) {
            // Update existing note
            $stmt = $pdo->prepare("UPDATE research_notes SET title = ?, content = ?, risk_override = ? WHERE id = ? AND researcher_id = ?");
            $stmt->execute([$data['title'], $data['content'], $risk_override, $note_id, $researcher_id]);
            echo json_encode(['success' => true, 'message' => 'Note updated']);
        } else {
            // Create new note
            $stmt = $pdo->prepare("INSERT INTO research_notes (researcher_id, asteroid_id, title, content, risk_override) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$researcher_id, $data['asteroid_id'], $data['title'], $data['content'], $risk_override]);
            echo json_encode(['success' => true, 'message' => 'Note created', 'note_id' => $pdo->lastInsertId()]);
        }
        exit;
    }

    // =============================================
    // ROUTE: Delete research note
    // =============================================
    if ($method === 'DELETE' && $action === 'notes') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['note_id'])) {
            throw new Exception('Missing note_id');
        }

        $stmt = $pdo->prepare("DELETE FROM research_notes WHERE id = ? AND researcher_id = ?");
        $stmt->execute([$data['note_id'], $researcher_id]);
        echo json_encode(['success' => true, 'message' => 'Note deleted']);
        exit;
    }

    // =============================================
    // ROUTE: Get session activity
    // =============================================
    if ($method === 'GET' && $action === 'sessions') {
        $stmt = $pdo->prepare("SELECT id, ip_address, user_agent, created_at, expires_at FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$user_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $sessions]);
        exit;
    }

    // =============================================
    // ROUTE: Get watchlist with notes count
    // =============================================
    if ($method === 'GET' && $action === 'watchlist') {
        $stmt = $pdo->prepare("
            SELECT w.*, 
                   (SELECT COUNT(*) FROM research_notes rn WHERE rn.asteroid_id = w.asteroid_id AND rn.researcher_id = ?) as notes_count
            FROM watchlist w 
            WHERE w.user_id = ? 
            ORDER BY w.added_at DESC
        ");
        $stmt->execute([$researcher_id, $user_id]);
        $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $watchlist]);
        exit;
    }

    // =============================================
    // ROUTE: Add to watchlist
    // =============================================
    if ($method === 'POST' && $action === 'watchlist') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['asteroid_id']) || empty($data['asteroid_name'])) {
            throw new Exception('Missing required fields: asteroid_id, asteroid_name');
        }

        $notes = $data['notes'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, asteroid_id, asteroid_name, notes) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE added_at = CURRENT_TIMESTAMP, notes = VALUES(notes)
        ");
        $stmt->execute([$user_id, $data['asteroid_id'], $data['asteroid_name'], $notes]);

        echo json_encode(['success' => true, 'message' => 'Added to watchlist']);
        exit;
    }

    // =============================================
    // ROUTE: Remove from watchlist
    // =============================================
    if ($method === 'DELETE' && $action === 'watchlist') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['asteroid_id'])) {
            throw new Exception('Missing asteroid_id');
        }

        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE user_id = ? AND asteroid_id = ?");
        $stmt->execute([$user_id, $data['asteroid_id']]);

        echo json_encode(['success' => true, 'message' => 'Removed from watchlist']);
        exit;
    }

    // =============================================
    // ROUTE: Export asteroid data (CSV or JSON)
    // =============================================
    if ($method === 'GET' && $action === 'export') {
        $format = $_GET['format'] ?? 'json';
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        // Fetch from NASA
        $apiUrl = NASA_NEO_BASE_URL . "?start_date={$startDate}&end_date={$endDate}&api_key=" . NASA_API_KEY;

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
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to fetch NASA data for export');
        }

        $nasaData = json_decode($response, true);
        $exportData = [];

        if (isset($nasaData['near_earth_objects'])) {
            foreach ($nasaData['near_earth_objects'] as $date => $asteroids) {
                foreach ($asteroids as $asteroid) {
                    $ca = $asteroid['close_approach_data'][0] ?? [];
                    $exportData[] = [
                        'id' => $asteroid['id'],
                        'name' => $asteroid['name'],
                        'is_hazardous' => $asteroid['is_potentially_hazardous_asteroid'] ? 'Yes' : 'No',
                        'diameter_min_km' => round($asteroid['estimated_diameter']['kilometers']['estimated_diameter_min'], 4),
                        'diameter_max_km' => round($asteroid['estimated_diameter']['kilometers']['estimated_diameter_max'], 4),
                        'close_approach_date' => $ca['close_approach_date'] ?? '',
                        'velocity_km_h' => isset($ca['relative_velocity']) ? round((float)$ca['relative_velocity']['kilometers_per_hour'], 2) : '',
                        'velocity_km_s' => isset($ca['relative_velocity']) ? round((float)$ca['relative_velocity']['kilometers_per_second'], 4) : '',
                        'miss_distance_km' => isset($ca['miss_distance']) ? round((float)$ca['miss_distance']['kilometers'], 2) : '',
                        'miss_distance_lunar' => isset($ca['miss_distance']) ? round((float)$ca['miss_distance']['lunar'], 4) : '',
                        'miss_distance_au' => isset($ca['miss_distance']) ? round((float)$ca['miss_distance']['astronomical'], 8) : '',
                        'orbiting_body' => $ca['orbiting_body'] ?? '',
                        'nasa_jpl_url' => $asteroid['nasa_jpl_url']
                    ];
                }
            }
        }

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="asteroid_data_' . $startDate . '_' . $endDate . '.csv"');

            if (!empty($exportData)) {
                $output = fopen('php://output', 'w');
                fputcsv($output, array_keys($exportData[0]));
                foreach ($exportData as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }
            exit;
        }

        echo json_encode(['success' => true, 'data' => $exportData, 'count' => count($exportData)]);
        exit;
    }

    // =============================================
    // ROUTE: Get alerts
    // =============================================
    if ($method === 'GET' && $action === 'alerts') {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        $stmt->execute([$user_id]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $alerts]);
        exit;
    }

    // =============================================
    // ROUTE: Dashboard stats
    // =============================================
    if ($method === 'GET' && $action === 'stats') {
        // Count watchlist items
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM watchlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $watchlistCount = $stmt->fetch()['count'];

        // Count research notes
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM research_notes WHERE researcher_id = ?");
        $stmt->execute([$researcher_id]);
        $notesCount = $stmt->fetch()['count'];

        // Count active sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE user_id = ? AND expires_at > NOW()");
        $stmt->execute([$user_id]);
        $sessionsCount = $stmt->fetch()['count'];

        // Count alerts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM alerts WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unreadAlerts = $stmt->fetch()['count'];

        echo json_encode([
            'success' => true,
            'data' => [
                'watchlist_count' => (int)$watchlistCount,
                'notes_count' => (int)$notesCount,
                'active_sessions' => (int)$sessionsCount,
                'unread_alerts' => (int)$unreadAlerts
            ]
        ]);
        exit;
    }

    // =============================================
    // ROUTE: Update NASA API key in session
    // =============================================
    if ($method === 'POST' && $action === 'apikey') {
        $data = json_decode(file_get_contents('php://input'), true);
        // We don't actually store custom API keys in DB for security
        // Just validate and return
        $apiKey = $data['api_key'] ?? '';

        if (empty($apiKey)) {
            throw new Exception('API key cannot be empty');
        }

        // Validate by making a test call
        $testUrl = "https://api.nasa.gov/neo/rest/v1/feed?start_date=" . date('Y-m-d') . "&end_date=" . date('Y-m-d') . "&api_key=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo json_encode(['success' => true, 'message' => 'API key is valid']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        }
        exit;
    }

    // No matching route
    throw new Exception('Invalid request: action=' . $action . ', method=' . $method);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
