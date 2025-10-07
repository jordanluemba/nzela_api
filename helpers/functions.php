<?php
/**
 * NZELA - Fonctions Utilitaires
 * Fonctions communes utilisées dans toute l'API
 */

/**
 * Vérifier si un utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifier l'authentification et arrêter si non connecté
 */
function requireAuth() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentification requise'], 401);
    }
}

/**
 * Retourner une réponse JSON et arrêter l'exécution
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Récupérer les données JSON de la requête
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    
    // Vérifier que l'input n'est pas vide
    if (empty($input)) {
        return [];
    }
    
    // Décoder le JSON
    $decoded = json_decode($input, true);
    
    // Vérifier les erreurs JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON invalide: ' . json_last_error_msg() . ' - Input reçu: ' . substr($input, 0, 100));
    }
    
    return $decoded ?: [];
}

/**
 * Générer un code unique de signalement
 */
function generateSignalementCode() {
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return "NZELA-{$year}{$month}{$day}-{$random}";
}

/**
 * Valider une adresse email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hasher un mot de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Vérifier un mot de passe
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Valider les champs requis
 */
function validateRequired($data, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            jsonResponse(['error' => "Le champ '{$field}' est requis"], 400);
        }
    }
}

/**
 * Nettoyer et sécuriser une chaîne
 */
function sanitizeString($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifier si une méthode HTTP est autorisée
 */
function checkMethod($allowedMethods) {
    $currentMethod = $_SERVER['REQUEST_METHOD'];
    if (!in_array($currentMethod, $allowedMethods)) {
        jsonResponse(['error' => 'Méthode non autorisée'], 405);
    }
}

/**
 * Obtenir l'adresse IP du client
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Logger une erreur (simple)
 */
function logError($message) {
    error_log("[NZELA API] " . date('Y-m-d H:i:s') . " - " . $message);
}

/**
 * Générer l'URL complète d'une image
 */
function getImageUrl($imagePath) {
    if (empty($imagePath)) {
        return null;
    }
    
    // Construire l'URL de base
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Déterminer le chemin de base de l'API
    $scriptName = $_SERVER['SCRIPT_NAME']; // ex: /api/types/list.php
    $apiBasePath = dirname(dirname($scriptName)); // ex: /api
    
    // Si on est déjà à la racine, ajuster
    if ($apiBasePath === '/' || $apiBasePath === '.') {
        $apiBasePath = '/api';
    }
    
    $baseUrl = $protocol . '://' . $host . $apiBasePath;
    
    return $baseUrl . '/image.php?path=' . urlencode($imagePath);
}

/**
 * Formatter un signalement avec URLs d'images
 */
function formatSignalementWithImages($signalement) {
    if (!is_array($signalement)) {
        return $signalement;
    }
    
    // Ajouter l'URL de la photo principale
    if (!empty($signalement['photo_principale'])) {
        $photoPath = $signalement['photo_principale'];
        
        // Si ce n'est qu'un nom de fichier, ajouter le chemin uploads/signalements/
        if (strpos($photoPath, '/') === false) {
            $photoPath = 'uploads/signalements/' . $photoPath;
        }
        
        $signalement['photo_url'] = getImageUrl($photoPath);
    } else {
        $signalement['photo_url'] = null;
    }
    
    // Ajouter l'URL de l'image du type si présente
    if (!empty($signalement['type_image']) || !empty($signalement['image_path'])) {
        $typeImagePath = $signalement['type_image'] ?? $signalement['image_path'];
        
        // Corriger automatiquement les anciens chemins d'images de types
        if (strpos($typeImagePath, '../assets/img/') === 0) {
            $typeImagePath = str_replace('../assets/img/', 'uploads/types/', $typeImagePath);
        }
        
        $signalement['type_image_url'] = getImageUrl($typeImagePath);
    } else {
        $signalement['type_image_url'] = null;
    }
    
    return $signalement;
}

/**
 * Formatter un type avec URL d'image
 */
function formatTypeWithImage($type) {
    if (!is_array($type)) {
        return $type;
    }
    
    // Corriger automatiquement les anciens chemins d'images
    if (!empty($type['image_path'])) {
        // Convertir les anciens chemins ../assets/img/ vers uploads/types/
        if (strpos($type['image_path'], '../assets/img/') === 0) {
            $type['image_path'] = str_replace('../assets/img/', 'uploads/types/', $type['image_path']);
        }
        
        $type['image_url'] = getImageUrl($type['image_path']);
    } else {
        $type['image_url'] = null;
    }
    
    return $type;
}

/**
 * ============================================================================
 * FONCTIONS D'AUTHENTIFICATION ADMIN
 * ============================================================================
 */

/**
 * Vérifier si l'utilisateur connecté est un administrateur (sessions classiques)
 * DEPRECATED - Utiliser la nouvelle authentification admin avec tokens
 */
function isAdminClassic() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? 'citoyen';
    return in_array($userRole, ['admin', 'superadmin']);
}

