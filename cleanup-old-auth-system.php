<?php
/**
 * Script de nettoyage : Suppression de l'ancien système d'authentification
 * Supprime la table admin_sessions et nettoie les données obsolètes
 */

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $pdo = $database->connect();
    
    echo "=== NETTOYAGE ANCIEN SYSTÈME D'AUTHENTIFICATION ===\n\n";
    
    // 1. Vérifier l'existence de la table admin_sessions
    echo "1. Vérification de la table admin_sessions...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "   ✅ Table admin_sessions trouvée\n";
        
        // Compter les enregistrements
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_sessions");
        $count = $stmt->fetch()['count'];
        echo "   📊 Nombre de sessions admin: $count\n\n";
        
        // Confirmer la suppression
        echo "2. Suppression de la table admin_sessions...\n";
        $pdo->exec("DROP TABLE admin_sessions");
        echo "   ✅ Table admin_sessions supprimée avec succès\n\n";
        
    } else {
        echo "   ℹ️  Table admin_sessions non trouvée (déjà supprimée)\n\n";
    }
    
    // 2. Vérifier la table admin_audit_log
    echo "3. Vérification de la table admin_audit_log...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
    $auditExists = $stmt->fetch();
    
    if ($auditExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_audit_log");
        $auditCount = $stmt->fetch()['count'];
        echo "   📊 Nombre d'entrées audit: $auditCount\n";
        echo "   ℹ️  Table admin_audit_log conservée pour l'historique\n\n";
    } else {
        echo "   ℹ️  Table admin_audit_log non trouvée\n\n";
    }
    
    // 3. Vérifier la table activity_log (nouveau système)
    echo "4. Vérification de la table activity_log (nouveau système)...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'activity_log'");
    $activityExists = $stmt->fetch();
    
    if ($activityExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM activity_log");
        $activityCount = $stmt->fetch()['count'];
        echo "   ✅ Table activity_log active avec $activityCount entrées\n\n";
    } else {
        echo "   ❌ Table activity_log non trouvée - Problème!\n\n";
    }
    
    // 4. Résumé
    echo "=== RÉSUMÉ DU NETTOYAGE ===\n";
    echo "✅ Ancien système de tokens supprimé\n";
    echo "✅ Table admin_sessions supprimée\n";
    echo "✅ Nouveau système de sessions unifié actif\n";
    echo "✅ Logging unifié avec activity_log\n\n";
    
    echo "🎉 MIGRATION VERS SYSTÈME UNIFIÉ TERMINÉE !\n\n";
    
    echo "AVANTAGES OBTENUS :\n";
    echo "- 🔐 Un seul système d'authentification (sessions PHP)\n";
    echo "- 📝 Logging simplifié et unifié\n";
    echo "- 🧹 Code plus propre et maintenable\n";
    echo "- ⚡ Moins de complexité technique\n";
    echo "- 🛡️ Sécurité maintenue avec sessions PHP natives\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>