<?php
/**
 * Star Oracle - Authentication Configuration
 * JWT and Session management functions
 */

require_once __DIR__ . '/db.php';

// JWT Configuration
define('JWT_SECRET', 'star_oracle_jwt_secret_key_2024_very_secure');
define('JWT_EXPIRY', 86400); // 24 hours in seconds
define('SESSION_EXPIRY', 86400); // 24 hours

/**
 * Create JWT token
 * @param array $payload
 * @return string
 */
function createJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRY;
    $payload = json_encode($payload);
    
    $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64Payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
    $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
}

/**
 * Verify JWT token
 * @param string $token
 * @return array|false
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    list($base64Header, $base64Payload, $base64Signature) = $parts;
    
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, JWT_SECRET, true);
    $expectedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    if (!hash_equals($expectedSignature, $base64Signature)) {
        return false;
    }
    
    $payload = json_decode(base64_decode(strtr($base64Payload, '-_', '+/')), true);
    
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Get authorization header
 * @return string|null
 */
function getAuthorizationHeader() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('ucwords', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    return $headers;
}

/**
 * Get Bearer token from header
 * @return string|null
 */
function getBearerToken() {
    $headers = getAuthorizationHeader();
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Authenticate user from token
 * @return array|false
 */
function authenticateUser() {
    $token = getBearerToken();
    
    if (!$token) {
        return false;
    }
    
    $payload = verifyJWT($token);
    
    if (!$payload) {
        return false;
    }
    
    // Verify session in database
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if (!$session) {
        return false;
    }
    
    // Get user data
    $stmt = $pdo->prepare("SELECT id, name, email, role, verified FROM users WHERE id = ?");
    $stmt->execute([$payload['user_id']]);
    $user = $stmt->fetch();
    
    return $user ?: false;
}

/**
 * Create session in database
 * @param int $userId
 * @param string $token
 * @return bool
 */
function createSession($userId, $token) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_EXPIRY);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare(
        "INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)"
    );
    
    return $stmt->execute([$userId, $token, $ipAddress, $userAgent, $expiresAt]);
}

/**
 * Destroy session
 * @param string $token
 * @return bool
 */
function destroySession($token) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
    return $stmt->execute([$token]);
}

/**
 * Destroy all user sessions
 * @param int $userId
 * @return bool
 */
function destroyAllUserSessions($userId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

/**
 * Require authentication middleware
 */
function requireAuth() {
    $user = authenticateUser();
    
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized. Please login.'], 401);
    }
    
    return $user;
}

/**
 * Require specific role
 * @param array $allowedRoles
 */
function requireRole($allowedRoles) {
    $user = requireAuth();
    
    if (!in_array($user['role'], $allowedRoles)) {
        jsonResponse(['error' => 'Access denied. Insufficient permissions.'], 403);
    }
    
    return $user;
}

/**
 * Clean expired sessions
 */
function cleanExpiredSessions() {
    $pdo = getDBConnection();
    if ($pdo) {
        $pdo->exec("DELETE FROM sessions WHERE expires_at < NOW()");
    }
}