/**
 * Vérifier si l'utilisateur connecté est un super administrateur (sessions classiques)
 * DEPRECATED - Utiliser la nouvelle authentification admin avec tokens
 */
function isSuperAdminClassic() {
    if (!isLoggedIn()) {
        return false;
    }
    
    return ($_SESSION['user_role'] ?? 'citoyen') === 'superadmin';
}

/**
 * Exiger des privilèges d'administrateur (sessions classiques - DEPRECATED)
 */
function requireAdminClassic() {
    if (!isAdminClassic()) {
        jsonResponse([
            'error' => 'Accès refusé',
            'message' => 'Privilèges d\'administrateur requis'
        ], 403);
    }
}

/**
 * Exiger des privilèges de super administrateur (sessions classiques - DEPRECATED)
 */
function requireSuperAdminClassic() {
    if (!isSuperAdminClassic()) {
        jsonResponse([
            'error' => 'Accès refusé', 
            'message' => 'Privilèges de super administrateur requis'
        ], 403);
    }
}

/**
 * Obtenir le rôle de l'utilisateur connecté
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? 'citoyen';
}

/**
 * Obtenir les informations complètes de l'utilisateur connecté
 */
function getCurrentUserInfo() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'] ?? null,
        'first_name' => $_SESSION['user_first_name'] ?? null,
        'last_name' => $_SESSION['user_last_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? 'citoyen',
        'permissions' => $_SESSION['user_permissions'] ?? null
    ];
}

/**
 * Vérifier une permission spécifique pour l'utilisateur connecté
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Super admin a toutes les permissions
    if (isSuperAdmin()) {
        return true;
    }
    
    // Vérifier dans les permissions JSON
    $permissions = $_SESSION['user_permissions'] ?? null;
    if (is_string($permissions)) {
        $permissions = json_decode($permissions, true);
    }
    
    if (!is_array($permissions)) {
        return false;
    }
    
    return in_array($permission, $permissions);
}

/**
 * Logger une action admin pour l'audit
 */
