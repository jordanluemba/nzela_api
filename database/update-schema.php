<?php
/**
 * Script de mise à jour de la base de données NZELA
 * Harmonise la structure avec le nouveau schéma
 */

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $updates = [];
    $errors = [];
    
    // 1. Vérifier et mettre à jour la table types_signalements
    try {
        // Vérifier si image_path existe
        $stmt = $pdo->query("SHOW COLUMNS FROM types_signalements LIKE 'image_path'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE types_signalements ADD COLUMN image_path VARCHAR(300) AFTER description");
            $updates[] = "Colonne image_path ajoutée à types_signalements";
        }
        
        // Vérifier si ordre_affichage existe
        $stmt = $pdo->query("SHOW COLUMNS FROM types_signalements LIKE 'ordre_affichage'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE types_signalements ADD COLUMN ordre_affichage INT DEFAULT 0 AFTER image_path");
            $updates[] = "Colonne ordre_affichage ajoutée à types_signalements";
        }
        
        // Vérifier si is_active existe
        $stmt = $pdo->query("SHOW COLUMNS FROM types_signalements LIKE 'is_active'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE types_signalements ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER ordre_affichage");
            $updates[] = "Colonne is_active ajoutée à types_signalements";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur types_signalements: " . $e->getMessage();
    }
    
    // 2. Vérifier et mettre à jour la table signalements
    try {
        // Vérifier si photo_principale existe (au lieu de photo_url)
        $stmt = $pdo->query("SHOW COLUMNS FROM signalements LIKE 'photo_principale'");
        if (!$stmt->fetch()) {
            // Vérifier si photo_url existe pour la renommer
            $stmt = $pdo->query("SHOW COLUMNS FROM signalements LIKE 'photo_url'");
            if ($stmt->fetch()) {
                $pdo->exec("ALTER TABLE signalements CHANGE photo_url photo_principale VARCHAR(500)");
                $updates[] = "Colonne photo_url renommée en photo_principale";
            } else {
                $pdo->exec("ALTER TABLE signalements ADD COLUMN photo_principale VARCHAR(500) AFTER telephone");
                $updates[] = "Colonne photo_principale ajoutée à signalements";
            }
        }
        
        // Vérifier les enum statut
        $stmt = $pdo->query("SHOW COLUMNS FROM signalements LIKE 'statut'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'Traité') === false) {
            $pdo->exec("ALTER TABLE signalements MODIFY statut ENUM('En attente','En cours','Traité','Rejeté') DEFAULT 'En attente'");
            $updates[] = "Enum statut mis à jour (Résolu → Traité)";
        }
        
        // Vérifier les enum circulation
        $stmt = $pdo->query("SHOW COLUMNS FROM signalements LIKE 'circulation'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'Oui, totalement') === false) {
            $pdo->exec("ALTER TABLE signalements MODIFY circulation ENUM('Oui, totalement','Partiellement','Non') DEFAULT NULL");
            $updates[] = "Enum circulation mis à jour";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur signalements: " . $e->getMessage();
    }
    
    // 3. Mettre à jour les données des types existants
    try {
        // Ajouter des ordres d'affichage par défaut
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM types_signalements WHERE ordre_affichage > 0");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $types = [
                ['nom' => 'Infrastructure Routière', 'ordre' => 1],
                ['nom' => 'Éclairage Public', 'ordre' => 2],
                ['nom' => 'Gestion des Déchets', 'ordre' => 3],
                ['nom' => 'Eau et Assainissement', 'ordre' => 4],
                ['nom' => 'Sécurité', 'ordre' => 5],
                ['nom' => 'Espaces Verts', 'ordre' => 6],
                ['nom' => 'Transport Public', 'ordre' => 7],
                ['nom' => 'Autres', 'ordre' => 8]
            ];
            
            $updateStmt = $pdo->prepare("UPDATE types_signalements SET ordre_affichage = :ordre WHERE nom = :nom");
            foreach ($types as $type) {
                $updateStmt->execute(['ordre' => $type['ordre'], 'nom' => $type['nom']]);
            }
            $updates[] = "Ordres d'affichage par défaut ajoutés";
        }
        
    } catch (Exception $e) {
        $errors[] = "Erreur mise à jour données: " . $e->getMessage();
    }
    
    // 4. Créer le dossier uploads/types s'il n'existe pas
    $uploadsDir = __DIR__ . '/../uploads/types';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
        $updates[] = "Dossier uploads/types créé";
    }
    
    // Réponse
    echo json_encode([
        'success' => true,
        'message' => 'Mise à jour de la base de données terminée',
        'updates' => $updates,
        'errors' => $errors,
        'total_updates' => count($updates),
        'total_errors' => count($errors)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur critique: ' . $e->getMessage()
    ]);
}
?>