<?php
/**
 * NZELA - Migration pour le système d'administration
 * Phase 1 : Modification de la base de données
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
    
    // Commencer une transaction
    $db->beginTransaction();
    
    $migrations = [];
    
    // 1. Ajouter les colonnes pour les rôles
    try {
        $db->exec("ALTER TABLE users 
                   ADD COLUMN role ENUM('citoyen', 'admin', 'superadmin') DEFAULT 'citoyen' AFTER email");
        $migrations[] = "✅ Colonne 'role' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'role' existe déjà";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE users 
                   ADD COLUMN permissions JSON DEFAULT NULL AFTER role");
        $migrations[] = "✅ Colonne 'permissions' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'permissions' existe déjà";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE users 
                   ADD COLUMN created_by INT DEFAULT NULL AFTER permissions");
        $migrations[] = "✅ Colonne 'created_by' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'created_by' existe déjà";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE users 
                   ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login");
        $migrations[] = "✅ Colonne 'last_activity' ajoutée à users";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'last_activity' existe déjà";
        } else {
            throw $e;
        }
    }
    
    // 2. Vérifier si le super admin existe déjà
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@nzela.local'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn() > 0;
    
    if (!$adminExists) {
        // Créer le super administrateur
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) 
                             VALUES ('admin@nzela.local', ?, 'Super', 'Administrateur', 'superadmin', 1, NOW())");
        $stmt->execute([$adminPassword]);
        $migrations[] = "✅ Super administrateur créé (admin@nzela.local / admin123)";
    } else {
        $migrations[] = "ℹ️ Super administrateur existe déjà";
    }
    
    // 3. Ajouter les index
    try {
        $db->exec("CREATE INDEX idx_users_role ON users(role)");
        $migrations[] = "✅ Index 'idx_users_role' créé";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            $migrations[] = "ℹ️ Index 'idx_users_role' existe déjà";
        } else {
            throw $e;
        }
    }
    
    // 4. Ajouter colonnes aux signalements
    try {
        $db->exec("ALTER TABLE signalements 
                   ADD COLUMN assigned_to INT DEFAULT NULL AFTER statut");
        $migrations[] = "✅ Colonne 'assigned_to' ajoutée à signalements";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'assigned_to' existe déjà";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE signalements 
                   ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER assigned_to");
        $migrations[] = "✅ Colonne 'admin_notes' ajoutée à signalements";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'admin_notes' existe déjà";
        } else {
            throw $e;
        }
    }
    
    // 5. Créer la table d'audit si elle n'existe pas
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
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_audit_admin (admin_id),
            INDEX idx_audit_target (target_type, target_id),
            INDEX idx_audit_date (created_at)
        )");
        $migrations[] = "✅ Table 'admin_audit_log' créée";
    } else {
        $migrations[] = "ℹ️ Table 'admin_audit_log' existe déjà";
    }
    
    // Valider la transaction
    $db->commit();
    
    jsonResponse([
        'success' => true,
        'message' => 'Migration du système d\'administration terminée avec succès',
        'migrations' => $migrations,
        'next_steps' => [
            'Créer les helpers d\'authentification admin',
            'Modifier le système de login',
            'Créer les endpoints d\'administration'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback seulement si une transaction est active
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    jsonResponse([
        'error' => 'Erreur lors de la migration',
        'details' => $e->getMessage(),
        'migrations_completed' => $migrations ?? []
    ], 500);
}
?>