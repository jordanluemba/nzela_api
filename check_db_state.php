<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
    
    echo "=== VERIFICATION DE L'ETAT DE LA BASE DE DONNEES ===\n\n";
    
    // Vérifier si les colonnes admin existent dans users
    $stmt = $pdo->query('DESCRIBE users');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'users':\n";
    foreach ($columns as $column) {
        echo "- $column\n";
    }
    
    echo "\n";
    
    // Vérifier si les tables admin existent
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin%'");
    $adminTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables admin existantes:\n";
    if (empty($adminTables)) {
        echo "- Aucune table admin trouvée\n";
    } else {
        foreach ($adminTables as $table) {
            echo "- $table\n";
        }
    }
    
    // Vérification spécifique des tables importantes
    $importantTables = ['admin_sessions', 'admin_audit_log', 'types_signalement'];
    foreach ($importantTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        echo "- $table: " . ($exists ? '✅ EXISTE' : '❌ MANQUANTE') . "\n";
    }
    
    echo "\n";
    
    // Vérifier les colonnes dans signalements
    $stmt = $pdo->query('DESCRIBE signalements');
    $signalementColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'signalements':\n";
    foreach ($signalementColumns as $column) {
        echo "- $column\n";
    }
    
    echo "\n";
    
    // Vérifier les colonnes dans types_signalement
    $stmt = $pdo->query('DESCRIBE types_signalement');
    $typeColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes dans la table 'types_signalement':\n";
    foreach ($typeColumns as $column) {
        echo "- $column\n";
    }
    
    echo "\n=== FIN DE LA VERIFICATION ===\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>