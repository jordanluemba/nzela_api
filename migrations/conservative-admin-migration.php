<?php
/**
 * NZELA - Migration conservatrice : ajouter seulement les colonnes manquantes
 * Préserve toutes les données existantes
 */

// Inclure les fichiers de configuration
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/functions.php';

// Vérifier si ce script est exécuté depuis localhost ou CLI
$isCLI = php_sapi_name() === 'cli';
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || 
               in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

if (!$isCLI && !$isLocalhost) {
    jsonResponse(['error' => 'Migration autorisée uniquement en développement'], 403);
}

try {
    $database = new Database();
    $db = $database->connect();
    
    $migrations = [];
    
    echo "<h2>🔄 Migration conservatrice - Ajout colonnes manquantes</h2>\n";
    echo "<pre>\n";
    
    // ==========================================
    // 1. COLONNES MANQUANTES DANS users
    // ==========================================
    echo "=== TABLE USERS ===\n";
    
    // Vérifier quelles colonnes existent déjà
    $stmt = $db->query("DESCRIBE users");
    $existingUserColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $userColumnsToAdd = [
        'role' => "ALTER TABLE users ADD COLUMN role ENUM('citoyen', 'admin', 'superadmin') DEFAULT 'citoyen' AFTER email",
        'permissions' => "ALTER TABLE users ADD COLUMN permissions JSON DEFAULT NULL AFTER role", 
        'created_by' => "ALTER TABLE users ADD COLUMN created_by INT DEFAULT NULL AFTER permissions",
        'last_activity' => "ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login"
    ];
    
    foreach ($userColumnsToAdd as $column => $sql) {
        if (!in_array($column, $existingUserColumns)) {
            try {
                $db->exec($sql);
                echo "✅ Colonne '$column' ajoutée à users\n";
                $migrations[] = "✅ users.$column ajoutée";
            } catch (Exception $e) {
                echo "❌ Erreur ajout '$column': " . $e->getMessage() . "\n";
                $migrations[] = "❌ users.$column: " . $e->getMessage();
            }
        } else {
            echo "ℹ️ Colonne '$column' existe déjà dans users\n";
            $migrations[] = "ℹ️ users.$column existe déjà";
        }
    }
    
    // ==========================================
    // 2. COLONNES MANQUANTES DANS signalements
    // ==========================================
    echo "\n=== TABLE SIGNALEMENTS ===\n";
    
    $stmt = $db->query("DESCRIBE signalements");
    $existingSignalementColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $signalementColumnsToAdd = [
        'assigned_to' => "ALTER TABLE signalements ADD COLUMN assigned_to INT DEFAULT NULL AFTER statut",
        'admin_notes' => "ALTER TABLE signalements ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER assigned_to", 
        'resolved_at' => "ALTER TABLE signalements ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER admin_notes",
        'resolved_by' => "ALTER TABLE signalements ADD COLUMN resolved_by INT DEFAULT NULL AFTER resolved_at"
    ];
    
    foreach ($signalementColumnsToAdd as $column => $sql) {
        if (!in_array($column, $existingSignalementColumns)) {
            try {
                $db->exec($sql);
                echo "✅ Colonne '$column' ajoutée à signalements\n";
                $migrations[] = "✅ signalements.$column ajoutée";
            } catch (Exception $e) {
                echo "❌ Erreur ajout '$column': " . $e->getMessage() . "\n";
                $migrations[] = "❌ signalements.$column: " . $e->getMessage();
            }
        } else {
            echo "ℹ️ Colonne '$column' existe déjà dans signalements\n";
            $migrations[] = "ℹ️ signalements.$column existe déjà";
        }
    }
    
    // ==========================================
    // 3. COLONNES MANQUANTES DANS types_signalements (table existante)
    // ==========================================
    echo "\n=== TABLE TYPES_SIGNALEMENTS ===\n";
    
    $stmt = $db->query("DESCRIBE types_signalements");
    $existingTypeColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $typeColumnsToAdd = [
        'created_by' => "ALTER TABLE types_signalements ADD COLUMN created_by INT DEFAULT NULL",
        'updated_by' => "ALTER TABLE types_signalements ADD COLUMN updated_by INT DEFAULT NULL AFTER created_by",
        'created_at' => "ALTER TABLE types_signalements ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER updated_by",
        'updated_at' => "ALTER TABLE types_signalements ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];
    
    foreach ($typeColumnsToAdd as $column => $sql) {
        if (!in_array($column, $existingTypeColumns)) {
            try {
                $db->exec($sql);
                echo "✅ Colonne '$column' ajoutée à types_signalements\n";
                $migrations[] = "✅ types_signalements.$column ajoutée";
            } catch (Exception $e) {
                echo "❌ Erreur ajout '$column': " . $e->getMessage() . "\n";
                $migrations[] = "❌ types_signalements.$column: " . $e->getMessage();
            }
        } else {
            echo "ℹ️ Colonne '$column' existe déjà dans types_signalements\n";
            $migrations[] = "ℹ️ types_signalements.$column existe déjà";
        }
    }
    
    // ==========================================
    // 4. CRÉER SEULEMENT LES TABLES QUI N'EXISTENT PAS
    // ==========================================
    echo "\n=== TABLES ADMINISTRATIVES ===\n";
    
    // Table admin_sessions
    $stmt = $db->query("SHOW TABLES LIKE 'admin_sessions'");
    if ($stmt->rowCount() == 0) {
        try {
            $db->exec("CREATE TABLE admin_sessions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                session_token VARCHAR(128) UNIQUE NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_admin_sessions_token (session_token),
                INDEX idx_admin_sessions_user (user_id, is_active),
                INDEX idx_admin_sessions_expires (expires_at)
            )");
            echo "✅ Table 'admin_sessions' créée\n";
            $migrations[] = "✅ Table admin_sessions créée";
        } catch (Exception $e) {
            echo "❌ Erreur création admin_sessions: " . $e->getMessage() . "\n";
            $migrations[] = "❌ admin_sessions: " . $e->getMessage();
        }
    } else {
        echo "ℹ️ Table 'admin_sessions' existe déjà\n";
        $migrations[] = "ℹ️ Table admin_sessions existe déjà";
    }
    
    // Table admin_audit_log
    $stmt = $db->query("SHOW TABLES LIKE 'admin_audit_log'");
    if ($stmt->rowCount() == 0) {
        try {
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
            echo "✅ Table 'admin_audit_log' créée\n";
            $migrations[] = "✅ Table admin_audit_log créée";
        } catch (Exception $e) {
            echo "❌ Erreur création admin_audit_log: " . $e->getMessage() . "\n";
            $migrations[] = "❌ admin_audit_log: " . $e->getMessage();
        }
    } else {
        echo "ℹ️ Table 'admin_audit_log' existe déjà\n";
        $migrations[] = "ℹ️ Table admin_audit_log existe déjà";
    }
    
    // ==========================================
    // 5. CRÉER LE SUPER ADMIN S'IL N'EXISTE PAS
    // ==========================================
    echo "\n=== SUPER ADMINISTRATEUR ===\n";
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@nzela.local'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn() > 0;
    
    if (!$adminExists) {
        try {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) 
                                 VALUES ('admin@nzela.local', ?, 'Super', 'Administrateur', 'superadmin', 1, NOW())");
            $stmt->execute([$adminPassword]);
            echo "✅ Super administrateur créé (admin@nzela.local / admin123)\n";
            $migrations[] = "✅ Super admin créé";
        } catch (Exception $e) {
            echo "❌ Erreur création admin: " . $e->getMessage() . "\n";
            $migrations[] = "❌ Super admin: " . $e->getMessage();
        }
    } else {
        // Mettre à jour le rôle s'il existe déjà
        try {
            $stmt = $db->prepare("UPDATE users SET role = 'superadmin' WHERE email = 'admin@nzela.local'");
            $stmt->execute();
            echo "✅ Rôle super administrateur mis à jour\n";
            $migrations[] = "✅ Super admin role mis à jour";
        } catch (Exception $e) {
            echo "❌ Erreur mise à jour admin: " . $e->getMessage() . "\n";
            $migrations[] = "❌ Super admin update: " . $e->getMessage();
        }
    }
    
    // ==========================================
    // 6. AJOUTER LES INDEX MANQUANTS
    // ==========================================
    echo "\n=== INDEX ET CONTRAINTES ===\n";
    
    $indexesToAdd = [
        "CREATE INDEX idx_users_role ON users(role)" => "idx_users_role sur users",
        "CREATE INDEX idx_signalements_assigned ON signalements(assigned_to)" => "idx_signalements_assigned sur signalements",
        "CREATE INDEX idx_signalements_resolved ON signalements(resolved_by, resolved_at)" => "idx_signalements_resolved sur signalements",
        "CREATE INDEX idx_types_active ON types_signalements(is_active)" => "idx_types_active sur types_signalements"
    ];
    
    foreach ($indexesToAdd as $sql => $description) {
        try {
            $db->exec($sql);
            echo "✅ Index '$description' créé\n";
            $migrations[] = "✅ Index $description créé";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), "Duplicate key name") !== false) {
                echo "ℹ️ Index '$description' existe déjà\n";
                $migrations[] = "ℹ️ Index $description existe déjà";
            } else {
                echo "❌ Erreur index '$description': " . $e->getMessage() . "\n";
                $migrations[] = "❌ Index $description: " . $e->getMessage();
            }
        }
    }
    
    echo "\n🎉 Migration conservatrice terminée avec succès !\n";
    echo "📊 Toutes vos données existantes ont été préservées.\n";
    echo "</pre>\n";
    
    // Réponse JSON pour les appels d'API
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Migration conservatrice terminée - Données préservées',
            'migrations' => $migrations,
            'preserved_tables' => ['users', 'signalements', 'types_signalements'],
            'summary' => 'Colonnes admin ajoutées sans perte de données'
        ]);
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur fatale: " . $e->getMessage() . "</div>\n";
    
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur fatale lors de la migration conservatrice',
            'details' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
    }
}
?>