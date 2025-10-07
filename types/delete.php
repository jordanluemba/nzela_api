<?php
/**
 * NZELA API - Supprimer un type de signalement
 * DELETE /api/types/delete.php
 * Suppression d'un type de signalement (avec vérifications)
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
checkMethod(['DELETE', 'POST']);

// Vérifier l'authentification (admin uniquement)
requireAuth();

try {
    // Récupérer l'ID du type
    $input = getJsonInput();
    $id = null;
    
    // Récupérer l'ID depuis JSON ou URL
    if (isset($input['id'])) {
        $id = (int)$input['id'];
    } elseif (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    } else {
        jsonResponse(['error' => 'ID du type requis'], 400);
    }
    
    // Vérifier que le type existe
    $typeModel = new TypeSignalement();
    $existingType = $typeModel->getById($id);
    
    if (!$existingType) {
        jsonResponse(['error' => 'Type de signalement non trouvé'], 404);
    }
    
    // Vérifier qu'il n'y a pas de signalements associés
    $signalementCount = $typeModel->countSignalements($id);
    
    if ($signalementCount > 0) {
        jsonResponse([
            'error' => 'Impossible de supprimer ce type car il est utilisé par ' . $signalementCount . ' signalement(s)',
            'signalement_count' => $signalementCount
        ], 400);
    }
    
    // Option de suppression forcée (admin)
    $force = isset($input['force']) && $input['force'] === true;
    
    if ($signalementCount > 0 && !$force) {
        jsonResponse([
            'error' => 'Type utilisé par des signalements. Utilisez force=true pour forcer la suppression',
            'signalement_count' => $signalementCount,
            'suggestion' => 'Considérez désactiver le type au lieu de le supprimer'
        ], 400);
    }
    
    // Effectuer la suppression
    if ($force && $signalementCount > 0) {
        // Suppression forcée : désactiver le type au lieu de le supprimer
        $result = $typeModel->deactivate($id);
        $message = 'Type désactivé avec succès (signalements associés préservés)';
    } else {
        // Suppression normale
        $result = $typeModel->delete($id);
        $message = 'Type supprimé avec succès';
    }
    
    if ($result) {
        jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => $id,
                'deleted_at' => date('Y-m-d H:i:s'),
                'forced' => $force
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la suppression'], 500);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
}
?>