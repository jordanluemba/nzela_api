<?php
/**
 * NZELA - Modèle Signalement
 * Gestion des signalements (table signalements)
 */

require_once __DIR__ . '/Database.php';

class Signalement extends DatabaseModel {
    
    /**
     * Créer un nouveau signalement
     */
    public function create($data) {
        $sql = "INSERT INTO signalements (
                    code, user_id, type_signalement_id, province, ville, commune, quartier, nom_rue,
                    latitude, longitude, description, urgence, circulation, nom_citoyen, telephone,
                    photo_principale, ip_address
                ) VALUES (
                    :code, :user_id, :type_signalement_id, :province, :ville, :commune, :quartier, :nom_rue,
                    :latitude, :longitude, :description, :urgence, :circulation, :nom_citoyen, :telephone,
                    :photo_principale, :ip_address
                )";
        
        return $this->insert($sql, [
            'code' => $data['code'],
            'user_id' => $data['user_id'] ?? null,
            'type_signalement_id' => $data['type_signalement_id'],
            'province' => $data['province'],
            'ville' => $data['ville'],
            'commune' => $data['commune'] ?? null,
            'quartier' => $data['quartier'] ?? null,
            'nom_rue' => $data['nom_rue'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['description'],
            'urgence' => $data['urgence'] ?? 'Moyen',
            'circulation' => $data['circulation'] ?? null,
            'nom_citoyen' => $data['nom_citoyen'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'photo_principale' => $data['photo_principale'] ?? null,
            'ip_address' => $data['ip_address'] ?? null
        ]);
    }
    
    /**
     * Récupérer tous les signalements avec pagination et filtres
     */
    public function getAll($filters = [], $limit = 20, $offset = 0) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['province'])) {
                $where[] = "s.province = :province";
                $params['province'] = $filters['province'];
            }
            
            if (!empty($filters['statut'])) {
                $where[] = "s.statut = :statut";
                $params['statut'] = $filters['statut'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "s.user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['urgence'])) {
                $where[] = "s.urgence = :urgence";
                $params['urgence'] = $filters['urgence'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Vérifier d'abord quelles colonnes existent dans la table users
            try {
                $userCols = $this->pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                $userNameConcat = in_array('firstName', $userCols) 
                    ? "CONCAT(u.firstName, ' ', u.lastName)" 
                    : "CONCAT(u.first_name, ' ', u.last_name)";
            } catch (Exception $e) {
                // Si la table users n'existe pas ou erreur, utiliser seulement nom_citoyen
                $userNameConcat = "NULL";
            }
            
            $sql = "SELECT 
                        s.*,
                        ts.nom as type_nom,
                        ts.image_path as type_image,
                        COALESCE({$userNameConcat}, s.nom_citoyen) as nom_complet
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    LEFT JOIN users u ON s.user_id = u.id
                    {$whereClause}
                    ORDER BY s.created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            return $this->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            // En cas d'erreur, essayer une requête plus simple
            error_log("Erreur getAll signalements: " . $e->getMessage());
            
            // Reconstruire les paramètres pour la requête de fallback
            $fallbackParams = ['limit' => $limit, 'offset' => $offset];
            $fallbackWhere = [];
            
            if (!empty($filters['province'])) {
                $fallbackWhere[] = "s.province = :province";
                $fallbackParams['province'] = $filters['province'];
            }
            if (!empty($filters['statut'])) {
                $fallbackWhere[] = "s.statut = :statut";
                $fallbackParams['statut'] = $filters['statut'];
            }
            if (!empty($filters['user_id'])) {
                $fallbackWhere[] = "s.user_id = :user_id";
                $fallbackParams['user_id'] = $filters['user_id'];
            }
            if (!empty($filters['urgence'])) {
                $fallbackWhere[] = "s.urgence = :urgence";
                $fallbackParams['urgence'] = $filters['urgence'];
            }
            
            $whereClause = !empty($fallbackWhere) ? 'WHERE ' . implode(' AND ', $fallbackWhere) : '';
            
            $sql = "SELECT s.*, ts.nom as type_nom
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    {$whereClause}
                    ORDER BY s.created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            return $this->fetchAll($sql, $fallbackParams);
        }
    }
    
    /**
     * Compter les signalements avec filtres
     */
    public function countAll($filters = []) {
        try {
            $where = [];
            $params = [];
            
            if (!empty($filters['province'])) {
                $where[] = "province = :province";
                $params['province'] = $filters['province'];
            }
            
            if (!empty($filters['statut'])) {
                $where[] = "statut = :statut";
                $params['statut'] = $filters['statut'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['urgence'])) {
                $where[] = "urgence = :urgence";
                $params['urgence'] = $filters['urgence'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT COUNT(*) FROM signalements {$whereClause}";
            
            return $this->count($sql, $params);
            
        } catch (Exception $e) {
            error_log("Erreur countAll signalements: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Récupérer un signalement par code
     */
    public function getByCode($code) {
        try {
            // Vérifier d'abord quelles colonnes existent dans la table users
            try {
                $userCols = $this->pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                $userNameConcat = in_array('firstName', $userCols) 
                    ? "CONCAT(u.firstName, ' ', u.lastName)" 
                    : "CONCAT(u.first_name, ' ', u.last_name)";
            } catch (Exception $e) {
                // Si la table users n'existe pas ou erreur, utiliser seulement nom_citoyen
                $userNameConcat = "NULL";
            }
            
            $sql = "SELECT 
                        s.*,
                        ts.nom as type_nom,
                        ts.image_path as type_image,
                        ts.description as type_description,
                        COALESCE({$userNameConcat}, s.nom_citoyen) as nom_complet,
                        u.email as user_email
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.code = :code";
            
            return $this->fetch($sql, ['code' => $code]);
            
        } catch (Exception $e) {
            error_log("Erreur getByCode signalement: " . $e->getMessage());
            
            // Requête de fallback plus simple
            $sql = "SELECT s.*, ts.nom as type_nom
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    WHERE s.code = :code";
            
            return $this->fetch($sql, ['code' => $code]);
        }
    }
    
    /**
     * Récupérer un signalement par ID
     */
    public function getById($id) {
        try {
            // Vérifier d'abord quelles colonnes existent dans la table users
            try {
                $userCols = $this->pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
                $userNameConcat = in_array('firstName', $userCols) 
                    ? "CONCAT(u.firstName, ' ', u.lastName)" 
                    : "CONCAT(u.first_name, ' ', u.last_name)";
            } catch (Exception $e) {
                // Si la table users n'existe pas ou erreur, utiliser seulement nom_citoyen
                $userNameConcat = "NULL";
            }
            
            $sql = "SELECT 
                        s.*,
                        ts.nom as type_nom,
                        ts.image_path as type_image,
                        ts.description as type_description,
                        COALESCE({$userNameConcat}, s.nom_citoyen) as nom_complet,
                        u.email as user_email
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.id = :id";
            
            return $this->fetch($sql, ['id' => $id]);
            
        } catch (Exception $e) {
            error_log("Erreur getById signalement: " . $e->getMessage());
            
            // Requête de fallback plus simple
            $sql = "SELECT s.*, ts.nom as type_nom
                    FROM signalements s
                    LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                    WHERE s.id = :id";
            
            return $this->fetch($sql, ['id' => $id]);
        }
    }
    
    /**
     * Récupérer les signalements d'un utilisateur
     */
    public function getByUserId($userId, $limit = 50, $offset = 0) {
        $sql = "SELECT 
                    s.*,
                    ts.nom as type_nom,
                    ts.image_path as type_image
                FROM signalements s
                LEFT JOIN types_signalements ts ON s.type_signalement_id = ts.id
                WHERE s.user_id = :user_id
                ORDER BY s.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        return $this->fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Mettre à jour le statut d'un signalement
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE signalements SET 
                statut = :statut,
                treated_at = CASE WHEN :statut = 'Traité' THEN CURRENT_TIMESTAMP ELSE treated_at END
                WHERE id = :id";
        
        return $this->query($sql, [
            'id' => $id,
            'statut' => $status
        ]);
    }
    
    /**
     * Vérifier si un code existe déjà
     */
    public function codeExists($code) {
        $sql = "SELECT COUNT(*) FROM signalements WHERE code = :code";
        return $this->count($sql, ['code' => $code]) > 0;
    }
    
    /**
     * Mettre à jour un signalement
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
        
        $sql = "UPDATE signalements SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
        
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Erreur mise à jour signalement: " . $e->getMessage());
        }
    }
    
    /**
     * Suppression douce d'un signalement
     */
    public function softDelete($id) {
        $sql = "UPDATE signalements SET 
                    statut = 'Supprimé',
                    deleted_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
        
        try {
            $stmt = $this->query($sql, ['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Erreur suppression signalement: " . $e->getMessage());
        }
    }
    
    /**
     * Statistiques globales
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN statut = 'En attente' THEN 1 END) as en_attente,
                    COUNT(CASE WHEN statut = 'En cours' THEN 1 END) as en_cours,
                    COUNT(CASE WHEN statut = 'Traité' THEN 1 END) as traites,
                    COUNT(CASE WHEN urgence = 'Urgent' THEN 1 END) as urgents,
                    COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as aujourd_hui,
                    COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as cette_semaine
                FROM signalements";
        
        return $this->fetch($sql);
    }
}
?>