<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
    
    echo "=== ANALYSE DETAILLEE DE LA BASE DE DONNEES ===\n\n";
    
    // 1. Lister TOUTES les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "TOUTES LES TABLES dans la base 'nzela_db':\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n";
    
    // 2. Chercher spécifiquement admin_sessions
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
    $adminSessionsExists = $stmt->fetchAll();
    echo "Recherche spécifique 'admin_sessions':\n";
    if (empty($adminSessionsExists)) {
        echo "❌ Table 'admin_sessions' NON TROUVEE\n";
    } else {
        echo "✅ Table 'admin_sessions' TROUVEE\n";
        // Décrire la structure
        $stmt = $pdo->query("DESCRIBE admin_sessions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Structure de admin_sessions:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']}) {$column['Null']} {$column['Key']} {$column['Default']}\n";
        }
    }
    
    echo "\n";
    
    // 3. Vérifier les tables qui commencent par 'admin'
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin%'");
    $adminTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables commençant par 'admin':\n";
    if (empty($adminTables)) {
        echo "❌ Aucune table 'admin*' trouvée\n";
    } else {
        foreach ($adminTables as $table) {
            echo "✅ $table\n";
        }
    }
    
    echo "\n";
    
    // 4. Vérifier la base de données actuelle
    $stmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $stmt->fetchColumn();
    echo "Base de données actuelle: $currentDb\n\n";
    
    // 5. Essayer de créer la table manuellement pour voir l'erreur
    echo "=== TEST DE CREATION admin_sessions ===\n";
    try {
        $pdo->exec("CREATE TABLE admin_sessions_test (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            session_token VARCHAR(128) UNIQUE NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            is_active BOOLEAN DEFAULT TRUE
        )");
        echo "✅ Création de table test réussie\n";
        
        // Supprimer la table test
        $pdo->exec("DROP TABLE admin_sessions_test");
        echo "✅ Table test supprimée\n";
        
    } catch (Exception $e) {
        echo "❌ Erreur création table test: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>