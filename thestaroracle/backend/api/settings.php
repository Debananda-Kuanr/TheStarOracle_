<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$settings) {
            $stmt = $pdo->prepare("
                INSERT INTO user_preferences (user_id, email_alerts, sms_alerts, push_notifications) 
                VALUES (?, 1, 0, 1)
            ");
            $stmt->execute([$user_id]);
            
            $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $settings]);
        
    } elseif ($method === 'POST' || $method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email_alerts = $data['email_alerts'] ?? 1;
        $sms_alerts = $data['sms_alerts'] ?? 0;
        $push_notifications = $data['push_notifications'] ?? 1;
        
        $stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE user_preferences 
                SET email_alerts = ?, sms_alerts = ?, push_notifications = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$email_alerts, $sms_alerts, $push_notifications, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO user_preferences (user_id, email_alerts, sms_alerts, push_notifications) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $email_alerts, $sms_alerts, $push_notifications]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Settings updated']);
        
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
