<?php
/**
 * NZELA API - Créer un type de signalement
 * POST /api/types/create.php
 * Création d'un nouveau type de signalement avec image
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
checkMethod(['POST']);

try {
    // Récupérer les données JSON
    $input = getJsonInput();
    
    // Validation des champs requis
    validateRequired($input, ['nom']);
    
    // Vérifier que l'utilisateur est connecté et a les droits admin (optionnel)
    // if (!isLoggedIn()) {
    //     jsonResponse(['error' => 'Authentification requise'], 401);
    // }
    
    // Préparer les données du type
    $typeData = [
        'nom' => sanitizeString($input['nom']),
        'description' => isset($input['description']) ? sanitizeString($input['description']) : null,
        'image_path' => isset($input['image_path']) ? sanitizeString($input['image_path']) : null,
        'ordre_affichage' => isset($input['ordre_affichage']) ? (int)$input['ordre_affichage'] : 0,
        'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : true
    ];
    
    // Vérifier que le nom n'existe pas déjà
    $typeModel = new TypeSignalement();
    $existingType = $typeModel->fetchAll("SELECT id FROM types_signalements WHERE nom = :nom", ['nom' => $typeData['nom']]);
    
    if (!empty($existingType)) {
        jsonResponse(['error' => 'Un type avec ce nom existe déjà'], 400);
    }
    
    // Si aucun ordre d'affichage spécifié, mettre à la fin
    if ($typeData['ordre_affichage'] === 0) {
        $maxOrder = $typeModel->fetch("SELECT MAX(ordre_affichage) as max_ordre FROM types_signalements");
        $typeData['ordre_affichage'] = ($maxOrder['max_ordre'] ?? 0) + 1;
    }
    
    // Créer le type
    $typeId = $typeModel->create($typeData);
    
    // Récupérer le type créé avec toutes ses données
    $createdType = $typeModel->getById($typeId);
    
    jsonResponse([
        'success' => true,
        'message' => 'Type de signalement créé avec succès',
        'data' => $createdType
    ], 201);
    
} catch (Exception $e) {
    logError("Erreur création type: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la création du type'], 500);
}
?>