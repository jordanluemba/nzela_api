<?php
/**
 * NZELA - Modèle Base de Données
 * Wrapper pour la classe Database
 */

require_once __DIR__ . '/../config/database.php';

class DatabaseModel {
    protected $pdo;
    
    public function __construct() {
        $database = new Database();
        $this->pdo = $database->connect();
    }
    
    /**
     * Tester la connexion à la base de données
     */
    public function testConnection() {
        try {
            if ($this->pdo === null) {
                return false;
            }
            $stmt = $this->pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Exécuter une requête préparée
     */
    protected function query($sql, $params = []) {
        try {
            // Vérifier que la connexion PDO est active
            if ($this->pdo === null) {
                throw new Exception("Connexion base de données non initialisée");
            }
            
            $stmt = $this->pdo->prepare($sql);
            if (!$stmt) {
                $errorInfo = $this->pdo->errorInfo();
                throw new Exception("Erreur préparation requête: " . $errorInfo[2]);
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Erreur exécution requête: " . $errorInfo[2]);
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            // Logging sécurisé
            $errorMsg = "Erreur SQL: " . $e->getMessage() . " - SQL: " . $sql . " - Params: " . json_encode($params);
            
            // Essayer de logger, mais ne pas échouer si ça ne marche pas
            try {
                if (function_exists('logError')) {
                    logError($errorMsg);
                } else {
                    error_log($errorMsg);
                }
            } catch (Exception $logException) {
                // Ignorer les erreurs de logging
                error_log("Erreur logging: " . $logException->getMessage());
            }
            
            throw new Exception("Erreur de base de données: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Erreur système: " . $e->getMessage());
        }
    }
    
    /**
     * Récupérer une seule ligne
     */
    protected function fetch($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Erreur fetch: " . $e->getMessage());
        }
    }
    
    /**
     * Récupérer toutes les lignes
     */
    protected function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Erreur fetchAll: " . $e->getMessage());
        }
    }
    
    /**
     * Insérer et retourner l'ID
     */
    protected function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Compter les résultats
     */
    protected function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Exécuter une requête sans retour de données
     */
    protected function execute($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception("Erreur execute: " . $e->getMessage());
        }
    }
}
?>