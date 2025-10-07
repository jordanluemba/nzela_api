<?php
/**
 * NZELA API - Supprimer un compte utilisateur
 * DELETE /api/auth/delete.php
 * Suppression du compte de l'utilisateur connecté
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
require_once '../models/User.php';

// Vérifier la méthode HTTP
checkMethod(['DELETE', 'POST']);

// Vérifier l'authentification
requireAuth();

try {
    // Récupérer les données JSON
    $input = getJsonInput();
    
    $userId = $_SESSION['user_id'];
    
    // Vérifier que l'utilisateur existe
    $userModel = new User();
    $existingUser = $userModel->getById($userId);
    
    if (!$existingUser) {
        jsonResponse(['error' => 'Utilisateur non trouvé'], 404);
    }
    
    // Demander confirmation du mot de passe pour sécurité
    if (!isset($input['password'])) {
        jsonResponse(['error' => 'Mot de passe requis pour confirmer la suppression'], 400);
    }
    
    // Vérifier le mot de passe
    if (!verifyPassword($input['password'], $existingUser['password'])) {
        jsonResponse(['error' => 'Mot de passe incorrect'], 400);
    }
    
    // Vérifier s'il y a des signalements associés
    $signalementCount = $userModel->countSignalements($userId);
    
    if ($signalementCount > 0) {
        $keepSignalements = isset($input['keepSignalements']) && $input['keepSignalements'] === true;
        
        if (!$keepSignalements) {
            jsonResponse([
                'error' => 'Vous avez ' . $signalementCount . ' signalement(s) associé(s). Utilisez keepSignalements=true pour les conserver',
                'signalement_count' => $signalementCount,
                'suggestion' => 'Les signalements seront anonymisés si vous confirmez'
            ], 400);
        }
    }
    
    // Effectuer la suppression
    $result = $userModel->deleteAccount($userId, [
        'anonymize_signalements' => true,
        'keep_signalements' => isset($input['keepSignalements']) ? $input['keepSignalements'] : false
    ]);
    
    if ($result) {
        // Détruire la session
        session_destroy();
        
        jsonResponse([
            'success' => true,
            'message' => 'Compte supprimé avec succès',
            'data' => [
                'deleted_at' => date('Y-m-d H:i:s'),
                'signalements_anonymized' => $signalementCount > 0
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la suppression du compte'], 500);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
}
?>