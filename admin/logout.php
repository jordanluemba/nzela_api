<?php
/**
 * NZELA API - Déconnexion Administrateur
 * POST /api/admin/logout.php
 * 
 * Déconnexion sécurisée des administrateurs avec invalidation de session
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
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
    
    // Vérifier et récupérer la session
    $database = new Database();
    $pdo = $database->connect();
    
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, u.email, u.role
        FROM admin_sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.session_token = :token AND s.is_active = 1 AND s.expires_at > NOW()
    ");
    
    $stmt->execute(['token' => $sessionToken]);
    $session = $stmt->fetch();
    
    if (!$session) {
        jsonResponse(['error' => 'Session invalide ou expirée'], 401);
    }
    
    // Invalider la session
    $stmt = $pdo->prepare("UPDATE admin_sessions SET is_active = 0 WHERE session_token = :token");
    $stmt->execute(['token' => $sessionToken]);
    
    // Log de la déconnexion
    logAdminAction($session['user_id'], 'LOGOUT', 'admin_session', $session['id'], [
        'session_token' => substr($sessionToken, 0, 8) . '...',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Nettoyer les sessions expirées (maintenance)
    $pdo->exec("DELETE FROM admin_sessions WHERE expires_at < NOW()");
    
    jsonResponse([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur admin logout: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Erreur lors de la déconnexion',
        'details' => $e->getMessage()
    ], 500);
}
?>