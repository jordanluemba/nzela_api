<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
    
    echo "=== COMPARAISON DES TABLES DE TYPES ===\n\n";
    
    // Ancienne table
    echo "Structure de 'types_signalements' (ancienne):\n";
    try {
        $stmt = $pdo->query('DESCRIBE types_signalements');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
        
        echo "\nContenu de types_signalements:\n";
        $stmt = $pdo->query('SELECT * FROM types_signalements');
        $oldData = $stmt->fetchAll();
        foreach ($oldData as $row) {
            echo "- ID: {$row['id']}, Nom: {$row['nom']}\n";
        }
    } catch (Exception $e) {
        echo "Erreur avec ancienne table: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Nouvelle table
    echo "Structure de 'types_signalement' (nouvelle):\n";
    try {
        $stmt = $pdo->query('DESCRIBE types_signalement');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            echo "- {$column['Field']} ({$column['Type']})\n";
        }
        
        echo "\nContenu de types_signalement:\n";
        $stmt = $pdo->query('SELECT * FROM types_signalement');
        $newData = $stmt->fetchAll();
        foreach ($newData as $row) {
            echo "- ID: {$row['id']}, Nom: {$row['nom']}\n";
        }
    } catch (Exception $e) {
        echo "Erreur avec nouvelle table: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== RECOMMANDATION ===\n";
    if (count($oldData ?? []) > 0 && count($newData ?? []) > 0) {
        echo "❗ Deux tables avec des données. Migration nécessaire.\n";
    } elseif (count($oldData ?? []) > 0) {
        echo "📋 Ancienne table contient des données. Migrer vers la nouvelle.\n";
    } else {
        echo "✅ Nouvelle table prête, ancienne vide ou inexistante.\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>