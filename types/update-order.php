<?php
/**
 * NZELA API - Réorganiser l'ordre des types de signalements
 * PUT /api/types/update-order.php
 * Modification de l'ordre d'affichage des types
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
checkMethod(['PUT']);

try {
    // Récupérer les données JSON
    $input = getJsonInput();
    
    // Validation des champs requis
    validateRequired($input, ['updates']);
    
    if (!is_array($input['updates'])) {
        jsonResponse(['error' => 'Le champ updates doit être un tableau'], 400);
    }
    
    // Valider la structure des mises à jour
    $updates = [];
    foreach ($input['updates'] as $update) {
        if (!isset($update['id']) || !isset($update['ordre_affichage'])) {
            jsonResponse(['error' => 'Chaque mise à jour doit contenir id et ordre_affichage'], 400);
        }
        
        $updates[] = [
            'id' => (int)$update['id'],
            'ordre_affichage' => (int)$update['ordre_affichage']
        ];
    }
    
    // Vérifier que tous les types existent
    $typeModel = new TypeSignalement();
    foreach ($updates as $update) {
        if (!$typeModel->exists($update['id'])) {
            jsonResponse(['error' => 'Type ID ' . $update['id'] . ' non trouvé'], 404);
        }
    }
    
    // Mettre à jour l'ordre
    $typeModel->updateOrder($updates);
    
    // Récupérer la liste mise à jour
    $updatedTypes = $typeModel->getAll();
    
    jsonResponse([
        'success' => true,
        'message' => 'Ordre des types mis à jour avec succès',
        'data' => $updatedTypes
    ]);
    
} catch (Exception $e) {
    logError("Erreur mise à jour ordre types: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la mise à jour de l\'ordre'], 500);
}
?>