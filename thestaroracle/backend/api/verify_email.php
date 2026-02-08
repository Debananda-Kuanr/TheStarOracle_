<?php
/**
 * Star Oracle - Email Verification API
 * Handles email verification via token
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

handleCORS();

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get token from query string or JSON body
$token = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? null;
} else {
    $input = getJSONInput();
    $token = $input['token'] ?? null;
}

if (empty($token)) {
    // If accessed via browser without token, show HTML message
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Email Verification - Star Oracle</title>
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; 
                       align-items: center; height: 100vh; margin: 0; background: #0a0a1a; color: white; }
                .container { text-align: center; padding: 40px; background: rgba(255,255,255,0.1); 
                             border-radius: 12px; }
                .error { color: #ff6b6b; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="error">Verification Failed</h1>
                <p>No verification token provided.</p>
                <a href="/frontend/login-user.html" style="color: #00d4ff;">Go to Login</a>
            </div>
        </body>
        </html>';
        exit;
    }
    jsonResponse(['error' => 'Verification token is required'], 400);
}

$token = sanitizeInput($token);

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    jsonResponse(['error' => 'Database connection failed'], 500);
}

try {
    // Find user with this verification token
    $stmt = $pdo->prepare(
        "SELECT id, name, email, verified FROM users WHERE verification_token = ?"
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Invalid or already used token
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Email Verification - Star Oracle</title>
                <style>
                    body { font-family: Arial, sans-serif; display: flex; justify-content: center; 
                           align-items: center; height: 100vh; margin: 0; background: #0a0a1a; color: white; }
                    .container { text-align: center; padding: 40px; background: rgba(255,255,255,0.1); 
                                 border-radius: 12px; }
                    .error { color: #ff6b6b; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="error">Verification Failed</h1>
                    <p>Invalid or expired verification link.</p>
                    <a href="/frontend/login-user.html" style="color: #00d4ff;">Go to Login</a>
                </div>
            </body>
            </html>';
            exit;
        }
        jsonResponse(['error' => 'Invalid or expired verification token'], 400);
    }
    
    if ($user['verified']) {
        // Already verified
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Email Verification - Star Oracle</title>
                <style>
                    body { font-family: Arial, sans-serif; display: flex; justify-content: center; 
                           align-items: center; height: 100vh; margin: 0; background: #0a0a1a; color: white; }
                    .container { text-align: center; padding: 40px; background: rgba(255,255,255,0.1); 
                                 border-radius: 12px; }
                    .success { color: #00d4ff; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1 class="success">Already Verified</h1>
                    <p>Your email has already been verified.</p>
                    <a href="/frontend/login-user.html" style="color: #00d4ff;">Go to Login</a>
                </div>
            </body>
            </html>';
            exit;
        }
        jsonResponse([
            'success' => true,
            'message' => 'Email already verified'
        ]);
    }
    
    // Update user as verified and clear verification token
    $stmt = $pdo->prepare(
        "UPDATE users SET verified = 1, verification_token = NULL WHERE id = ?"
    );
    $stmt->execute([$user['id']]);
    
    // Success response
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Email Verification - Star Oracle</title>
            <meta http-equiv="refresh" content="3;url=/frontend/login-user.html">
            <style>
                body { font-family: Arial, sans-serif; display: flex; justify-content: center; 
                       align-items: center; height: 100vh; margin: 0; background: #0a0a1a; color: white; }
                .container { text-align: center; padding: 40px; background: rgba(255,255,255,0.1); 
                             border-radius: 12px; }
                .success { color: #00ff88; }
                .glow { text-shadow: 0 0 20px #00ff88; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1 class="success glow">✓ Email Verified!</h1>
                <p>Welcome to Star Oracle, ' . htmlspecialchars($user['name']) . '!</p>
                <p>Your account has been verified successfully.</p>
                <p>Redirecting to login page...</p>
                <a href="/frontend/login-user.html" style="color: #00d4ff;">Click here if not redirected</a>
            </div>
        </body>
        </html>';
        exit;
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Email verified successfully',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Verification error: " . $e->getMessage());
    jsonResponse(['error' => 'Verification failed. Please try again.'], 500);
}
