<?php
/**
 * Configuration de base de données pour Render
 * Utilise les variables d'environnement automatiquement configurées
 */

class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port;
    
    public function __construct() {
        // Configuration pour Render avec variables d'environnement
        $this->host = $_ENV['DB_HOST'] ?? getenv('DATABASE_URL') ? $this->parseUrl() : 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? 'nzela_db';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        $this->port = $_ENV['DB_PORT'] ?? '3306';
    }
    
    /**
     * Parse DATABASE_URL pour extraire les composants
     */
    private function parseUrl() {
        $databaseUrl = getenv('DATABASE_URL');
        if ($databaseUrl) {
            $url = parse_url($databaseUrl);
            $this->host = $url['host'];
            $this->dbname = ltrim($url['path'], '/');
            $this->username = $url['user'];
            $this->password = $url['pass'];
            $this->port = $url['port'] ?? '3306';
            return $this->host;
        }
        return 'localhost';
    }
    
    public function getConnection() {
        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ]);
            
            return $pdo;
        } catch(PDOException $e) {
            error_log("Erreur de connexion: " . $e->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
    }
}