<?php
/**
 * NZELA - Configuration Base de Données
 * Connexion MySQL pour WampServer
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'nzela_db';
    private $username = 'root';
    private $password = '';
    private $pdo = null;

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch(PDOException $e) {
                throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        }
        
        return $this->pdo;
    }
}
?>