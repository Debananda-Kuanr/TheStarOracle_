<?php
/**
 * Star Oracle - Researcher Login API
 * Handles researcher authentication with research ID verification
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
if (empty($input['email']) || empty($input['password']) || empty($input['research_id'])) {
    jsonResponse(['error' => 'Email, password, and research ID are required'], 400);
}

$email = sanitizeInput($input['email']);
$password = $input['password'];
$researchId = sanitizeInput($input['research_id']);

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
    // Find user by email with researcher role
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.password_hash, u.role, u.verified, 
                r.id as researcher_id, r.research_id, r.organization, r.specialization
         FROM users u
         INNER JOIN researchers r ON u.id = r.user_id
         WHERE u.email = ? AND u.role = 'researcher'"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Invalid credentials or account is not a researcher account'], 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }
    
    // Verify research ID
    if ($user['research_id'] !== $researchId) {
        jsonResponse(['error' => 'Invalid research ID'], 401);
    }
    
    // Create JWT token with researcher info
    $tokenPayload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'researcher_id' => $user['researcher_id'],
        'research_id' => $user['research_id']
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
        'message' => 'Researcher login successful',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'verified' => (bool)$user['verified'],
            'researcher' => [
                'researcher_id' => $user['researcher_id'],
                'research_id' => $user['research_id'],
                'organization' => $user['organization'],
                'specialization' => $user['specialization']
            ]
        ],
        'expires_in' => JWT_EXPIRY
    ]);
    
} catch (PDOException $e) {
    error_log("Researcher login error: " . $e->getMessage());
    jsonResponse(['error' => 'Login failed. Please try again.'], 500);
}
