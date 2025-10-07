<?php
/**
 * NZELA API - Supprimer notifications
 * DELETE /api/notifications/delete.php
 * 
 * Supprimer des notifications spécifiques
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
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez DELETE.'], 405);
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
    
    // Récupérer les paramètres (DELETE peut avoir un body JSON ou des paramètres GET)
    $notificationId = null;
    
    // Essayer d'abord le body JSON
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['notification_id'])) {
            $notificationId = intval($data['notification_id']);
        }
    }
    
    // Fallback sur les paramètres GET
    if (!$notificationId && isset($_GET['notification_id'])) {
        $notificationId = intval($_GET['notification_id']);
    }
    
    if (!$notificationId || $notificationId <= 0) {
        jsonResponse(['error' => 'ID de notification requis et valide'], 400);
    }
    
    // Supprimer la notification
    $notificationModel = new Notification();
    $success = $notificationModel->delete($notificationId, $currentUser['id'], $recipientType);
    
    if ($success) {
        // Logger l'action pour les admins
        if ($recipientType === 'admin') {
            logAdminAction($currentUser['id'], 'delete_notification', 'notification', $notificationId);
        }
        
        // Récupérer le nouveau compte de notifications non lues
        $newUnreadCount = $notificationModel->getUnreadCount($currentUser['id'], $recipientType);
        
        jsonResponse([
            'success' => true,
            'data' => [
                'notification_id' => $notificationId,
                'deleted' => true,
                'new_unread_count' => $newUnreadCount
            ]
        ]);
        
    } else {
        jsonResponse([
            'success' => false,
            'error' => 'Notification non trouvée ou impossible à supprimer'
        ], 404);
    }
    
} catch (Exception $e) {
    error_log("Erreur suppression notification: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur lors de la suppression de la notification',
        'details' => $e->getMessage()
    ], 500);
}
?>