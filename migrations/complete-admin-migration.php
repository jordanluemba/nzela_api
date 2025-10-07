<?php
/**
 * NZELA - Migration complémentaire pour finaliser le système d'administration
 * Cette migration ajoute les éléments manquants détectés lors de la vérification
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
    // Utiliser la classe Database pour la connexion
    $database = new Database();
    $db = $database->connect();
    
    $migrations = [];
    
    echo "<h2>🔄 Migration complémentaire du système d'administration</h2>\n";
    echo "<pre>\n";
    
    // 1. Créer la table admin_sessions
    try {
        $result = $db->query("SHOW TABLES LIKE 'admin_sessions'");
        if ($result->rowCount() == 0) {
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
            $migrations[] = "✅ Table 'admin_sessions' créée";
            echo "✅ Table 'admin_sessions' créée\n";
        } else {
            $migrations[] = "ℹ️ Table 'admin_sessions' existe déjà";
            echo "ℹ️ Table 'admin_sessions' existe déjà\n";
        }
    } catch (Exception $e) {
        $migrations[] = "❌ Erreur table admin_sessions: " . $e->getMessage();
        echo "❌ Erreur table admin_sessions: " . $e->getMessage() . "\n";
    }
    
    // 2. Créer la table types_signalement
    try {
        $result = $db->query("SHOW TABLES LIKE 'types_signalement'");
        if ($result->rowCount() == 0) {
            $db->exec("CREATE TABLE types_signalement (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT DEFAULT NULL,
                updated_by INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_types_active (is_active),
                INDEX idx_types_created_by (created_by)
            )");
            $migrations[] = "✅ Table 'types_signalement' créée";
            echo "✅ Table 'types_signalement' créée\n";
            
            // Insérer quelques types par défaut
            $defaultTypes = [
                ['Nid de poule', 'Trou ou dégradation de la chaussée'],
                ['Éclairage défaillant', 'Panne ou absence d\'éclairage public'],
                ['Égout bouché', 'Problème de drainage ou d\'évacuation'],
                ['Ordures non ramassées', 'Accumulation d\'ordures ménagères'],
                ['Signalisation endommagée', 'Panneau cassé ou illisible'],
                ['Chaussée dégradée', 'Route en mauvais état général']
            ];
            
            $stmt = $db->prepare("INSERT INTO types_signalement (nom, description) VALUES (?, ?)");
            foreach ($defaultTypes as $type) {
                $stmt->execute($type);
            }
            $migrations[] = "✅ Types de signalement par défaut ajoutés";
            echo "✅ " . count($defaultTypes) . " types de signalement par défaut ajoutés\n";
            
        } else {
            $migrations[] = "ℹ️ Table 'types_signalement' existe déjà";
            echo "ℹ️ Table 'types_signalement' existe déjà\n";
        }
    } catch (Exception $e) {
        $migrations[] = "❌ Erreur table types_signalement: " . $e->getMessage();
        echo "❌ Erreur table types_signalement: " . $e->getMessage() . "\n";
    }
    
    // 3. Ajouter les colonnes manquantes dans signalements
    try {
        $db->exec("ALTER TABLE signalements ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER admin_notes");
        $migrations[] = "✅ Colonne 'resolved_at' ajoutée à signalements";
        echo "✅ Colonne 'resolved_at' ajoutée à signalements\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'resolved_at' existe déjà";
            echo "ℹ️ Colonne 'resolved_at' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur colonne 'resolved_at': " . $e->getMessage();
            echo "❌ Erreur colonne 'resolved_at': " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $db->exec("ALTER TABLE signalements ADD COLUMN resolved_by INT DEFAULT NULL AFTER resolved_at");
        $migrations[] = "✅ Colonne 'resolved_by' ajoutée à signalements";
        echo "✅ Colonne 'resolved_by' ajoutée à signalements\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            $migrations[] = "ℹ️ Colonne 'resolved_by' existe déjà";
            echo "ℹ️ Colonne 'resolved_by' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur colonne 'resolved_by': " . $e->getMessage();
            echo "❌ Erreur colonne 'resolved_by': " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Ajouter les clés étrangères manquantes
    try {
        $db->exec("ALTER TABLE signalements ADD CONSTRAINT fk_signalements_assigned_to 
                   FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL");
        $migrations[] = "✅ Clé étrangère 'assigned_to' ajoutée";
        echo "✅ Clé étrangère 'assigned_to' ajoutée\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false || strpos($e->getMessage(), "foreign key constraint already exists") !== false) {
            $migrations[] = "ℹ️ Clé étrangère 'assigned_to' existe déjà";
            echo "ℹ️ Clé étrangère 'assigned_to' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur clé étrangère assigned_to: " . $e->getMessage();
            echo "❌ Erreur clé étrangère assigned_to: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $db->exec("ALTER TABLE signalements ADD CONSTRAINT fk_signalements_resolved_by 
                   FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL");
        $migrations[] = "✅ Clé étrangère 'resolved_by' ajoutée";
        echo "✅ Clé étrangère 'resolved_by' ajoutée\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false || strpos($e->getMessage(), "foreign key constraint already exists") !== false) {
            $migrations[] = "ℹ️ Clé étrangère 'resolved_by' existe déjà";
            echo "ℹ️ Clé étrangère 'resolved_by' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur clé étrangère resolved_by: " . $e->getMessage();
            echo "❌ Erreur clé étrangère resolved_by: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Ajouter les index manquants
    try {
        $db->exec("CREATE INDEX idx_signalements_assigned ON signalements(assigned_to)");
        $migrations[] = "✅ Index 'idx_signalements_assigned' créé";
        echo "✅ Index 'idx_signalements_assigned' créé\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            $migrations[] = "ℹ️ Index 'idx_signalements_assigned' existe déjà";
            echo "ℹ️ Index 'idx_signalements_assigned' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur index assigned: " . $e->getMessage();
            echo "❌ Erreur index assigned: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        $db->exec("CREATE INDEX idx_signalements_resolved ON signalements(resolved_by, resolved_at)");
        $migrations[] = "✅ Index 'idx_signalements_resolved' créé";
        echo "✅ Index 'idx_signalements_resolved' créé\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            $migrations[] = "ℹ️ Index 'idx_signalements_resolved' existe déjà";
            echo "ℹ️ Index 'idx_signalements_resolved' existe déjà\n";
        } else {
            $migrations[] = "❌ Erreur index resolved: " . $e->getMessage();
            echo "❌ Erreur index resolved: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Migration complémentaire terminée avec succès !\n";
    echo "</pre>\n";
    
    // Réponse JSON pour les appels d'API
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Migration complémentaire du système d\'administration terminée',
            'migrations' => $migrations,
            'summary' => [
                'tables_created' => ['admin_sessions', 'types_signalement'],
                'columns_added' => ['resolved_at', 'resolved_by'],
                'foreign_keys_added' => ['assigned_to', 'resolved_by'],
                'indexes_added' => ['idx_signalements_assigned', 'idx_signalements_resolved']
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Erreur fatale lors de la migration: " . $e->getMessage() . "</div>\n";
    
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Erreur fatale lors de la migration complémentaire',
            'details' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
    }
}
?>