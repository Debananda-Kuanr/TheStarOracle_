<?php
/**
 * Star Oracle - User Login API
 * Handles user authentication
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

handleCORS();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get input data
$input = getJSONInput();

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    jsonResponse(['error' => 'Email and password are required'], 400);
}

$email = sanitizeInput($input['email']);
$password = $input['password'];

// Validate email format
if (!isValidEmail($email)) {
    jsonResponse(['error' => 'Invalid email format'], 400);
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    jsonResponse(['error' => 'Database connection failed'], 500);
}

try {
    // Find user by email
    $stmt = $pdo->prepare(
        "SELECT id, name, email, password_hash, role, verified FROM users WHERE email = ?"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }
    
    // Check if account is verified (optional - can be removed for testing)
    // if (!$user['verified']) {
    //     jsonResponse(['error' => 'Please verify your email before logging in'], 403);
    // }
    
    // Create JWT token
    $tokenPayload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    $token = createJWT($tokenPayload);
    
    // Store session in database
    createSession($user['id'], $token);
    
    // Clean expired sessions periodically
    if (rand(1, 10) === 1) {
        cleanExpiredSessions();
    }
    
    // Return success response
    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'verified' => (bool)$user['verified']
        ],
        'expires_in' => JWT_EXPIRY
    ]);
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    jsonResponse(['error' => 'Login failed. Please try again.'], 500);
}
