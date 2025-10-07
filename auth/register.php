<?php
/**
 * NZELA API - Inscription utilisateur
 * POST /api/auth/register.php
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
    validateRequired($input, ['firstName', 'lastName', 'email', 'password']);
    
    $firstName = sanitizeString($input['firstName']);
    $lastName = sanitizeString($input['lastName']);
    $email = sanitizeString($input['email']);
    $password = $input['password'];
    $phone = isset($input['phone']) ? sanitizeString($input['phone']) : null;
    $province = isset($input['province']) ? sanitizeString($input['province']) : null;
    
    // Validations
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Format d\'email invalide'], 400);
    }
    
    if (strlen($password) < 8) {
        jsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], 400);
    }
    
    // Vérifier si l'email existe déjà
    $userModel = new User();
    if ($userModel->emailExists($email)) {
        jsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
    }
    
    // Créer l'utilisateur
    $userData = [
        'email' => $email,
        'password_hash' => hashPassword($password),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'province' => $province
    ];
    
    $userId = $userModel->create($userData);
    
    // Connecter automatiquement l'utilisateur
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
    
    jsonResponse([
        'success' => true,
        'message' => 'Compte créé avec succès',
        'user' => [
            'id' => $userId,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'phone' => $phone,
            'province' => $province,
            'signalementsCount' => 0
        ]
    ], 201);
    
} catch (Exception $e) {
    logError("Erreur register: " . $e->getMessage() . " - Ligne: " . $e->getLine());
    
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