<?php
/**
 * Migration vers système d'authentification unifié
 * Création de la table activity_log pour remplacer admin_audit_log
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->connect();
    
    echo "=== MIGRATION VERS SYSTÈME UNIFIÉ ===\n\n";
    
    // 1. Créer la table activity_log si elle n'existe pas
    echo "1. Création de la table activity_log...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        role ENUM('citoyen', 'admin', 'superadmin') NOT NULL DEFAULT 'citoyen',
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(50) NULL,
        record_id INT NULL,
        details JSON NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_activity_user (user_id),
        INDEX idx_activity_role (role),
        INDEX idx_activity_action (action),
        INDEX idx_activity_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Table activity_log créée avec succès\n\n";
    
    // 2. Vérifier la structure
    echo "2. Vérification de la structure...\n";
    $stmt = $pdo->query("DESCRIBE activity_log");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$row['Field']}: {$row['Type']}\n";
    }
    
    echo "\n✅ Migration terminée avec succès !\n";
    echo "\nPROCHAINES ÉTAPES :\n";
    echo "1. Tester les nouvelles fonctions d'authentification\n";
    echo "2. Migrer auth/login.php pour supporter les rôles admin\n";
    echo "3. Migrer les endpoints admin/* vers les sessions\n";
    echo "4. Supprimer l'ancien système (tokens + admin_sessions)\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>