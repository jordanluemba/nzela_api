<?php
/**
 * NZELA - Migration simplifiée pour le système d'administration
 * Version sans transaction pour éviter les erreurs DDL
 */

// Inclure les fichiers de configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

// Vérifier si ce script est exécuté depuis localhost
if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) && 
    !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])) {
    jsonResponse(['error' => 'Migration autorisée uniquement en développement'], 403);
}

try {
    // Utiliser la classe Database pour la connexion
    $database = new Database();
    $db = $database->connect();
    
    $migrations = [];
    
    // 1. Ajouter la colonne role
    try {
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('citoyen', 'admin', 'superadmin') DEFAULT 'citoyen' AFTER email");
        $migrations[] = "✅ Colonne 'role' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'role' existe déjà";
        } else {
            $migrations[] = "❌ Erreur colonne 'role': " . $e->getMessage();
        }
    }
    
    // 2. Ajouter la colonne permissions
    try {
        $db->exec("ALTER TABLE users ADD COLUMN permissions JSON DEFAULT NULL AFTER role");
        $migrations[] = "✅ Colonne 'permissions' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'permissions' existe déjà";
        } else {
            $migrations[] = "❌ Erreur colonne 'permissions': " . $e->getMessage();
        }
    }
    
    // 3. Ajouter la colonne last_activity
    try {
        $db->exec("ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login");
        $migrations[] = "✅ Colonne 'last_activity' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'last_activity' existe déjà";
        } else {
            $migrations[] = "❌ Erreur colonne 'last_activity': " . $e->getMessage();
        }
    }
    
    // 4. Vérifier si le super admin existe déjà
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@nzela.local'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn() > 0;
    
    if (!$adminExists) {
        // Créer le super administrateur
        try {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) 
                                 VALUES ('admin@nzela.local', ?, 'Super', 'Administrateur', 'superadmin', 1, NOW())");
            $stmt->execute([$adminPassword]);
            $migrations[] = "✅ Super administrateur créé (admin@nzela.local / admin123)";
        } catch (Exception $e) {
            $migrations[] = "❌ Erreur création admin: " . $e->getMessage();
        }
    } else {
        // Mettre à jour le rôle s'il existe déjà
        try {
            $stmt = $db->prepare("UPDATE users SET role = 'superadmin' WHERE email = 'admin@nzela.local'");
            $stmt->execute();
            $migrations[] = "✅ Rôle super administrateur mis à jour";
        } catch (Exception $e) {
            $migrations[] = "❌ Erreur mise à jour admin: " . $e->getMessage();
        }
    }
    
    // 5. Ajouter les colonnes aux signalements
    try {
        $db->exec("ALTER TABLE signalements ADD COLUMN assigned_to INT DEFAULT NULL AFTER statut");
        $migrations[] = "✅ Colonne 'assigned_to' ajoutée à signalements";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'assigned_to' existe déjà";
        } else {
            $migrations[] = "❌ Erreur colonne 'assigned_to': " . $e->getMessage();
        }
    }
    
    try {
        $db->exec("ALTER TABLE signalements ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER assigned_to");
        $migrations[] = "✅ Colonne 'admin_notes' ajoutée à signalements";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'admin_notes' existe déjà";
        } else {
            $migrations[] = "❌ Erreur colonne 'admin_notes': " . $e->getMessage();
        }
    }
    
    // 6. Créer la table d'audit si elle n'existe pas
    try {
        $result = $db->query("SHOW TABLES LIKE 'admin_audit_log'");
        if ($result->rowCount() == 0) {
            $db->exec("CREATE TABLE admin_audit_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                admin_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                target_id INT,
                old_values JSON,
                new_values JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_admin (admin_id),
                INDEX idx_audit_target (target_type, target_id),
                INDEX idx_audit_date (created_at)
            )");
            $migrations[] = "✅ Table 'admin_audit_log' créée";
        } else {
            $migrations[] = "ℹ️ Table 'admin_audit_log' existe déjà";
        }
    } catch (Exception $e) {
        $migrations[] = "❌ Erreur table audit: " . $e->getMessage();
    }
    
    // 7. Ajouter les index
    try {
        $db->exec("CREATE INDEX idx_users_role ON users(role)");
        $migrations[] = "✅ Index 'idx_users_role' créé";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            $migrations[] = "ℹ️ Index 'idx_users_role' existe déjà";
        } else {
            $migrations[] = "❌ Erreur index role: " . $e->getMessage();
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Migration du système d\'administration terminée',
        'migrations' => $migrations,
        'next_steps' => [
            'Créer les helpers d\'authentification admin',
            'Modifier le système de login',
            'Créer les endpoints d\'administration'
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Erreur fatale lors de la migration',
        'details' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ], 500);
}
?>