<?php
/**
 * NZELA API - Supprimer un signalement
 * DELETE /api/signalements/delete.php
 * Suppression d'un signalement (soft delete)
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
checkMethod(['DELETE', 'POST']);

// Vérifier l'authentification
requireAuth();

try {
    // Récupérer l'ID du signalement
    $input = getJsonInput();
    $id = null;
    
    // Récupérer l'ID depuis JSON ou URL
    if (isset($input['id'])) {
        $id = (int)$input['id'];
    } elseif (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
    } else {
        jsonResponse(['error' => 'ID du signalement requis'], 400);
    }
    
    // Vérifier que le signalement existe
    $signalementModel = new Signalement();
    $existingSignalement = $signalementModel->getById($id);
    
    if (!$existingSignalement) {
        jsonResponse(['error' => 'Signalement non trouvé'], 404);
    }
    
    // Vérifier que l'utilisateur peut supprimer ce signalement
    // L'utilisateur peut supprimer ses propres signalements ou être admin
    if ($existingSignalement['user_id'] != $_SESSION['user_id']) {
        // TODO: Vérifier si l'utilisateur est admin
        jsonResponse(['error' => 'Accès refusé'], 403);
    }
    
    // Vérifier que le signalement n'est pas déjà traité
    if ($existingSignalement['statut'] === 'Traité') {
        jsonResponse(['error' => 'Impossible de supprimer un signalement traité'], 400);
    }
    
    // Effectuer la suppression (soft delete)
    $result = $signalementModel->softDelete($id);
    
    if ($result) {
        jsonResponse([
            'success' => true,
            'message' => 'Signalement supprimé avec succès',
            'data' => [
                'id' => $id,
                'deleted_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la suppression'], 500);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
}
?>