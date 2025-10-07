<?php
/**
 * NZELA API - Mettre à jour un type de signalement
 * PUT /api/types/update.php
 * Modification d'un type de signalement existant
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
    validateRequired($input, ['id']);
    
    $typeId = (int)$input['id'];
    
    // Vérifier que le type existe
    $typeModel = new TypeSignalement();
    $existingType = $typeModel->getById($typeId);
    
    if (!$existingType) {
        jsonResponse(['error' => 'Type de signalement non trouvé'], 404);
    }
    
    // Préparer les données à mettre à jour
    $updateData = [];
    
    if (isset($input['nom'])) {
        $updateData['nom'] = sanitizeString($input['nom']);
        
        // Vérifier que le nouveau nom n'existe pas déjà (sauf pour le type actuel)
        $existingName = $typeModel->fetch(
            "SELECT id FROM types_signalements WHERE nom = :nom AND id != :id", 
            ['nom' => $updateData['nom'], 'id' => $typeId]
        );
        
        if ($existingName) {
            jsonResponse(['error' => 'Un type avec ce nom existe déjà'], 400);
        }
    }
    
    if (isset($input['description'])) {
        $updateData['description'] = sanitizeString($input['description']);
    }
    
    if (isset($input['image_path'])) {
        $updateData['image_path'] = sanitizeString($input['image_path']);
    }
    
    if (isset($input['ordre_affichage'])) {
        $updateData['ordre_affichage'] = (int)$input['ordre_affichage'];
    }
    
    if (isset($input['is_active'])) {
        $updateData['is_active'] = (bool)$input['is_active'];
    }
    
    // Vérifier qu'il y a au moins un champ à mettre à jour
    if (empty($updateData)) {
        jsonResponse(['error' => 'Aucun champ à mettre à jour'], 400);
    }
    
    // Mettre à jour le type
    $typeModel->update($typeId, $updateData);
    
    // Récupérer le type mis à jour
    $updatedType = $typeModel->getById($typeId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Type de signalement mis à jour avec succès',
        'data' => $updatedType
    ]);
    
} catch (Exception $e) {
    logError("Erreur mise à jour type: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la mise à jour du type'], 500);
}
?>