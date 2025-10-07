<?php
/**
 * NZELA API - Upload de photo
 * POST /api/upload/photo.php
 * Pour les photos des signalements
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

// Vérifier la méthode HTTP
checkMethod(['POST']);

try {
    // Vérifier qu'un fichier a été uploadé
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Aucune photo reçue ou erreur d\'upload'], 400);
    }
    
    $file = $_FILES['photo'];
    
    // Vérifications de sécurité
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        jsonResponse(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou WebP'], 400);
    }
    
    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => 'Fichier trop volumineux. Maximum 5MB'], 400);
    }
    
    // Vérifier que c'est bien une image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        jsonResponse(['error' => 'Le fichier n\'est pas une image valide'], 400);
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'photo_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extension);
    
    // Chemin de destination
    $uploadDir = __DIR__ . '/../uploads/signalements/';
    $filePath = $uploadDir . $fileName;
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        jsonResponse(['error' => 'Erreur lors de la sauvegarde du fichier'], 500);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Photo uploadée avec succès',
        'data' => [
            'filename' => $fileName,
            'path' => 'uploads/signalements/' . $fileName,
            'size' => $file['size'],
            'type' => $file['type']
        ]
    ], 201);
    
} catch (Exception $e) {
    logError("Erreur upload photo: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>