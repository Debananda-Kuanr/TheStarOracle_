<?php
/**
 * Star Oracle - Logout API
 * Destroys user session
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

handleCORS();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get token from header
$token = getBearerToken();

if (!$token) {
    jsonResponse(['error' => 'No token provided'], 400);
}

// Verify token is valid
$payload = verifyJWT($token);

if (!$payload) {
    jsonResponse(['error' => 'Invalid or expired token'], 401);
}

// Destroy the session
$destroyed = destroySession($token);

if ($destroyed) {
    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} else {
    jsonResponse([
        'success' => true,
        'message' => 'Session already ended'
    ]);
}
