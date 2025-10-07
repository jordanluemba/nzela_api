<?php
/**
 * NZELA API - Détail d'un type de signalement
 * GET /api/types/detail.php?id=1
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
require_once '../models/TypeSignalement.php';

// Vérifier la méthode HTTP
checkMethod(['GET']);

try {
    // Récupérer l'ID depuis l'URL
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        jsonResponse(['error' => 'ID du type requis'], 400);
    }
    
    $typeModel = new TypeSignalement();
    $type = $typeModel->getById($id);
    
    if (!$type) {
        jsonResponse(['error' => 'Type de signalement non trouvé'], 404);
    }
    
    // Ajouter l'URL de l'image
    $type = formatTypeWithImage($type);
    
    // Ajouter les statistiques du type
    $signalementsCount = $typeModel->getSignalementsCount($id);
    $type['signalements_count'] = $signalementsCount;
    
    jsonResponse([
        'success' => true,
        'data' => $type
    ]);
    
} catch (Exception $e) {
    logError("Erreur types detail: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>