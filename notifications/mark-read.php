<?php
/**
 * NZELA API - Marquer notifications comme lues
 * POST /api/notifications/mark-read.php
 * 
 * Marquer une ou plusieurs notifications comme lues
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
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
    
    // Vérifier le contenu JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') === false) {
        jsonResponse(['error' => 'Content-Type doit être application/json'], 400);
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'JSON invalide'], 400);
    }
    
    $notificationModel = new Notification();
    $results = [];
    $successCount = 0;
    
    // Cas 1: Marquer toutes les notifications comme lues
    if (isset($data['mark_all']) && $data['mark_all'] === true) {
        
        $success = $notificationModel->markAllAsRead($currentUser['id'], $recipientType);
        
        if ($success) {
            $results['mark_all'] = 'success';
            $successCount = 1;
            
            // Logger l'action
            if ($recipientType === 'admin') {
                logAdminAction($currentUser['id'], 'mark_all_notifications_read', 'notification', null);
            }
        } else {
            $results['mark_all'] = 'error';
        }
        
    }
    // Cas 2: Marquer des notifications spécifiques
    elseif (isset($data['notification_ids']) && is_array($data['notification_ids'])) {
        
        foreach ($data['notification_ids'] as $notificationId) {
            $notificationId = intval($notificationId);
            
            if ($notificationId > 0) {
                $success = $notificationModel->markAsRead($notificationId, $currentUser['id'], $recipientType);
                
                if ($success) {
                    $results['notifications'][$notificationId] = 'success';
                    $successCount++;
                } else {
                    $results['notifications'][$notificationId] = 'error';
                }
            } else {
                $results['notifications'][$notificationId] = 'invalid_id';
            }
        }
        
        // Logger l'action pour les admins
        if ($recipientType === 'admin' && $successCount > 0) {
            logAdminAction($currentUser['id'], 'mark_notifications_read', 'notification', null, [
                'count' => $successCount,
                'notification_ids' => array_keys(array_filter($results['notifications'], function($status) {
                    return $status === 'success';
                }))
            ]);
        }
        
    }
    // Cas 3: Marquer une seule notification
    elseif (isset($data['notification_id'])) {
        
        $notificationId = intval($data['notification_id']);
        
        if ($notificationId > 0) {
            $success = $notificationModel->markAsRead($notificationId, $currentUser['id'], $recipientType);
            
            if ($success) {
                $results['notification'] = $notificationId;
                $results['status'] = 'success';
                $successCount = 1;
                
                // Logger l'action
                if ($recipientType === 'admin') {
                    logAdminAction($currentUser['id'], 'mark_notification_read', 'notification', $notificationId);
                }
            } else {
                $results['notification'] = $notificationId;
                $results['status'] = 'error';
            }
        } else {
            jsonResponse(['error' => 'ID de notification invalide'], 400);
        }
        
    } else {
        jsonResponse(['error' => 'Paramètres requis: notification_id, notification_ids[], ou mark_all'], 400);
    }
    
    // Récupérer le nouveau compte de notifications non lues
    $newUnreadCount = $notificationModel->getUnreadCount($currentUser['id'], $recipientType);
    
    jsonResponse([
        'success' => true,
        'data' => [
            'results' => $results,
            'success_count' => $successCount,
            'new_unread_count' => $newUnreadCount
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur marquage notifications: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur lors du marquage des notifications',
        'details' => $e->getMessage()
    ], 500);
}
?>