<?php
/**
 * NZELA API - Détail d'un signalement
 * GET /api/signalements/detail.php?code=CODE ou ?id=ID
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Capturer les erreurs PHP fatales et les convertir en JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erreur PHP: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
        exit;
    }
});

require_once '../config/cors.php';
require_once '../helpers/functions.php';
require_once '../models/Signalement.php';

// Vérifier la méthode HTTP
checkMethod(['GET']);

// Mode diagnostic simple
if (isset($_GET['diagnostic']) && $_GET['diagnostic'] === 'true') {
    try {
        $signalementModel = new Signalement();
        
        $diagnosticInfo = [
            'success' => true,
            'connection' => $signalementModel->testConnection(),
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => 'detail'
        ];
        
        jsonResponse($diagnosticInfo);
        exit;
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

try {
    // Récupérer le code ou l'ID depuis l'URL
    $code = isset($_GET['code']) ? sanitizeString($_GET['code']) : null;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$code && $id <= 0) {
        jsonResponse(['error' => 'Code ou ID du signalement requis'], 400);
    }
    
    $signalementModel = new Signalement();
    
    // Rechercher par code ou par ID
    $signalement = null;
    if ($code) {
        $signalement = $signalementModel->getByCode($code);
    } else {
        $signalement = $signalementModel->getById($id);
    }
    
    if (!$signalement) {
        jsonResponse(['error' => 'Signalement non trouvé'], 404);
    }
    
    // Formatage des données pour s'assurer de la cohérence
    if (is_array($signalement)) {
        $signalement = formatSignalementWithImages($signalement);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $signalement
    ]);
    
} catch (Exception $e) {
    // Log simplifié pour debug
    $errorMsg = "Erreur detail signalement: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    error_log($errorMsg);
    
    // En mode debug, renvoyer plus de détails
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        jsonResponse([
            'success' => false,
            'error' => 'Erreur serveur',
            'debug' => [
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ], 500);
    } else {
        jsonResponse(['error' => 'Erreur serveur'], 500);
    }
}
?>