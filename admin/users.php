<?php
/**
 * NZELA API - Gestion des Utilisateurs Administrateurs
 * 
 * GET /api/admin/users.php - Liste des utilisateurs
 * POST /api/admin/users.php - Créer un utilisateur/admin
 * PUT /api/admin/users.php - Modifier un utilisateur
 * DELETE /api/admin/users.php - Supprimer un utilisateur
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Database.php';

// Vérifier l'authentification admin
$currentAdmin = getCurrentAdmin();
if (!$currentAdmin) {
    jsonResponse(['error' => 'Authentification administrateur requise'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetUsers($currentAdmin);
            break;
            
        case 'POST':
            handleCreateUser($currentAdmin);
            break;
            
        case 'PUT':
            handleUpdateUser($currentAdmin);
            break;
            
        case 'DELETE':
            handleDeleteUser($currentAdmin);
            break;
            
        default:
            jsonResponse(['error' => 'Méthode non supportée'], 405);
    }
    
} catch (Exception $e) {
    error_log("Erreur admin/users: " . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur serveur lors de la gestion des utilisateurs',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Lister les utilisateurs avec filtres
 */
function handleGetUsers($currentAdmin) {
    try {
        $database = new Database();
        $db = $database->connect();
        
        // Paramètres de requête
        $role = $_GET['role'] ?? 'all'; // 'all', 'citoyen', 'admin', 'superadmin'
        $status = $_GET['status'] ?? 'all'; // 'all', 'active', 'inactive'
        $search = $_GET['search'] ?? '';
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $orderBy = $_GET['order_by'] ?? 'created_at';
        $orderDir = ($_GET['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        // Construire la clause WHERE
        $whereConditions = [];
        $params = [];
        
        if ($role !== 'all') {
            $whereConditions[] = "role = ?";
            $params[] = $role;
        }
        
        if ($status === 'active') {
            $whereConditions[] = "is_active = 1";
        } elseif ($status === 'inactive') {
            $whereConditions[] = "is_active = 0";
        }
        
        if (!empty($search)) {
            $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Requête pour compter le total
        $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Requête pour récupérer les utilisateurs
        $allowedOrderFields = ['id', 'email', 'role', 'first_name', 'last_name', 'created_at', 'last_login', 'is_active'];
        if (!in_array($orderBy, $allowedOrderFields)) {
            $orderBy = 'created_at';
        }
        
        $sql = "
            SELECT 
                id, email, role, permissions, first_name, last_name, phone, 
                province, is_active, created_at, last_login, last_activity,
                created_by
            FROM users 
            $whereClause 
            ORDER BY $orderBy $orderDir 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les données pour la réponse
        foreach ($users as &$user) {
            $user['permissions'] = $user['permissions'] ? json_decode($user['permissions'], true) : null;
            // Ne pas exposer d'informations sensibles
            unset($user['password_hash']);
        }
        
        // Statistiques rapides
        $statsQueries = [
            'total_citoyens' => "SELECT COUNT(*) FROM users WHERE role = 'citoyen'",
            'total_admins' => "SELECT COUNT(*) FROM users WHERE role IN ('admin', 'superadmin')",
            'active_users' => "SELECT COUNT(*) FROM users WHERE is_active = 1",
            'recent_registrations' => "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ];
        
        $stats = [];
        foreach ($statsQueries as $key => $query) {
            $stmt = $db->prepare($query);
            $stmt->execute();
            $stats[$key] = $stmt->fetchColumn();
        }
        
        // Logger l'action
        logAdminAction($currentAdmin['id'], 'list_users', 'user', null, [
            'filters' => compact('role', 'status', 'search', 'limit', 'page'),
            'total_returned' => count($users)
        ]);
        
        jsonResponse([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalUsers,
                    'pages' => ceil($totalUsers / $limit),
                    'has_next' => ($page * $limit) < $totalUsers,
                    'has_prev' => $page > 1
                ],
                'filters' => compact('role', 'status', 'search', 'orderBy', 'orderDir'),
                'stats' => $stats
            ]
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de la récupération des utilisateurs: " . $e->getMessage());
    }
}

/**
 * Créer un nouvel utilisateur ou administrateur
 */
function handleCreateUser($currentAdmin) {
    try {
        // Vérifier les permissions selon le rôle à créer
        $roleToCreate = $data['role'] ?? 'citoyen';
        
        // Les admins peuvent créer des citoyens
        // Seuls les superadmins peuvent créer des admins/superadmins
        if (($roleToCreate === 'admin' || $roleToCreate === 'superadmin') && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Seuls les super-administrateurs peuvent créer des administrateurs'], 403);
        }
        
        // Les admins et superadmins peuvent créer des citoyens
        if ($currentAdmin['role'] !== 'admin' && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Permissions insuffisantes pour créer des utilisateurs'], 403);
        }
        
        // Récupérer et valider les données
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'Données JSON invalides'], 400);
        }
        
        // Validation des champs requis
        $required = ['email', 'password', 'first_name', 'last_name', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                jsonResponse(['error' => "Le champ '$field' est requis"], 400);
            }
        }
        
        // Validation du role
        $allowedRoles = ['citoyen', 'admin', 'superadmin'];
        if (!in_array($data['role'], $allowedRoles)) {
            jsonResponse(['error' => 'Rôle invalide'], 400);
        }
        
        // Validation de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Email invalide'], 400);
        }
        
        // Validation du mot de passe
        if (strlen($data['password']) < 6) {
            jsonResponse(['error' => 'Le mot de passe doit contenir au moins 6 caractères'], 400);
        }
        
        $userModel = new User();
        
        // Vérifier que l'email n'existe pas déjà
        if ($userModel->emailExists($data['email'])) {
            jsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
        }
        
        // Préparer les permissions par défaut selon le rôle
        $defaultPermissions = [
            'citoyen' => null,
            'admin' => [
                'view_signalements' => true,
                'manage_signalements' => true,
                'view_stats' => true,
                'manage_types' => false
            ],
            'superadmin' => [
                'view_signalements' => true,
                'manage_signalements' => true,
                'view_stats' => true,
                'manage_types' => true,
                'manage_users' => true,
                'manage_admins' => true
            ]
        ];
        
        // Créer l'utilisateur
        $userData = [
            'email' => $data['email'],
            'password' => $data['password'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null,
            'role' => $data['role'],
            'permissions' => $defaultPermissions[$data['role']],
            'created_by' => $currentAdmin['id'],
            'is_active' => $data['is_active'] ?? true
        ];
        
        if ($data['role'] === 'admin' || $data['role'] === 'superadmin') {
            $userId = $userModel->createAdmin($userData);
        } else {
            $userId = $userModel->register($userData);
        }
        
        if ($userId) {
            // Logger l'action
            logAdminAction($currentAdmin['id'], 'create_user', 'user', $userId, [
                'role' => $data['role'],
                'email' => $data['email']
            ]);
            
            // Créer une notification de bienvenue pour le nouvel utilisateur
            if ($data['role'] !== 'citoyen') {
                createNotification(
                    $userId,
                    'admin',
                    'admin_message',
                    'Bienvenue dans l\'équipe administrative NZELA',
                    "Votre compte administrateur a été créé avec le rôle '{$data['role']}'. Vous pouvez maintenant vous connecter et accéder au panneau d'administration.",
                    [
                        'sender_id' => $currentAdmin['id'],
                        'sender_type' => 'admin',
                        'priority' => 'high',
                        'data' => ['welcome' => true, 'role' => $data['role']]
                    ]
                );
            }
            
            jsonResponse([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => [
                    'user_id' => $userId,
                    'role' => $data['role'],
                    'email' => $data['email']
                ]
            ], 201);
            
        } else {
            jsonResponse(['error' => 'Erreur lors de la création de l\'utilisateur'], 500);
        }
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de la création de l'utilisateur: " . $e->getMessage());
    }
}

