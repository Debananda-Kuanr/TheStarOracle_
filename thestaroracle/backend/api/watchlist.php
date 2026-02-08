<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/db.php';
require_once '../config/auth.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $user = authenticateUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $user_id = $user['id'];
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($method === 'GET' && $action === 'list') {
        $stmt = $pdo->prepare("
            SELECT * FROM watchlist 
            WHERE user_id = ? 
            ORDER BY added_at DESC
        ");
        $stmt->execute([$user_id]);
        $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $watchlist]);
        
    } elseif ($method === 'POST' && $action === 'add') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['asteroid_id']) || !isset($data['asteroid_name'])) {
            throw new Exception('Missing required fields: asteroid_id and asteroid_name');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO watchlist (user_id, asteroid_id, asteroid_name) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE added_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$user_id, $data['asteroid_id'], $data['asteroid_name']]);
        
        echo json_encode(['success' => true, 'message' => 'Added to watchlist']);
        
    } elseif ($method === 'DELETE' && $action === 'remove') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['asteroid_id'])) {
            throw new Exception('Missing asteroid_id');
        }
        
        $stmt = $pdo->prepare("
            DELETE FROM watchlist 
            WHERE user_id = ? AND asteroid_id = ?
        ");
        $stmt->execute([$user_id, $data['asteroid_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Removed from watchlist']);
        
    } else {
        throw new Exception('Invalid request');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