function logAdminAction($action, $targetType, $targetId = null, $oldValues = null, $newValues = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->connect();
        
        $stmt = $db->prepare("
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $targetType,
            $targetId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return true;
    } catch (Exception $e) {
        logError("Erreur logging action admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour la dernière activité de l'utilisateur
 */
function updateLastActivity() {
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->connect();
        
        $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        return true;
    } catch (Exception $e) {
        logError("Erreur mise à jour activité: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir les statistiques rapides pour le dashboard admin
 */
function getAdminDashboardStats() {
    requireAdmin();
    
    try {
        $database = new Database();
        $db = $database->connect();
        
        $stats = [];
        
        // Nombre total de signalements
        $stmt = $db->query("SELECT COUNT(*) as total FROM signalements");
        $stats['total_signalements'] = $stmt->fetchColumn();
        
        // Signalements par statut
        $stmt = $db->query("SELECT statut, COUNT(*) as count FROM signalements GROUP BY statut");
        $stats['signalements_par_statut'] = $stmt->fetchAll();
        
        // Signalements en attente (priorité)
        $stmt = $db->query("SELECT COUNT(*) as count FROM signalements WHERE statut = 'En attente'");
        $stats['en_attente'] = $stmt->fetchColumn();
        
        // Nombre total d'utilisateurs
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
        $stats['total_utilisateurs'] = $stmt->fetchColumn();
        
        // Derniers signalements
        $stmt = $db->query("SELECT id, code, description, statut, created_at FROM signalements ORDER BY created_at DESC LIMIT 5");
        $stats['derniers_signalements'] = $stmt->fetchAll();
        
        return $stats;
    } catch (Exception $e) {
        logError("Erreur stats dashboard: " . $e->getMessage());
        return null;
    }
}

// ===============================================
// FONCTIONS D'AUTHENTIFICATION ADMINISTRATEUR
// ===============================================

/**
 * Vérifier et récupérer l'administrateur connecté depuis le token
 */
function getCurrentAdmin() {
    static $currentAdmin = null;
    
    if ($currentAdmin !== null) {
        return $currentAdmin;
    }
    
    // Récupérer le token depuis les headers
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return false;
    }
    
    $sessionToken = $matches[1];
    
    // Vérifier la session
    try {
        require_once __DIR__ . '/../models/User.php';
        $userModel = new User();
        $currentAdmin = $userModel->verifyAdminSession($sessionToken);
        
        if ($currentAdmin) {
            // Mettre à jour la dernière activité
            $userModel->updateLastActivity($currentAdmin['id']);
        }
        
        return $currentAdmin;
        
    } catch (Exception $e) {
        error_log("Erreur vérification admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier si l'utilisateur actuel est un administrateur
 */
function isAdmin() {
    $admin = getCurrentAdmin();
    return $admin && in_array($admin['role'], ['admin', 'superadmin']);
}

/**
 * Vérifier si l'utilisateur actuel est un super administrateur
 */
function isSuperAdmin() {
    $admin = getCurrentAdmin();
    return $admin && $admin['role'] === 'superadmin';
}

/**
 * Requérir une authentification administrateur
 */
function requireAdmin() {
    if (!isAdmin()) {
        jsonResponse(['error' => 'Accès refusé. Privilèges administrateur requis.'], 403);
    }
}

/**
 * Requérir une authentification super administrateur
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        jsonResponse(['error' => 'Accès refusé. Privilèges super administrateur requis.'], 403);
    }
}

/**
 * Vérifier une permission spécifique pour l'admin actuel
 */
function hasAdminPermission($permission) {
    $admin = getCurrentAdmin();
    if (!$admin) return false;
    
    // Super admin a toutes les permissions
    if ($admin['role'] === 'superadmin') return true;
    
    // Vérifier dans les permissions JSON
    if ($admin['permissions']) {
        $permissions = json_decode($admin['permissions'], true);
        return in_array($permission, $permissions ?? []);
    }
    
    return false;
}

/**
 * Logger une action administrateur pour audit (sessions classiques - DEPRECATED)
 */
function logAdminActionClassic($action, $targetType, $targetId = null, $oldValues = null, $newValues = null) {
    $admin = getCurrentAdmin();
    if (!$admin) return;
    
    try {
        $database = new Database();
        $pdo = $database->connect();
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, old_values, new_values, ip_address, user_agent) 
            VALUES (:admin_id, :action, :target_type, :target_id, :old_values, :new_values, :ip, :user_agent)
        ");
        
        $stmt->execute([
            'admin_id' => $admin['id'],
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur log audit admin: " . $e->getMessage());
    }
}

// ========================================
// FONCTIONS DE NOTIFICATIONS
// ========================================

/**
 * Créer une notification pour un utilisateur
 */
function createNotification($recipientId, $recipientType, $type, $title, $message, $options = []) {
    try {
        require_once __DIR__ . '/../models/Notification.php';
        
        $notification = new Notification();
        return $notification->create($recipientId, $recipientType, $type, $title, $message, $options);
        
    } catch (Exception $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier tous les admins d'un nouveau signalement
 */
function notifyAdminsNewSignalement($signalementId, $signalement) {
    try {
        $database = new Database();
        $db = $database->connect();
        
        // Récupérer tous les admins
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE role IN ('admin', 'superadmin') AND is_active = 1
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $title = "Nouveau signalement #" . $signalementId;
        $message = "Un nouveau signalement a été créé : " . $signalement['titre'];
        
        $options = [
            'sender_type' => 'system',
            'related_type' => 'signalement',
            'related_id' => $signalementId,
            'priority' => 'normal',
            'data' => [
                'signalement_id' => $signalementId,
                'type' => $signalement['type_id'] ?? null,
                'localisation' => $signalement['localisation'] ?? null
            ]
        ];
        
        $successCount = 0;
        foreach ($admins as $admin) {
            if (createNotification($admin['id'], 'admin', 'nouveau_signalement', $title, $message, $options)) {
                $successCount++;
            }
        }
        
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("Erreur notification admins: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifier un citoyen du changement de statut de son signalement
 */
function notifySignalementStatusChange($signalementId, $citizenId, $oldStatus, $newStatus, $comment = null) {
    try {
        $statusLabels = [
            'en_attente' => 'En attente',
            'en_cours' => 'En cours de traitement',
            'resolu' => 'Résolu',
            'rejete' => 'Rejeté'
        ];
        
        $title = "Signalement #" . $signalementId . " - Statut mis à jour";
        
        $message = "Votre signalement est maintenant : " . ($statusLabels[$newStatus] ?? $newStatus);
        if ($comment) {
            $message .= "\n\nCommentaire : " . $comment;
        }
        
        $priority = ($newStatus === 'resolu') ? 'normal' : 'high';
        
        $options = [
            'sender_type' => 'admin',
            'related_type' => 'signalement',
            'related_id' => $signalementId,
            'priority' => $priority,
            'data' => [
                'signalement_id' => $signalementId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'comment' => $comment
            ]
        ];
        
        return createNotification($citizenId, 'citoyen', 'statut_change', $title, $message, $options);
        
    } catch (Exception $e) {
        error_log("Erreur notification changement statut: " . $e->getMessage());
        return false;
    }
}

/**
 * Créer une notification de confirmation pour un nouveau signalement
 */
function notifySignalementConfirmation($signalementId, $citizenId, $signalement) {
    try {
        $title = "Signalement créé avec succès";
        $message = "Votre signalement #" . $signalementId . " a été enregistré : " . $signalement['titre'];
        $message .= "\n\nNous vous tiendrons informé de son traitement.";
        
        $options = [
            'sender_type' => 'system',
            'related_type' => 'signalement',
            'related_id' => $signalementId,
            'priority' => 'low',
            'data' => [
                'signalement_id' => $signalementId,
                'type' => 'confirmation'
            ]
        ];
        
        return createNotification($citizenId, 'citoyen', 'confirmation', $title, $message, $options);
        
    } catch (Exception $e) {
        error_log("Erreur notification confirmation: " . $e->getMessage());
        return false;
    }
}

/**
 * Envoyer une notification système (maintenance, etc.)
 */
function sendSystemNotification($title, $message, $targetType = 'all', $priority = 'normal') {
    try {
        $database = new Database();
        $db = $database->connect();
        
        $whereClause = "is_active = 1";
        if ($targetType === 'admins') {
            $whereClause .= " AND role IN ('admin', 'superadmin')";
        } elseif ($targetType === 'citoyens') {
            $whereClause .= " AND role = 'citoyen'";
        }
        
        $stmt = $db->prepare("SELECT id, role FROM users WHERE {$whereClause}");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $options = [
            'sender_type' => 'system',
            'priority' => $priority,
            'data' => [
                'broadcast' => true,
                'target' => $targetType
            ]
        ];
        
        $successCount = 0;
        foreach ($users as $user) {
            $recipientType = ($user['role'] === 'citoyen') ? 'citoyen' : 'admin';
            
            if (createNotification($user['id'], $recipientType, 'system_alert', $title, $message, $options)) {
                $successCount++;
            }
        }
        
        return $successCount;
        
    } catch (Exception $e) {
        error_log("Erreur notification système: " . $e->getMessage());
        return 0;
    }
}

/**
 * Obtenir le nombre de notifications non lues pour un utilisateur
 */
function getUnreadNotificationCount($userId, $userRole = 'citoyen') {
    try {
        require_once __DIR__ . '/../models/Notification.php';
        
        $notification = new Notification();
        $recipientType = ($userRole === 'citoyen') ? 'citoyen' : 'admin';
        
        return $notification->getUnreadCount($userId, $recipientType);
        
    } catch (Exception $e) {
        error_log("Erreur comptage notifications: " . $e->getMessage());
        return 0;
    }
}
?>