/**
 * Modifier un utilisateur existant
 */
function handleUpdateUser($currentAdmin) {
    try {
        // Récupérer et valider les données
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonResponse(['error' => 'Données JSON invalides'], 400);
        }
        
        if (empty($data['user_id'])) {
            jsonResponse(['error' => 'ID utilisateur requis'], 400);
        }
        
        $userId = intval($data['user_id']);
        $userModel = new User();
        
        // Récupérer l'utilisateur existant
        $existingUser = $userModel->getUserById($userId);
        if (!$existingUser) {
            jsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }
        
        // Vérifier les permissions selon les rôles
        if ($existingUser['role'] === 'superadmin' && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Seuls les super-administrateurs peuvent modifier d\'autres super-administrateurs'], 403);
        }
        
        // Les admins peuvent modifier les citoyens, mais pas d'autres admins
        if ($existingUser['role'] === 'admin' && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Seuls les super-administrateurs peuvent modifier d\'autres administrateurs'], 403);
        }
        
        // Vérifier si on essaie de changer le rôle vers admin/superadmin
        if (isset($data['role']) && ($data['role'] === 'admin' || $data['role'] === 'superadmin') && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Seuls les super-administrateurs peuvent promouvoir des utilisateurs au rôle d\'administrateur'], 403);
        }
        
        // Préparer les données de mise à jour
        $updateData = [];
        $allowedFields = ['first_name', 'last_name', 'phone', 'province', 'is_active', 'permissions'];
        
        // Gérer les permissions par champ selon le rôle
        if ($currentAdmin['role'] === 'superadmin') {
            // Les superadmins peuvent tout modifier
            $allowedFields[] = 'role';
            $allowedFields[] = 'email';
        } elseif ($currentAdmin['role'] === 'admin') {
            // Les admins peuvent modifier l'email des citoyens
            if ($existingUser['role'] === 'citoyen') {
                $allowedFields[] = 'email';
            }
            // Mais ne peuvent pas changer les rôles
        }
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Validation de l'email si fourni
        if (isset($updateData['email'])) {
            if (!filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Email invalide'], 400);
            }
            
            // Vérifier l'unicité (sauf pour l'utilisateur actuel)
            if ($updateData['email'] !== $existingUser['email'] && $userModel->emailExists($updateData['email'])) {
                jsonResponse(['error' => 'Cet email est déjà utilisé'], 409);
            }
        }
        
        // Validation du rôle si fourni
        if (isset($updateData['role'])) {
            $allowedRoles = ['citoyen', 'admin', 'superadmin'];
            if (!in_array($updateData['role'], $allowedRoles)) {
                jsonResponse(['error' => 'Rôle invalide'], 400);
            }
        }
        
        // Gestion du changement de mot de passe
        if (!empty($data['new_password'])) {
            if (strlen($data['new_password']) < 6) {
                jsonResponse(['error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'], 400);
            }
            $updateData['password'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }
        
        // Effectuer la mise à jour
        $success = $userModel->updateAdmin($userId, $updateData);
        
        if ($success) {
            // Logger l'action
            logAdminAction($currentAdmin['id'], 'update_user', 'user', $userId, [
                'updated_fields' => array_keys($updateData),
                'old_values' => array_intersect_key($existingUser, $updateData)
            ]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => [
                    'user_id' => $userId,
                    'updated_fields' => array_keys($updateData)
                ]
            ]);
            
        } else {
            jsonResponse(['error' => 'Erreur lors de la mise à jour'], 500);
        }
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de la mise à jour de l'utilisateur: " . $e->getMessage());
    }
}

