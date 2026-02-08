<?php
/**
 * Simple test endpoint to verify API is working
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'success' => true,
    'message' => 'Backend API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);
