<?php
/**
 * Script de vérification BDD - Signalements avec photos
 */

require_once '../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Récupérer les derniers signalements
    $stmt = $pdo->prepare("
        SELECT id, code, photo_principale, created_at 
        FROM signalements 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $signalements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Derniers signalements récupérés',
        'data' => $signalements,
        'debug' => [
            'count' => count($signalements),
            'sql' => "SELECT id, code, photo_principale, created_at FROM signalements ORDER BY created_at DESC LIMIT 10"
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>