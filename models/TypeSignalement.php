<?php
/**
 * NZELA - Modèle TypeSignalement
 * Gestion des types de signalements (table types_signalements)
 */

require_once __DIR__ . '/Database.php';

class TypeSignalement extends DatabaseModel {
    
    /**
     * Récupérer tous les types actifs
     */
    public function getAll() {
        $sql = "SELECT id, nom, description, image_path, ordre_affichage 
                FROM types_signalements 
                WHERE is_active = 1 
                ORDER BY ordre_affichage ASC, nom ASC";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Récupérer un type par ID
     */
    public function getById($id) {
        $sql = "SELECT id, nom, description, image_path, ordre_affichage 
                FROM types_signalements 
                WHERE id = :id AND is_active = 1";
        
        return $this->fetch($sql, ['id' => $id]);
    }
    
    /**
     * Récupérer un type par nom
     */
    public function getByNom($nom) {
        try {
            // Vérifier quelles colonnes existent dans la table
            $stmt = $this->pdo->query("SHOW COLUMNS FROM types_signalements");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Construire la requête selon les colonnes disponibles
            $selectFields = ['id', 'nom'];
            
            if (in_array('description', $columns)) {
                $selectFields[] = 'description';
            }
            if (in_array('image_path', $columns)) {
                $selectFields[] = 'image_path';
            }
            if (in_array('ordre_affichage', $columns)) {
                $selectFields[] = 'ordre_affichage';
            }
            
            $sql = "SELECT " . implode(', ', $selectFields) . " FROM types_signalements WHERE nom = :nom";
            
            // Ajouter la condition is_active si la colonne existe
            if (in_array('is_active', $columns)) {
                $sql .= " AND is_active = 1";
            }
            
            return $this->fetch($sql, ['nom' => $nom]);
            
        } catch (Exception $e) {
            // Si la requête adaptative échoue, essayer une requête de base
            try {
                $sql = "SELECT id, nom FROM types_signalements WHERE nom = :nom";
                return $this->fetch($sql, ['nom' => $nom]);
            } catch (Exception $e2) {
                throw new Exception("Erreur lors de la récupération du type par nom: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * Vérifier si un type existe
     */
    public function exists($id) {
        try {
            // Vérifier si la colonne is_active existe
            $stmt = $this->pdo->query("SHOW COLUMNS FROM types_signalements LIKE 'is_active'");
            $hasIsActive = $stmt->rowCount() > 0;
            
            if ($hasIsActive) {
                $sql = "SELECT COUNT(*) FROM types_signalements WHERE id = :id AND is_active = 1";
            } else {
                $sql = "SELECT COUNT(*) FROM types_signalements WHERE id = :id";
            }
            
            return $this->count($sql, ['id' => $id]) > 0;
            
        } catch (Exception $e) {
            // En cas d'erreur, utiliser la requête de base
            $sql = "SELECT COUNT(*) FROM types_signalements WHERE id = :id";
            return $this->count($sql, ['id' => $id]) > 0;
        }
    }
    
    /**
     * Compter les signalements par type
     */
    public function getSignalementsCount($typeId) {
        $sql = "SELECT COUNT(*) FROM signalements WHERE type_signalement_id = :type_id";
        return $this->count($sql, ['type_id' => $typeId]);
    }
    
    /**
     * Statistiques par type
     */
    public function getStats() {
        $sql = "SELECT 
                    ts.id,
                    ts.nom,
                    ts.image_path,
                    COUNT(s.id) as total_signalements,
                    COUNT(CASE WHEN s.statut = 'En attente' THEN 1 END) as en_attente,
                    COUNT(CASE WHEN s.statut = 'En cours' THEN 1 END) as en_cours,
                    COUNT(CASE WHEN s.statut = 'Traité' THEN 1 END) as traites
                FROM types_signalements ts
                LEFT JOIN signalements s ON ts.id = s.type_signalement_id
                WHERE ts.is_active = 1
                GROUP BY ts.id
                ORDER BY total_signalements DESC";
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Créer un nouveau type de signalement
     */
    public function create($data) {
        $sql = "INSERT INTO types_signalements (nom, description, image_path, ordre_affichage, is_active) 
                VALUES (:nom, :description, :image_path, :ordre_affichage, :is_active)";
        
        return $this->insert($sql, [
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'ordre_affichage' => $data['ordre_affichage'] ?? 0,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Mettre à jour un type de signalement
     */
    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];
        
        if (isset($data['nom'])) {
            $fields[] = "nom = :nom";
            $params['nom'] = $data['nom'];
        }
        
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params['description'] = $data['description'];
        }
        
        if (isset($data['image_path'])) {
            $fields[] = "image_path = :image_path";
            $params['image_path'] = $data['image_path'];
        }
        
        if (isset($data['ordre_affichage'])) {
            $fields[] = "ordre_affichage = :ordre_affichage";
            $params['ordre_affichage'] = $data['ordre_affichage'];
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = $data['is_active'];
        }
        
        if (empty($fields)) {
            throw new Exception("Aucun champ à mettre à jour");
        }
        
        $sql = "UPDATE types_signalements SET " . implode(', ', $fields) . " WHERE id = :id";
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Désactiver un type (soft delete)
     */
    public function deactivate($id) {
        $sql = "UPDATE types_signalements SET is_active = 0 WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }
    
    /**
     * Compter les signalements associés à un type
     */
    public function countSignalements($typeId) {
        $sql = "SELECT COUNT(*) FROM signalements WHERE type_signalement_id = :type_id AND statut != 'Supprimé'";
        
        try {
            $stmt = $this->query($sql, ['type_id' => $typeId]);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Supprimer définitivement un type
     */
    public function delete($id) {
        $sql = "DELETE FROM types_signalements WHERE id = :id";
        
        try {
            $stmt = $this->query($sql, ['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Erreur suppression type: " . $e->getMessage());
        }
    }
    
    /**
     * Réorganiser l'ordre d'affichage
     */
    public function updateOrder($updates) {
        try {
            $this->pdo->beginTransaction();
            
            $sql = "UPDATE types_signalements SET ordre_affichage = :ordre WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($updates as $update) {
                $stmt->execute([
                    'id' => $update['id'],
                    'ordre' => $update['ordre_affichage']
                ]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
?>