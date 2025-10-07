<?php
/**
 * NZELA API - Vérification Session Administrateur
 * GET /api/admin/me.php
 * 
 * Vérifier l'état de la session admin et récupérer les informations de l'administrateur connecté
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez GET.'], 405);
}

try {
    // Récupérer le token depuis les headers (compatible CLI et web)
    $authHeader = null;
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    }
    
    // Fallback pour CLI ou si getallheaders() n'existe pas
    if (!$authHeader) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    }
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        jsonResponse(['error' => 'Token d\'authentification requis'], 401);
    }
    
    $sessionToken = $matches[1];
    
    // Vérifier la session
    $userModel = new User();
    $admin = $userModel->verifyAdminSession($sessionToken);
    
    if (!$admin) {
        jsonResponse(['error' => 'Session invalide ou expirée'], 401);
    }
    
    // Mettre à jour la dernière activité
    $userModel->updateLastActivity($admin['id']);
    
    // Préparer les données de réponse (sans informations sensibles)
    $responseData = [
        'id' => $admin['id'],
        'email' => $admin['email'],
        'first_name' => $admin['first_name'],
        'last_name' => $admin['last_name'],
        'role' => $admin['role'],
        'permissions' => $admin['permissions'] ? json_decode($admin['permissions'], true) : null,
        'last_activity' => date('Y-m-d H:i:s'),
        'session_expires' => $admin['expires_at'],
        'session_ip' => $admin['ip_address']
    ];
    
    jsonResponse([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Erreur vérification session admin: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Erreur lors de la vérification de session',
        'details' => $e->getMessage()
    ], 500);
}
?>