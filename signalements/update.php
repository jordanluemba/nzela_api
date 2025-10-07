<?php
/**
 * NZELA API - Mettre à jour un signalement
 * PUT /api/signalements/update.php
 * Mise à jour complète d'un signalement
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
require_once '../models/TypeSignalement.php';

// Vérifier la méthode HTTP
checkMethod(['PUT', 'POST']);

// Vérifier l'authentification
requireAuth();

try {
    // Récupérer les données JSON
    $input = getJsonInput();
    
    // Validation des champs requis
    validateRequired($input, ['id']);
    
    $id = (int)$input['id'];
    
    // Vérifier que le signalement existe
    $signalementModel = new Signalement();
    $existingSignalement = $signalementModel->getById($id);
    
    if (!$existingSignalement) {
        jsonResponse(['error' => 'Signalement non trouvé'], 404);
    }
    
    // Vérifier que l'utilisateur peut modifier ce signalement
    // L'utilisateur peut modifier ses propres signalements ou être admin
    if ($existingSignalement['user_id'] != $_SESSION['user_id']) {
        // TODO: Vérifier si l'utilisateur est admin
        jsonResponse(['error' => 'Accès refusé'], 403);
    }
    
    // Préparer les données de mise à jour
    $updateData = [];
    
    // Champs modifiables
    if (isset($input['type_signalement_id'])) {
        $typeModel = new TypeSignalement();
        if (!$typeModel->exists($input['type_signalement_id'])) {
            jsonResponse(['error' => 'Type de signalement invalide'], 400);
        }
        $updateData['type_signalement_id'] = (int)$input['type_signalement_id'];
    }
    
    if (isset($input['province'])) {
        $updateData['province'] = sanitizeString($input['province']);
    }
    
    if (isset($input['ville'])) {
        $updateData['ville'] = sanitizeString($input['ville']);
    }
    
    if (isset($input['commune'])) {
        $updateData['commune'] = sanitizeString($input['commune']);
    }
    
    if (isset($input['quartier'])) {
        $updateData['quartier'] = sanitizeString($input['quartier']);
    }
    
    if (isset($input['nom_rue'])) {
        $updateData['nom_rue'] = sanitizeString($input['nom_rue']);
    }
    
    if (isset($input['latitude'])) {
        $updateData['latitude'] = (float)$input['latitude'];
    }
    
    if (isset($input['longitude'])) {
        $updateData['longitude'] = (float)$input['longitude'];
    }
    
    if (isset($input['description'])) {
        $updateData['description'] = sanitizeString($input['description']);
    }
    
    if (isset($input['urgence'])) {
        $validUrgences = ['Faible', 'Moyen', 'Élevé', 'Critique'];
        if (!in_array($input['urgence'], $validUrgences)) {
            jsonResponse(['error' => 'Niveau d\'urgence invalide'], 400);
        }
        $updateData['urgence'] = $input['urgence'];
    }
    
    if (isset($input['circulation'])) {
        $updateData['circulation'] = sanitizeString($input['circulation']);
    }
    
    if (isset($input['nom_citoyen'])) {
        $updateData['nom_citoyen'] = sanitizeString($input['nom_citoyen']);
    }
    
    if (isset($input['telephone'])) {
        $updateData['telephone'] = sanitizeString($input['telephone']);
    }
    
    if (isset($input['photo_principale'])) {
        $updateData['photo_principale'] = sanitizeString($input['photo_principale']);
    }
    
    // Vérifier qu'il y a au moins un champ à mettre à jour
    if (empty($updateData)) {
        jsonResponse(['error' => 'Aucune donnée à mettre à jour'], 400);
    }
    
    // Mettre à jour le signalement
    $result = $signalementModel->update($id, $updateData);
    
    if ($result) {
        // Récupérer le signalement mis à jour
        $updatedSignalement = $signalementModel->getById($id);
        
        jsonResponse([
            'success' => true,
            'message' => 'Signalement mis à jour avec succès',
            'data' => $updatedSignalement
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
}
?>