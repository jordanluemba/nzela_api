<?php
/**
 * Script pour corriger les chemins d'images des types
 * Remplace ../assets/img/ par uploads/types/
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->connect();
    
    echo "🔍 Analyse des chemins d'images actuels\n";
    echo "======================================\n\n";
    
    // Récupérer les types avec leurs chemins actuels
    $stmt = $pdo->query("SELECT id, nom, image_path FROM types_signalements ORDER BY id");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📋 Types actuels :\n";
    foreach ($types as $type) {
        echo "- ID {$type['id']}: {$type['nom']}\n";
        echo "  Chemin actuel: {$type['image_path']}\n";
        
        // Si le chemin pointe vers ../assets/img/, le corriger
        if (strpos($type['image_path'], '../assets/img/') === 0) {
            $filename = basename($type['image_path']);
            $newPath = 'uploads/types/' . $filename;
            
            echo "  ➜ Nouveau chemin: {$newPath}\n";
            
            // Mettre à jour en base
            $updateStmt = $pdo->prepare("UPDATE types_signalements SET image_path = ? WHERE id = ?");
            $updateStmt->execute([$newPath, $type['id']]);
            
            echo "  ✅ Mis à jour\n";
        } else {
            echo "  ✅ Déjà correct\n";
        }
        echo "\n";
    }
    
    echo "🎯 Vérification finale :\n";
    echo "========================\n";
    
    $stmt = $pdo->query("SELECT id, nom, image_path FROM types_signalements WHERE image_path LIKE '../assets/%'");
    $remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($remaining)) {
        echo "✅ Tous les chemins sont maintenant corrects !\n";
    } else {
        echo "⚠️ Chemins restants à corriger :\n";
        foreach ($remaining as $type) {
            echo "- {$type['nom']}: {$type['image_path']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>