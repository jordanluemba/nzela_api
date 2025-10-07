<?php
/**
 * NZELA API - Déconnexion utilisateur
 * POST /api/auth/logout.php
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
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de chargement']);
    exit;
}

// Vérifier la méthode HTTP
checkMethod(['POST']);

try {
    // Détruire toutes les données de session
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
    
} catch (Exception $e) {
    logError("Erreur logout: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>