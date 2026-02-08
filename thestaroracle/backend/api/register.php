<?php
/**
 * Star Oracle - User Registration API
 * Handles user and researcher registration
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
$requiredFields = ['name', 'email', 'password', 'role'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        jsonResponse(['error' => "Missing required field: $field"], 400);
    }
}

$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$role = sanitizeInput($input['role']);

// Validate email
if (!isValidEmail($email)) {
    jsonResponse(['error' => 'Invalid email format'], 400);
}

// Validate role
$allowedRoles = ['user', 'researcher'];
if (!in_array($role, $allowedRoles)) {
    jsonResponse(['error' => 'Invalid role. Must be "user" or "researcher"'], 400);
}

// Validate password strength
if (strlen($password) < 8) {
    jsonResponse(['error' => 'Password must be at least 8 characters long'], 400);
}

if (!preg_match('/[A-Z]/', $password)) {
    jsonResponse(['error' => 'Password must contain at least one uppercase letter'], 400);
}

if (!preg_match('/[a-z]/', $password)) {
    jsonResponse(['error' => 'Password must contain at least one lowercase letter'], 400);
}

if (!preg_match('/[0-9]/', $password)) {
    jsonResponse(['error' => 'Password must contain at least one number'], 400);
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    jsonResponse(['error' => 'Database connection failed'], 500);
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Email already registered'], 409);
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Generate verification token
    $verificationToken = generateToken(32);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert user
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, verified, verification_token) 
         VALUES (?, ?, ?, ?, 0, ?)"
    );
    $stmt->execute([$name, $email, $passwordHash, $role, $verificationToken]);
    $userId = $pdo->lastInsertId();
    
    // If researcher, handle researcher-specific fields
    if ($role === 'researcher') {
        $researchId = $input['research_id'] ?? null;
        $organization = $input['organization'] ?? null;
        
        if (empty($researchId)) {
            // Generate a research ID if not provided
            $researchId = 'RSR-' . strtoupper(substr(md5(uniqid()), 0, 8));
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO researchers (user_id, research_id, organization) VALUES (?, ?, ?)"
        );
        $stmt->execute([$userId, $researchId, $organization]);
    }
    
    // Create default user preferences
    $stmt = $pdo->prepare(
        "INSERT INTO user_preferences (user_id, email_alerts, push_notifications) VALUES (?, 1, 1)"
    );
    $stmt->execute([$userId]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'Registration successful. Please check your email to verify your account.',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ]
    ];
    
    if ($role === 'researcher') {
        $response['user']['research_id'] = $researchId;
    }
    
    // In a production environment, send verification email here
    // For demo purposes, we'll include the verification link in the response
    $response['verification_link'] = "/backend/api/verify_email.php?token=" . $verificationToken;
    
    jsonResponse($response, 201);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Registration error: " . $e->getMessage());
    jsonResponse(['error' => 'Registration failed. Please try again.'], 500);
}
