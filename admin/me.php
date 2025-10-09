<?php
/**
 * NZELA API - Profil Administrateur
 * GET /api/admin/me.php
 * 
 * Récupérer les informations de l'administrateur connecté avec le nouveau système unifié
 * Note: Ce endpoint est maintenant un alias de /auth/me.php pour les admins
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
    // Vérifier l'authentification admin avec le nouveau système
    requireAuth('admin');
    
    $currentUser = getCurrentUser();
    
    // Récupérer les données complètes depuis la base
    $userModel = new User();
    $user = $userModel->getById($currentUser['id']);
    
    if (!$user) {
        jsonResponse(['error' => 'Utilisateur non trouvé'], 404);
    }
    
    // Mettre à jour la dernière activité
    $userModel->updateLastActivity($user['id']);
    
    // Préparer la réponse spécifique aux admins
    $responseData = [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'permissions' => $user['permissions'] ? json_decode($user['permissions'], true) : [],
        'last_activity' => $user['last_activity'],
        'last_login' => $user['last_login'],
        'created_at' => $user['created_at'],
        'session_expires' => date('Y-m-d H:i:s', $_SESSION['expires_at'] ?? time() + 7200),
        'session_login_time' => date('Y-m-d H:i:s', $_SESSION['login_time'] ?? time()),
        'session_ip' => $_SESSION['ip_address'] ?? getClientIP()
    ];
    
    // Logger la consultation du profil
    logUserActivity('VIEW_PROFILE', 'users', $user['id']);
    
    jsonResponse([
        'success' => true,
        'data' => $responseData
    ]);
    
} catch (Exception $e) {
    error_log("Erreur profil admin: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Erreur lors de la récupération du profil',
        'details' => $e->getMessage()
    ], 500);
}
?>