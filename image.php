<?php
/**
 * NZELA API - Servir les images uploadées
 * GET /api/image.php?path=uploads/signalements/photo_123456_7890.jpg
 * Endpoint sécurisé pour afficher les images dans le frontend
 */

// Désactiver l'affichage des erreurs
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config/cors.php';

try {
    // Vérifier le paramètre path
    if (!isset($_GET['path']) || empty($_GET['path'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètre path requis']);
        exit;
    }
    
    $requestedPath = $_GET['path'];
    
    // Sécurité : vérifier que le chemin est valide
    $allowedPaths = [
        // Images uploadées par l'API (signalements et types)
        '/^uploads\/(signalements|types)\/[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|webp|svg)$/i',
        // Images statiques des types (pour compatibilité si nécessaire)
        '/^\.\.\/assets\/img\/[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|webp|svg)$/i'
    ];
    
    $isValidPath = false;
    foreach ($allowedPaths as $pattern) {
        if (preg_match($pattern, $requestedPath)) {
            $isValidPath = true;
            break;
        }
    }
    
    if (!$isValidPath) {
        http_response_code(400);
        echo json_encode(['error' => 'Chemin invalide']);
        exit;
    }
    
    // Construire le chemin absolu
    $absolutePath = __DIR__ . '/' . $requestedPath;
    
    // Vérifier que le fichier existe
    if (!file_exists($absolutePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Image non trouvée']);
        exit;
    }
    
    // Déterminer le type MIME
    $imageInfo = getimagesize($absolutePath);
    if ($imageInfo === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Fichier invalide']);
        exit;
    }
    
    $mimeType = $imageInfo['mime'];
    
    // Headers de sécurité
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($absolutePath));
    header('Cache-Control: public, max-age=31536000'); // Cache 1 an
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    
    // Servir le fichier
    readfile($absolutePath);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>