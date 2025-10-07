<?php
/**
 * Modèle Notification - NZELA
 * 
 * Gestion des notifications utilisateurs et administrateurs
 */

require_once __DIR__ . '/Database.php';

class Notification {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    /**
     * Créer une nouvelle notification
     */
    public function create($recipientId, $recipientType, $type, $title, $message, $options = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    recipient_id, recipient_type, sender_id, sender_type, 
                    type, title, message, related_type, related_id, 
                    priority, data, expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $recipientId,
                $recipientType,
                $options['sender_id'] ?? null,
                $options['sender_type'] ?? 'system',
                $type,
                $title,
                $message,
                $options['related_type'] ?? null,
                $options['related_id'] ?? null,
                $options['priority'] ?? 'normal',
                isset($options['data']) ? json_encode($options['data']) : null,
                $options['expires_at'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("Erreur création notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupérer les notifications d'un utilisateur
     */
    public function getUserNotifications($userId, $recipientType = 'citoyen', $unreadOnly = false, $limit = 50) {
        try {
            $whereClause = "recipient_id = ? AND recipient_type = ?";
            $params = [$userId, $recipientType];
            
            if ($unreadOnly) {
                $whereClause .= " AND is_read = FALSE";
            }
            
            // Exclure les notifications expirées
            $whereClause .= " AND (expires_at IS NULL OR expires_at > NOW())";
            
            $stmt = $this->db->prepare("
                SELECT 
                    id, sender_id, sender_type, type, title, message,
                    related_type, related_id, is_read, priority, data,
                    created_at, read_at, expires_at
                FROM notifications 
                WHERE {$whereClause}
                ORDER BY 
                    is_read ASC,
                    priority DESC,
                    created_at DESC 
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur récupération notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compter les notifications non lues
     */
    public function getUnreadCount($userId, $recipientType = 'citoyen') {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE recipient_id = ? AND recipient_type = ? 
                AND is_read = FALSE
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            
            $stmt->execute([$userId, $recipientType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("Erreur comptage notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Marquer une notification comme lue
     */
    public function markAsRead($notificationId, $userId, $recipientType = 'citoyen') {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE id = ? AND recipient_id = ? AND recipient_type = ?
            ");
            
            return $stmt->execute([$notificationId, $userId, $recipientType]);
            
        } catch (Exception $e) {
            error_log("Erreur marquage notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead($userId, $recipientType = 'citoyen') {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE recipient_id = ? AND recipient_type = ? AND is_read = FALSE
            ");
            
            return $stmt->execute([$userId, $recipientType]);
            
        } catch (Exception $e) {
            error_log("Erreur marquage toutes notifications: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprimer une notification
     */
    public function delete($notificationId, $userId, $recipientType = 'citoyen') {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND recipient_id = ? AND recipient_type = ?
            ");
            
            return $stmt->execute([$notificationId, $userId, $recipientType]);
            
        } catch (Exception $e) {
            error_log("Erreur suppression notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprimer les notifications expirées (pour maintenance)
     */
    public function cleanExpiredNotifications() {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE expires_at IS NOT NULL AND expires_at < NOW()
            ");
            
            $stmt->execute();
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Erreur nettoyage notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Récupérer les préférences de notification d'un utilisateur
     */
    public function getUserPreferences($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM notification_preferences WHERE user_id = ?
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Erreur récupération préférences: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Mettre à jour les préférences de notification
     */
    public function updateUserPreferences($userId, $preferences) {
        try {
            // D'abord vérifier si les préférences existent
            $existing = $this->getUserPreferences($userId);
            
            if (!$existing) {
                // Créer nouvelles préférences
                $stmt = $this->db->prepare("
                    INSERT INTO notification_preferences (
                        user_id, nouveau_signalement, statut_change, 
                        admin_message, system_alert, in_app, email, sms, 
                        digest_frequency
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                return $stmt->execute([
                    $userId,
                    $preferences['nouveau_signalement'] ?? true,
                    $preferences['statut_change'] ?? true,
                    $preferences['admin_message'] ?? true,
                    $preferences['system_alert'] ?? true,
                    $preferences['in_app'] ?? true,
                    $preferences['email'] ?? false,
                    $preferences['sms'] ?? false,
                    $preferences['digest_frequency'] ?? 'immediate'
                ]);
                
            } else {
                // Mettre à jour préférences existantes
                $stmt = $this->db->prepare("
                    UPDATE notification_preferences SET
                        nouveau_signalement = ?, statut_change = ?,
                        admin_message = ?, system_alert = ?,
                        in_app = ?, email = ?, sms = ?,
                        digest_frequency = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                
                return $stmt->execute([
                    $preferences['nouveau_signalement'] ?? $existing['nouveau_signalement'],
                    $preferences['statut_change'] ?? $existing['statut_change'],
                    $preferences['admin_message'] ?? $existing['admin_message'],
                    $preferences['system_alert'] ?? $existing['system_alert'],
                    $preferences['in_app'] ?? $existing['in_app'],
                    $preferences['email'] ?? $existing['email'],
                    $preferences['sms'] ?? $existing['sms'],
                    $preferences['digest_frequency'] ?? $existing['digest_frequency'],
                    $userId
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Erreur mise à jour préférences: " . $e->getMessage());
            return false;
        }
    }
}
?>