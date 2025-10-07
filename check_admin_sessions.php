<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->connect();

echo "=== VERIFICATION TABLE ADMIN_SESSIONS ===\n\n";

$stmt = $pdo->query("SHOW TABLES LIKE 'admin_sessions'");
if ($stmt->rowCount() > 0) {
    echo "✅ Table admin_sessions EXISTE\n\n";
    
    // Structure
    echo "Structure:\n";
    $desc = $pdo->query('DESCRIBE admin_sessions');
    foreach ($desc as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    // Contenu
    echo "\nContenu:\n";
    $data = $pdo->query('SELECT COUNT(*) as total FROM admin_sessions');
    $count = $data->fetchColumn();
    echo "- $count sessions enregistrées\n";
    
} else {
    echo "❌ Table admin_sessions N'EXISTE PAS\n";
    echo "Il faut la créer pour la sécurité admin.\n";
}

echo "\n=== UTILITÉ DE LA TABLE ADMIN_SESSIONS ===\n";
echo "🔐 SÉCURITÉ RENFORCÉE pour les administrateurs:\n";
echo "- Sessions séparées des citoyens normaux\n";
echo "- Traçabilité: IP, User-Agent, durée\n";
echo "- Expiration automatique des sessions\n";
echo "- Possibilité de révoquer des sessions à distance\n";
echo "- Audit: qui s'est connecté quand et d'où\n";
echo "- Protection contre le vol de session\n\n";

echo "🚨 SANS cette table:\n";
echo "- Admins utilisent les sessions PHP normales\n";
echo "- Pas de traçabilité des connexions admin\n";
echo "- Pas de révocation de session possible\n";
echo "- Sécurité moindre pour les comptes privilégiés\n";
?>