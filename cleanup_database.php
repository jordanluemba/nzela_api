<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->connect();

echo "🧹 Nettoyage de la base de données\n\n";

try {
    // Supprimer la table dupliquée
    $pdo->exec('DROP TABLE IF EXISTS types_signalement');
    echo "✅ Table dupliquée 'types_signalement' (sans 's') supprimée\n";
} catch (Exception $e) {
    echo "❌ Erreur suppression: " . $e->getMessage() . "\n";
}

// Vérification finale
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\n📊 Tables finales dans la base:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // Compter les types dans la table finale
    $stmt = $pdo->query("SELECT COUNT(*) FROM types_signalements");
    $count = $stmt->fetchColumn();
    echo "\n✅ $count types de signalements conservés dans 'types_signalements'\n";
    
} catch (Exception $e) {
    echo "❌ Erreur vérification: " . $e->getMessage() . "\n";
}

echo "\n🎉 Base de données nettoyée et prête pour l'administration !\n";
?>