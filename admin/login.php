<?php
/**
 * NZELA API - Connexion Administrateur
 * POST /api/admin/login.php
 * 
 * Système d'authentification spécialisé pour les administrateurs
 * Utilise la table admin_sessions pour une sécurité renforcée
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Fonction pour capturer les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Erreur serveur interne',
            'debug' => [
                'message' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]
        ]);
        exit;
    }
});

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
}

try {
    // Vérifier que la requête contient du JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') === false) {
        jsonResponse(['error' => 'Content-Type doit être application/json'], 400);
    }
    
    // Récupérer les données JSON
    $input = getJsonInput();
    
    // Vérifier que les données JSON sont valides
    if (empty($input) || !is_array($input)) {
        jsonResponse(['error' => 'Données JSON invalides ou vides'], 400);
    }
    
    // Validation des champs requis
    validateRequired($input, ['email', 'password']);
    
    $email = sanitizeString($input['email']);
    $password = $input['password'];
    
    // Valider l'email
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Format d\'email invalide'], 400);
    }
    
    // Tentative de connexion avec vérification du rôle admin
    $userModel = new User();
    $user = $userModel->loginAdmin($email, $password);
    
    if (!$user) {
        // Log de la tentative de connexion échouée pour audit
        logAdminAction(null, 'LOGIN_FAILED', 'admin_login', null, [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        jsonResponse(['error' => 'Email ou mot de passe incorrect, ou privilèges insuffisants'], 401);
    }
    
    // Vérifier que l'utilisateur a bien un rôle admin
    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        // Log de la tentative d'accès non autorisée
        logAdminAction($user['id'], 'ACCESS_DENIED', 'admin_login', null, [
            'reason' => 'insufficient_privileges',
            'user_role' => $user['role']
        ]);
        
        jsonResponse(['error' => 'Accès refusé. Privilèges administrateur requis.'], 403);
    }
    
    // Créer une session admin sécurisée
    $sessionToken = createAdminSession($user['id']);
    
    if (!$sessionToken) {
        jsonResponse(['error' => 'Erreur lors de la création de session'], 500);
    }
    
    // Mettre à jour la dernière activité
    $userModel->updateLastActivity($user['id']);
    
    // Log de la connexion réussie
    logAdminAction($user['id'], 'LOGIN_SUCCESS', 'admin_login', null, [
        'session_token' => substr($sessionToken, 0, 8) . '...',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Préparer les données de réponse (sans informations sensibles)
    $responseData = [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role'],
        'permissions' => $user['permissions'] ? json_decode($user['permissions'], true) : null,
        'last_login' => date('Y-m-d H:i:s'),
        'session_expires' => date('Y-m-d H:i:s', strtotime('+2 hours'))
    ];
    
    // Réponse de succès
    jsonResponse([
        'success' => true,
        'message' => 'Connexion administrateur réussie',
        'data' => $responseData,
        'admin_token' => $sessionToken,
        'expires_in' => 7200 // 2 heures en secondes
    ]);
    
} catch (Exception $e) {
    // Log de l'erreur pour debug
    error_log("Erreur admin login: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Erreur lors de la connexion',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Créer une session administrateur sécurisée
 */
function createAdminSession($userId) {
    try {
        $database = new Database();
        $pdo = $database->connect();
        
        // Générer un token sécurisé
        $sessionToken = bin2hex(random_bytes(32));
        
        // Nettoyer les anciennes sessions expirées
        $cleanupStmt = $pdo->prepare("DELETE FROM admin_sessions WHERE expires_at < NOW()");
        $cleanupStmt->execute();
        
        // Désactiver les anciennes sessions actives de cet utilisateur (optionnel, pour session unique)
        $deactivateStmt = $pdo->prepare("UPDATE admin_sessions SET is_active = 0 WHERE user_id = :user_id AND is_active = 1");
        $deactivateStmt->execute(['user_id' => $userId]);
        
        // Créer la nouvelle session
        $stmt = $pdo->prepare("
            INSERT INTO admin_sessions (user_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (:user_id, :token, :ip, :user_agent, DATE_ADD(NOW(), INTERVAL 2 HOUR))
        ");
        
        $result = $stmt->execute([
            'user_id' => $userId,
            'token' => $sessionToken,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
        return $result ? $sessionToken : false;
        
    } catch (Exception $e) {
        error_log("Erreur création session admin: " . $e->getMessage());
        return false;
    }
}
?>