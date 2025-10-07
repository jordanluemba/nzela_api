<?php
/**
 * NZELA API - Upload d'image pour type de signalement
 * POST /api/upload/type-image.php
 * Pour les icônes des types de signalements
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
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Aucune image reçue ou erreur d\'upload'], 400);
    }
    
    $file = $_FILES['image'];
    
    // Vérifications de sécurité
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB pour les icônes
    
    if (!in_array($file['type'], $allowedTypes)) {
        jsonResponse(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, WebP ou SVG'], 400);
    }
    
    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => 'Fichier trop volumineux. Maximum 2MB pour les icônes'], 400);
    }
    
    // Vérifier que c'est bien une image (sauf pour SVG)
    if ($file['type'] !== 'image/svg+xml') {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            jsonResponse(['error' => 'Le fichier n\'est pas une image valide'], 400);
        }
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'type_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extension);
    
    // Chemin de destination
    $uploadDir = __DIR__ . '/../uploads/types/';
    $filePath = $uploadDir . $fileName;
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        jsonResponse(['error' => 'Erreur lors de la sauvegarde du fichier'], 500);
    }
    
    // Si c'est une image bitmap, la redimensionner pour optimiser
    if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
        $maxWidth = 128; // Taille optimale pour les icônes
        $maxHeight = 128;
        
        // Charger l'image selon son type
        switch ($file['type']) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($filePath);
                break;
        }
        
        if ($source) {
            $oldWidth = imagesx($source);
            $oldHeight = imagesy($source);
            
            // Calculer les nouvelles dimensions en gardant le ratio
            $ratio = min($maxWidth / $oldWidth, $maxHeight / $oldHeight);
            $newWidth = intval($oldWidth * $ratio);
            $newHeight = intval($oldHeight * $ratio);
            
            // Créer la nouvelle image
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            
            // Préserver la transparence pour PNG
            if ($file['type'] === 'image/png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefill($resized, 0, 0, $transparent);
            }
            
            // Redimensionner
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);
            
            // Sauvegarder l'image redimensionnée
            switch ($file['type']) {
                case 'image/jpeg':
                    imagejpeg($resized, $filePath, 90);
                    break;
                case 'image/png':
                    imagepng($resized, $filePath, 6);
                    break;
                case 'image/webp':
                    imagewebp($resized, $filePath, 90);
                    break;
            }
            
            // Nettoyer la mémoire
            imagedestroy($source);
            imagedestroy($resized);
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Image de type uploadée avec succès',
        'data' => [
            'filename' => $fileName,
            'path' => 'uploads/types/' . $fileName,
            'size' => filesize($filePath),
            'type' => $file['type'],
            'optimized' => in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp'])
        ]
    ], 201);
    
} catch (Exception $e) {
    logError("Erreur upload image type: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>