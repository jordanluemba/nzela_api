<?php
/**
 * NZELA - Modèle User
 * Gestion des utilisateurs (table users)
 */

require_once __DIR__ . '/Database.php';

class User extends DatabaseModel {
    
    /**
     * Créer un nouvel utilisateur
     */
    public function create($data) {
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, phone, province) 
                VALUES (:email, :password_hash, :first_name, :last_name, :phone, :province)";
        
        return $this->insert($sql, [
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null
        ]);
    }
    
    /**
     * Connexion utilisateur
     */
    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = :email AND is_active = 1";
        $user = $this->fetch($sql, ['email' => $email]);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Vérifier si un email existe
     */
    public function emailExists($email) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
        return $this->count($sql, ['email' => $email]) > 0;
    }
    
    /**
     * Récupérer un utilisateur par ID
     */
    public function getById($id) {
        $sql = "SELECT id, email, first_name, last_name, phone, province, role, permissions, 
                       created_at, last_login, last_activity 
                FROM users WHERE id = :id AND is_active = 1";
        return $this->fetch($sql, ['id' => $id]);
    }
    
    /**
     * Mettre à jour la dernière connexion
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $this->query($sql, ['id' => $userId]);
    }
    
    /**
     * Mettre à jour le profil utilisateur
     */
    public function updateProfile($userId, $data) {
        $sql = "UPDATE users SET 
                first_name = :first_name,
                last_name = :last_name,
                phone = :phone,
                province = :province
                WHERE id = :id";
        
        return $this->query($sql, [
            'id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'province' => $data['province'] ?? null
        ]);
    }
    
    /**
     * Compter les signalements d'un utilisateur
     */
    public function getSignalementsCount($userId) {
        $sql = "SELECT COUNT(*) FROM signalements WHERE user_id = :user_id";
        return $this->count($sql, ['user_id' => $userId]);
    }
    
    /**
     * Mettre à jour un utilisateur
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Erreur mise à jour utilisateur: " . $e->getMessage());
        }
    }
    
    /**
     * Compter les signalements d'un utilisateur
     */
    public function countSignalements($userId) {
        $sql = "SELECT COUNT(*) FROM signalements WHERE user_id = :user_id AND statut != 'Supprimé'";
        
        try {
            $stmt = $this->query($sql, ['user_id' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Supprimer définitivement un compte utilisateur
     */
    public function deleteAccount($userId, $options = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Si on veut conserver les signalements, les anonymiser
            if (isset($options['anonymize_signalements']) && $options['anonymize_signalements']) {
                $sql = "UPDATE signalements SET 
                            user_id = NULL,
                            nom_citoyen = 'Utilisateur supprimé',
                            telephone = NULL
                        WHERE user_id = :user_id";
                $this->query($sql, ['user_id' => $userId]);
            }
            
            // Supprimer l'utilisateur
            $sql = "DELETE FROM users WHERE id = :id";
            $stmt = $this->query($sql, ['id' => $userId]);
            $deleted = $stmt->rowCount() > 0;
            
            $this->pdo->commit();
            return $deleted;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Erreur suppression compte: " . $e->getMessage());
        }
    }

    /**
     * Mettre à jour la dernière activité
     */
    public function updateLastActivity($userId) {
        $sql = "UPDATE users SET last_activity = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $userId]);
    }

    /**
     * Obtenir tous les administrateurs
     */
    public function getAdmins($filters = []) {
        $where = ["role IN ('admin', 'superadmin')"];
        $params = [];
        
        if (isset($filters['role']) && in_array($filters['role'], ['admin', 'superadmin'])) {
            $where[] = "role = :role";
            $params['role'] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $where[] = "is_active = :is_active";
            $params['is_active'] = $filters['is_active'] ? 1 : 0;
        }
        
        $sql = "
            SELECT id, email, first_name, last_name, role, permissions, 
                   is_active, created_at, last_login, last_activity,
                   created_by
            FROM users 
            WHERE " . implode(' AND ', $where) . "
            ORDER BY role DESC, created_at DESC
        ";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Créer un administrateur
     */
    public function createAdmin($data, $createdBy = null) {
        // Vérifier que l'email n'existe pas
        if ($this->emailExists($data['email'])) {
            throw new Exception("Un utilisateur avec cet email existe déjà");
        }
        
        // Valider le rôle
        if (!in_array($data['role'], ['admin', 'superadmin'])) {
            throw new Exception("Rôle invalide");
        }
        
        $sql = "INSERT INTO users (email, password_hash, first_name, last_name, role, permissions, created_by, is_active) 
                VALUES (:email, :password_hash, :first_name, :last_name, :role, :permissions, :created_by, 1)";
        
        return $this->insert($sql, [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null,
            'created_by' => $createdBy
        ]);
    }

    /**
     * Mettre à jour un administrateur
     */
    public function updateAdmin($id, $data, $updatedBy = null) {
        $fields = [];
        $params = ['id' => $id];
        
        if (isset($data['email'])) {
            // Vérifier que l'email n'est pas pris par un autre utilisateur
            $sql = "SELECT COUNT(*) FROM users WHERE email = :email AND id != :id";
            if ($this->count($sql, ['email' => $data['email'], 'id' => $id]) > 0) {
                throw new Exception("Un autre utilisateur utilise déjà cet email");
            }
            $fields[] = "email = :email";
            $params['email'] = $data['email'];
        }
        
        if (isset($data['first_name'])) {
            $fields[] = "first_name = :first_name";
            $params['first_name'] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $fields[] = "last_name = :last_name";
            $params['last_name'] = $data['last_name'];
        }
        
        if (isset($data['role']) && in_array($data['role'], ['admin', 'superadmin'])) {
            $fields[] = "role = :role";
            $params['role'] = $data['role'];
        }
        
        if (isset($data['permissions'])) {
            $fields[] = "permissions = :permissions";
            $params['permissions'] = is_array($data['permissions']) ? json_encode($data['permissions']) : $data['permissions'];
        }
        
        if (isset($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        if (empty($fields)) {
            throw new Exception("Aucune donnée à mettre à jour");
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->execute($sql, $params);
    }
    
    /**
     * Récupérer un utilisateur par son ID
     */
    public function getUserById($userId) {
        $sql = "SELECT * FROM users WHERE id = :id";
        return $this->fetch($sql, ['id' => $userId]);
    }
    
    /**
     * Supprimer un utilisateur (soft delete ou hard delete)
     */
    public function deleteUser($userId) {
        try {
            // Option 1: Soft delete (désactiver l'utilisateur)
            $sql = "UPDATE users SET is_active = 0, email = CONCAT('deleted_', id, '_', email) WHERE id = :id";
            return $this->execute($sql, ['id' => $userId]);
            
            // Option 2: Hard delete (décommenter si nécessaire)
            // $sql = "DELETE FROM users WHERE id = :id";
            // return $this->execute($sql, ['id' => $userId]);
            
        } catch (Exception $e) {
            error_log("Erreur suppression utilisateur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthode register mise à jour pour supporter les rôles
     */
    public function register($data) {
        try {
            $sql = "INSERT INTO users (
                email, password_hash, first_name, last_name, phone, province, 
                role, permissions, created_by, is_active
            ) VALUES (
                :email, :password_hash, :first_name, :last_name, :phone, :province,
                :role, :permissions, :created_by, :is_active
            )";
            
            $params = [
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'province' => $data['province'] ?? null,
                'role' => $data['role'] ?? 'citoyen',
                'permissions' => $data['permissions'] ? json_encode($data['permissions']) : null,
                'created_by' => $data['created_by'] ?? null,
                'is_active' => $data['is_active'] ?? true
            ];
            
            return $this->insert($sql, $params);
            
        } catch (Exception $e) {
            error_log("Erreur création utilisateur: " . $e->getMessage());
            throw new Exception("Impossible de créer l'utilisateur");
        }
    }
    
    /**
     * Rechercher des utilisateurs avec critères
     */
    public function searchUsers($criteria = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Construire la clause WHERE dynamiquement
            if (!empty($criteria['role'])) {
                $whereConditions[] = "role = :role";
                $params['role'] = $criteria['role'];
            }
            
            if (isset($criteria['is_active'])) {
                $whereConditions[] = "is_active = :is_active";
                $params['is_active'] = $criteria['is_active'] ? 1 : 0;
            }
            
            if (!empty($criteria['search'])) {
                $whereConditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
                $params['search'] = '%' . $criteria['search'] . '%';
            }
            
            if (!empty($criteria['created_after'])) {
                $whereConditions[] = "created_at >= :created_after";
                $params['created_after'] = $criteria['created_after'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Gestion du tri
            $orderBy = $criteria['order_by'] ?? 'created_at';
            $orderDir = ($criteria['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            
            // Gestion de la pagination
            $limit = min(100, max(1, intval($criteria['limit'] ?? 20)));
            $offset = max(0, intval($criteria['offset'] ?? 0));
            
            $sql = "
                SELECT id, email, role, permissions, first_name, last_name, 
                       phone, province, is_active, created_at, last_login, 
                       last_activity, created_by
                FROM users 
                $whereClause 
                ORDER BY $orderBy $orderDir 
                LIMIT :limit OFFSET :offset
            ";
            
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            return $this->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Erreur recherche utilisateurs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtenir les statistiques des utilisateurs
     */
    public function getUserStats() {
        try {
            $stats = [];
            
            // Total par rôle
            $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
            $roleStats = $this->fetchAll($sql);
            
            foreach ($roleStats as $stat) {
                $stats['by_role'][$stat['role']] = intval($stat['count']);
            }
            
            // Utilisateurs actifs/inactifs
            $sql = "SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active";
            $statusStats = $this->fetchAll($sql);
            
            foreach ($statusStats as $stat) {
                $key = $stat['is_active'] ? 'active' : 'inactive';
                $stats['by_status'][$key] = intval($stat['count']);
            }
            
            // Inscriptions récentes (30 derniers jours)
            $sql = "SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stats['recent_registrations'] = $this->count($sql);
            
            // Total général
            $stats['total'] = array_sum($stats['by_status'] ?? [0, 0]);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Erreur statistiques utilisateurs: " . $e->getMessage());
            return [];
        }
    }
}
?>