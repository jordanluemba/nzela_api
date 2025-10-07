<?php
/**
 * NZELA API - Compteur de notifications
 * GET /api/notifications/count.php
 * 
 * Récupérer le nombre de notifications non lues pour un utilisateur
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/User.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez GET.'], 405);
}

try {
    // Récupérer l'utilisateur connecté
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        // Essayer avec l'authentification admin
        $currentUser = getCurrentAdmin();
        if (!$currentUser) {
            jsonResponse(['error' => 'Authentification requise'], 401);
        }
    }
    
    // Déterminer le type d'utilisateur
    $recipientType = ($currentUser['role'] === 'citoyen') ? 'citoyen' : 'admin';
    
    // Récupérer le compteur
    $notificationModel = new Notification();
    $unreadCount = $notificationModel->getUnreadCount($currentUser['id'], $recipientType);
    
    // Optionnel: récupérer les statistiques détaillées
    $includeStats = isset($_GET['include_stats']) && $_GET['include_stats'] === 'true';
    
    $response = [
        'success' => true,
        'data' => [
            'unread_count' => $unreadCount,
            'user_id' => $currentUser['id'],
            'recipient_type' => $recipientType
        ]
    ];
    
    // Ajouter des statistiques détaillées si demandées
    if ($includeStats) {
        try {
            $database = new Database();
            $db = $database->connect();
            
            // Statistiques par type de notification
            $stmt = $db->prepare("
                SELECT 
                    type,
                    COUNT(*) as count,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
                FROM notifications 
                WHERE recipient_id = ? AND recipient_type = ?
                AND (expires_at IS NULL OR expires_at > NOW())
                GROUP BY type
            ");
            
            $stmt->execute([$currentUser['id'], $recipientType]);
            $statsByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Statistiques par priorité
            $stmt = $db->prepare("
                SELECT 
                    priority,
                    COUNT(*) as count,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count
                FROM notifications 
                WHERE recipient_id = ? AND recipient_type = ?
                AND (expires_at IS NULL OR expires_at > NOW())
                GROUP BY priority
            ");
            
            $stmt->execute([$currentUser['id'], $recipientType]);
            $statsByPriority = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['data']['stats'] = [
                'by_type' => $statsByType,
                'by_priority' => $statsByPriority
            ];
            
        } catch (Exception $e) {
            // Si les stats échouent, continuer sans elles
            $response['data']['stats_error'] = 'Impossible de charger les statistiques détaillées';
        }
    }
    
    jsonResponse($response);
    
} catch (Exception $e) {
    error_log("Erreur comptage notifications: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur lors du comptage des notifications',
        'details' => $e->getMessage()
    ], 500);
}
?>