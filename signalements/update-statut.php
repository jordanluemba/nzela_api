<?php
/**
 * NZELA API - Mettre à jour le statut d'un signalement
 * PUT /api/signalements/update-statut.php
 * Endpoint admin pour changer le statut des signalements
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
checkMethod(['PUT', 'POST']);

// Vérifier l'authentification (pour l'instant tous les utilisateurs connectés)
requireAuth();

try {
    // Récupérer les données JSON
    $input = getJsonInput();
    
    // Validation des champs requis
    validateRequired($input, ['id', 'statut']);
    
    $id = (int)$input['id'];
    $statut = $input['statut'];
    
    // Valider le statut
    $statutsValides = ['En attente', 'En cours', 'Traité', 'Rejeté'];
    if (!in_array($statut, $statutsValides)) {
        jsonResponse(['error' => 'Statut invalide'], 400);
    }
    
    if ($id <= 0) {
        jsonResponse(['error' => 'ID de signalement invalide'], 400);
    }
    
    $signalementModel = new Signalement();
    
    // Vérifier que le signalement existe
    $signalement = $signalementModel->getById($id);
    if (!$signalement) {
        jsonResponse(['error' => 'Signalement non trouvé'], 404);
    }
    
    // Mettre à jour le statut
    $signalementModel->updateStatus($id, $statut);
    
    jsonResponse([
        'success' => true,
        'message' => 'Statut mis à jour avec succès',
        'data' => [
            'id' => $id,
            'ancien_statut' => $signalement['statut'],
            'nouveau_statut' => $statut,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    logError("Erreur update statut: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>