<?php
/**
 * NZELA API - Lister les signalements
 * GET /api/signalements/list.php
 * Avec pagination et filtres
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

// Mode diagnostic simple
if (isset($_GET['diagnostic']) && $_GET['diagnostic'] === 'true') {
    try {
        $signalementModel = new Signalement();
        
        $diagnosticInfo = [
            'success' => true,
            'connection' => $signalementModel->testConnection(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Test requête simple
        try {
            $test = $signalementModel->fetchAll("SELECT COUNT(*) as total FROM signalements");
            $diagnosticInfo['signalements_count'] = $test[0]['total'] ?? 0;
        } catch (Exception $e) {
            $diagnosticInfo['signalements_error'] = $e->getMessage();
        }
        
        // Test types
        try {
            $test = $signalementModel->fetchAll("SELECT COUNT(*) as total FROM types_signalements");
            $diagnosticInfo['types_count'] = $test[0]['total'] ?? 0;
        } catch (Exception $e) {
            $diagnosticInfo['types_error'] = $e->getMessage();
        }
        
        jsonResponse($diagnosticInfo);
        exit;
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

try {
    // Paramètres de pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // Filtres optionnels
    $filters = [];
    
    if (!empty($_GET['province'])) {
        $filters['province'] = sanitizeString($_GET['province']);
    }
    
    if (!empty($_GET['statut'])) {
        $statutValid = ['En attente', 'En cours', 'Traité', 'Rejeté'];
        if (in_array($_GET['statut'], $statutValid)) {
            $filters['statut'] = $_GET['statut'];
        }
    }
    
    if (!empty($_GET['urgence'])) {
        $urgenceValid = ['Urgent', 'Moyen', 'Faible'];
        if (in_array($_GET['urgence'], $urgenceValid)) {
            $filters['urgence'] = $_GET['urgence'];
        }
    }
    
    // Filtre "mes signalements" (si connecté)
    if (isset($_GET['my']) && $_GET['my'] === 'true' && isLoggedIn()) {
        $filters['user_id'] = $_SESSION['user_id'];
    }
    
    $signalementModel = new Signalement();
    
    // Récupérer les signalements
    $signalements = $signalementModel->getAll($filters, $limit, $offset);
    
    // Compter le total pour la pagination
    $total = $signalementModel->countAll($filters);
    
    // Formatage simple des données
    if (is_array($signalements)) {
        foreach ($signalements as &$signalement) {
            $signalement = formatSignalementWithImages($signalement);
        }
    }
    
    jsonResponse([
        'success' => true,
        'data' => $signalements ?: [],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'has_more' => ($offset + count($signalements ?: [])) < $total
        ],
        'filters' => $filters,
        'count' => count($signalements ?: [])
    ]);
    
} catch (Exception $e) {
    // Log simplifié pour debug
    $errorMsg = "Erreur list signalements: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    error_log($errorMsg);
    
    // En mode debug, renvoyer plus de détails
    if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
        jsonResponse([
            'success' => false,
            'error' => 'Erreur serveur',
            'debug' => [
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ], 500);
    } else {
        jsonResponse(['error' => 'Erreur serveur'], 500);
    }
}
?>