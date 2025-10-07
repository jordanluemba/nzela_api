<?php
/**
 * NZELA API - Signalements de l'utilisateur connecté
 * GET /api/signalements/user.php
 * Pour la page mes-signalements.html
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
checkMethod(['GET']);

// Vérifier l'authentification
requireAuth();

try {
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 50;
    $offset = ($page - 1) * $limit;
    
    $signalementModel = new Signalement();
    
    // Récupérer les signalements de l'utilisateur
    $signalements = $signalementModel->getByUserId($_SESSION['user_id'], $limit, $offset);
    
    // Ajouter les URLs d'images aux signalements
    if (is_array($signalements)) {
        foreach ($signalements as &$signalement) {
            $signalement = formatSignalementWithImages($signalement);
        }
    }
    
    // Compter le total
    $filters = ['user_id' => $_SESSION['user_id']];
    $total = $signalementModel->countAll($filters);
    
    jsonResponse([
        'success' => true,
        'data' => $signalements,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ],
        'user_id' => $_SESSION['user_id']
    ]);
    
} catch (Exception $e) {
    logError("Erreur user signalements: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur'], 500);
}
?>