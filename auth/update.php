<?php
/**
 * NZELA API - Mettre à jour le profil utilisateur
 * PUT /api/auth/update.php
 * Mise à jour du profil de l'utilisateur connecté
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
checkMethod(['PUT', 'POST']);

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
    
    // Préparer les données de mise à jour
    $updateData = [];
    
    // Champs modifiables
    if (isset($input['firstName'])) {
        if (empty(trim($input['firstName']))) {
            jsonResponse(['error' => 'Le prénom ne peut pas être vide'], 400);
        }
        $updateData['firstName'] = sanitizeString($input['firstName']);
    }
    
    if (isset($input['lastName'])) {
        if (empty(trim($input['lastName']))) {
            jsonResponse(['error' => 'Le nom ne peut pas être vide'], 400);
        }
        $updateData['lastName'] = sanitizeString($input['lastName']);
    }
    
    if (isset($input['email'])) {
        $email = sanitizeString($input['email']);
        if (!validateEmail($email)) {
            jsonResponse(['error' => 'Adresse email invalide'], 400);
        }
        
        // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
        if ($email !== $existingUser['email'] && $userModel->emailExists($email)) {
            jsonResponse(['error' => 'Cette adresse email est déjà utilisée'], 400);
        }
        
        $updateData['email'] = $email;
    }
    
    if (isset($input['phone'])) {
        $updateData['phone'] = sanitizeString($input['phone']);
    }
    
    if (isset($input['province'])) {
        $updateData['province'] = sanitizeString($input['province']);
    }
    
    // Changement de mot de passe (optionnel)
    if (isset($input['currentPassword']) && isset($input['newPassword'])) {
        // Vérifier le mot de passe actuel
        if (!verifyPassword($input['currentPassword'], $existingUser['password'])) {
            jsonResponse(['error' => 'Mot de passe actuel incorrect'], 400);
        }
        
        // Valider le nouveau mot de passe
        if (strlen($input['newPassword']) < 6) {
            jsonResponse(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'], 400);
        }
        
        $updateData['password'] = hashPassword($input['newPassword']);
    }
    
    // Vérifier qu'il y a au moins un champ à mettre à jour
    if (empty($updateData)) {
        jsonResponse(['error' => 'Aucune donnée à mettre à jour'], 400);
    }
    
    // Mettre à jour l'utilisateur
    $result = $userModel->update($userId, $updateData);
    
    if ($result) {
        // Récupérer l'utilisateur mis à jour (sans le mot de passe)
        $updatedUser = $userModel->getById($userId);
        unset($updatedUser['password']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => $updatedUser
        ]);
    } else {
        jsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
    }
    
} catch (Exception $e) {
    jsonResponse(['error' => 'Erreur: ' . $e->getMessage()], 500);
}
?>