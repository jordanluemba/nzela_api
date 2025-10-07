<?php
/**
 * NZELA API - Liste des types de signalements
 * GET /api/types/list.php
 * Pour charger dynamiquement les cartes dans nouveau-signalement.html
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
    require_once '../models/TypeSignalement.php';
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
checkMethod(['GET']);

try {
    $typeModel = new TypeSignalement();
    $types = $typeModel->getAll();
    
    // Ajouter les URLs d'images aux types
    if (is_array($types)) {
        foreach ($types as &$type) {
            $type = formatTypeWithImage($type);
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => $types,
        'count' => count($types)
    ]);
    
} catch (Exception $e) {
    logError("Erreur types list: " . $e->getMessage() . " - Ligne: " . $e->getLine());
    
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