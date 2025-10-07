<?php
/**
 * NZELA API - Liste des notifications
 * GET /api/notifications/list.php
 * 
 * Récupérer la liste des notifications pour un utilisateur
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
    // Récupérer l'utilisateur connecté (citoyen ou admin)
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
    
    // Paramètres de requête
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    
    // Récupérer les notifications
    $notificationModel = new Notification();
    $notifications = $notificationModel->getUserNotifications(
        $currentUser['id'], 
        $recipientType, 
        $unreadOnly, 
        $limit
    );
    
    // Compter le total de notifications non lues
    $unreadCount = $notificationModel->getUnreadCount($currentUser['id'], $recipientType);
    
    // Préparer la réponse avec métadonnées
    $response = [
        'success' => true,
        'data' => [
            'notifications' => $notifications,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_returned' => count($notifications),
                'has_more' => count($notifications) === $limit
            ],
            'meta' => [
                'unread_count' => $unreadCount,
                'recipient_type' => $recipientType,
                'user_id' => $currentUser['id']
            ]
        ]
    ];
    
    // Logger l'activité
    if ($recipientType === 'admin') {
        logAdminAction($currentUser['id'], 'view_notifications', 'notification', null, [
            'unread_only' => $unreadOnly,
            'limit' => $limit
        ]);
    }
    
    jsonResponse($response);
    
} catch (Exception $e) {
    error_log("Erreur liste notifications: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur lors de la récupération des notifications',
        'details' => $e->getMessage()
    ], 500);
}
?>