<?php
/**
 * NZELA API - Profil utilisateur
 * GET /api/auth/me.php
 */

// Désactiver l'affichage des erreurs
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Fonction pour capturer les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur serveur interne']);
        exit;
    }
});

try {
    require_once '../config/cors.php';
    require_once '../helpers/functions.php';
    require_once '../models/User.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de chargement']);
    exit;
}

// Vérifier la méthode HTTP
checkMethod(['GET']);

// Vérifier l'authentification
requireAuth();

try {
    $userModel = new User();
    $user = $userModel->getById($_SESSION['user_id']);
    
    if (!$user) {
        jsonResponse(['error' => 'Utilisateur non trouvé'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'phone' => $user['phone'],
            'province' => $user['province'],
            'createdAt' => $user['created_at'],
            'lastLogin' => $user['last_login'],
            'signalementsCount' => $userModel->getSignalementsCount($user['id'])
        ]
    ]);
    
} catch (Exception $e) {
    logError("Erreur me: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>