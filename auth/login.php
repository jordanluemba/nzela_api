<?php
/**
 * NZELA API - Connexion utilisateur
 * POST /api/auth/login.php
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

try {
    require_once '../config/cors.php';
    require_once '../helpers/functions.php';
    require_once '../models/User.php';
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erreur de chargement des dépendances',
        'debug' => $e->getMessage()
    ]);
    exit;
}

// Vérifier la méthode HTTP
checkMethod(['POST']);

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
    
    // Tentative de connexion pour tous les types d'utilisateurs
    $userModel = new User();
    $user = $userModel->login($email, $password);
    
    if ($user) {
        // Créer la session unifiée avec le nouveau système
        createUserSession($user);
        
        // Mettre à jour la dernière connexion
        $userModel->updateLastLogin($user['id']);
        
        // Logger la connexion
        logUserActivity('LOGIN', 'users', $user['id'], [
            'ip' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Préparer la réponse selon le rôle
        $responseData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'role' => $user['role'] ?? 'citoyen',
            'phone' => $user['phone'],
            'province' => $user['province']
        ];
        
        // Ajouter des données spécifiques selon le rôle
        if (in_array($user['role'] ?? 'citoyen', ['admin', 'superadmin'])) {
            // Pour les admins : permissions et infos supplémentaires
            $responseData['permissions'] = $user['permissions'] ? json_decode($user['permissions'], true) : [];
            $responseData['lastActivity'] = $user['last_activity'];
            $responseData['sessionExpires'] = date('Y-m-d H:i:s', $_SESSION['expires_at']);
        } else {
            // Pour les citoyens : nombre de signalements
            $responseData['signalementsCount'] = $userModel->getSignalementsCount($user['id']);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => $responseData
        ]);
    } else {
        jsonResponse(['error' => 'Email ou mot de passe incorrect'], 401);
    }
    
} catch (Exception $e) {
    logError("Erreur login: " . $e->getMessage() . " - Ligne: " . $e->getLine());
    
    // En mode debug, inclure plus d'informations
    $isDev = ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
    
    if ($isDev) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'debug' => [
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 3)
            ]
        ], 500);
    } else {
        jsonResponse(['error' => 'Erreur serveur'], 500);
    }
}
?>