/**
 * Supprimer un utilisateur
 */
function handleDeleteUser($currentAdmin) {
    try {
        // Récupérer l'ID depuis les paramètres ou le body JSON
        $userId = null;
        
        if (!empty($_GET['user_id'])) {
            $userId = intval($_GET['user_id']);
        } else {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['user_id'])) {
                $userId = intval($data['user_id']);
            }
        }
        
        if (!$userId) {
            jsonResponse(['error' => 'ID utilisateur requis'], 400);
        }
        
        // Vérifier les permissions de suppression
        if ($currentAdmin['role'] !== 'admin' && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Permissions insuffisantes pour supprimer des utilisateurs'], 403);
        }
        
        $userModel = new User();
        
        // Récupérer l'utilisateur à supprimer
        $userToDelete = $userModel->getUserById($userId);
        if (!$userToDelete) {
            jsonResponse(['error' => 'Utilisateur non trouvé'], 404);
        }
        
        // Empêcher la suppression de soi-même
        if ($userId == $currentAdmin['id']) {
            jsonResponse(['error' => 'Vous ne pouvez pas supprimer votre propre compte'], 400);
        }
        
        // Les admins ne peuvent supprimer que des citoyens
        if ($currentAdmin['role'] === 'admin' && $userToDelete['role'] !== 'citoyen') {
            jsonResponse(['error' => 'Les administrateurs ne peuvent supprimer que des citoyens'], 403);
        }
        
        // Seuls les superadmins peuvent supprimer d'autres admins
        if ($userToDelete['role'] === 'superadmin' && $currentAdmin['role'] !== 'superadmin') {
            jsonResponse(['error' => 'Seuls les super-administrateurs peuvent supprimer d\'autres super-administrateurs'], 403);
        }
        
        // Effectuer la suppression (soft delete ou hard delete selon la logique métier)
        $success = $userModel->deleteUser($userId);
        
        if ($success) {
            // Logger l'action
            logAdminAction($currentAdmin['id'], 'delete_user', 'user', $userId, [
                'deleted_user' => [
                    'email' => $userToDelete['email'],
                    'role' => $userToDelete['role'],
                    'name' => $userToDelete['first_name'] . ' ' . $userToDelete['last_name']
                ]
            ]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès',
                'data' => [
                    'user_id' => $userId,
                    'email' => $userToDelete['email']
                ]
            ]);
            
        } else {
            jsonResponse(['error' => 'Erreur lors de la suppression'], 500);
        }
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de la suppression de l'utilisateur: " . $e->getMessage());
    }
}
?>