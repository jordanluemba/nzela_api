<?php
/**
 * NZELA API - Test de santé simple
 * GET /api/health-simple.php
 */

// Headers CORS explicites pour test
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://nzela-ten.vercel.app');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Gestion OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Test simple
echo json_encode([
    'success' => true,
    'message' => 'API NZELA opérationnelle',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'],
    'cors_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none'
]);
?>