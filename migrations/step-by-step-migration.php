<?php
/**
 * NZELA - Migration par étapes pour le système d'administration
 * Exécution étape par étape pour éviter les erreurs
 */

header('Content-Type: application/json; charset=utf-8');

// Inclure les fichiers de configuration
require_once __DIR__ . '/../config/database.php';

// Vérifier si ce script est exécuté depuis localhost
if (!in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])) {
    echo json_encode(['error' => 'Migration autorisée uniquement en développement']);
    exit;
}

try {
    // Utiliser la classe Database pour la connexion
    $database = new Database();
    $db = $database->connect();
    
    $step = $_GET['step'] ?? '1';
    $result = [];
    
    switch ($step) {
        case '1':
            // Étape 1: Ajouter colonne role
            try {
                $db->exec("ALTER TABLE users ADD COLUMN role ENUM('citoyen', 'admin', 'superadmin') DEFAULT 'citoyen' AFTER email");
                $result = ['success' => true, 'message' => 'Colonne role ajoutée', 'next_step' => '2'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "Duplicate column") !== false) {
                    $result = ['success' => true, 'message' => 'Colonne role existe déjà', 'next_step' => '2'];
                } else {
                    throw $e;
                }
            }
            break;
            
        case '2':
            // Étape 2: Ajouter colonne last_activity
            try {
                $db->exec("ALTER TABLE users ADD COLUMN last_activity DATETIME DEFAULT NULL AFTER last_login");
                $result = ['success' => true, 'message' => 'Colonne last_activity ajoutée', 'next_step' => '3'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "Duplicate column") !== false) {
                    $result = ['success' => true, 'message' => 'Colonne last_activity existe déjà', 'next_step' => '3'];
                } else {
                    throw $e;
                }
            }
            break;
            
        case '3':
            // Étape 3: Créer ou mettre à jour le super admin
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@nzela.local'");
            $stmt->execute();
            $adminExists = $stmt->fetchColumn() > 0;
            
            if (!$adminExists) {
                $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, is_active, created_at) 
                                     VALUES ('admin@nzela.local', ?, 'Super', 'Administrateur', 'superadmin', 1, NOW())");
                $stmt->execute([$adminPassword]);
                $result = ['success' => true, 'message' => 'Super administrateur créé (admin@nzela.local / admin123)', 'next_step' => '4'];
            } else {
                $stmt = $db->prepare("UPDATE users SET role = 'superadmin' WHERE email = 'admin@nzela.local'");
                $stmt->execute();
                $result = ['success' => true, 'message' => 'Rôle super administrateur mis à jour', 'next_step' => '4'];
            }
            break;
            
        case '4':
            // Étape 4: Ajouter colonnes signalements
            try {
                $db->exec("ALTER TABLE signalements ADD COLUMN assigned_to INT DEFAULT NULL AFTER statut");
                $result = ['success' => true, 'message' => 'Colonne assigned_to ajoutée aux signalements', 'next_step' => '5'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "Duplicate column") !== false) {
                    $result = ['success' => true, 'message' => 'Colonne assigned_to existe déjà', 'next_step' => '5'];
                } else {
                    throw $e;
                }
            }
            break;
            
        case '5':
            // Étape 5: Ajouter admin_notes
            try {
                $db->exec("ALTER TABLE signalements ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER assigned_to");
                $result = ['success' => true, 'message' => 'Colonne admin_notes ajoutée aux signalements', 'next_step' => 'done'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "Duplicate column") !== false) {
                    $result = ['success' => true, 'message' => 'Colonne admin_notes existe déjà', 'next_step' => 'done'];
                } else {
                    throw $e;
                }
            }
            break;
            
        case 'done':
            $result = [
                'success' => true, 
                'message' => 'Migration terminée avec succès!',
                'admin_credentials' => [
                    'email' => 'admin@nzela.local',
                    'password' => 'admin123',
                    'note' => 'Changez ce mot de passe en production'
                ]
            ];
            break;
            
        default:
            $result = ['error' => 'Étape invalide'];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Erreur lors de la migration',
        'details' => $e->getMessage(),
        'step' => $step ?? 'unknown'
    ], JSON_PRETTY_PRINT);
}
?>