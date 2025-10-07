<?php
/**
 * Script pour corriger les noms de photos des signalements
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
    
    echo "🔧 Correction des noms de photos des signalements\n";
    echo "==============================================\n\n";
    
    // Lister les fichiers existants
    $uploadsDir = 'uploads/signalements/';
    $existingFiles = scandir($uploadsDir);
    $photoFiles = array_filter($existingFiles, function($file) {
        return !in_array($file, ['.', '..']) && 
               preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
    });
    
    echo "📁 Fichiers photos disponibles :\n";
    foreach ($photoFiles as $file) {
        echo "- $file\n";
    }
    echo "\n";
    
    // Vérifier les signalements avec photos
    $stmt = $pdo->query("
        SELECT id, code, photo_principale 
        FROM signalements 
        WHERE photo_principale IS NOT NULL 
        AND photo_principale != ''
    ");
    $signalements = $stmt->fetchAll();
    
    echo "📋 Signalements avec photos en base :\n";
    foreach ($signalements as $signalement) {
        $photoPath = $uploadsDir . $signalement['photo_principale'];
        $exists = file_exists($photoPath) ? '✅' : '❌';
        echo "$exists {$signalement['code']}: {$signalement['photo_principale']}\n";
    }
    echo "\n";
    
    // Proposer une correspondance automatique
    echo "🔄 Correspondances suggérées :\n";
    $updates = [];
    
    if (count($photoFiles) >= count($signalements)) {
        foreach ($signalements as $index => $signalement) {
            if (isset($photoFiles[$index])) {
                $newPhoto = $photoFiles[$index];
                $updates[] = [
                    'id' => $signalement['id'],
                    'old' => $signalement['photo_principale'],
                    'new' => $newPhoto
                ];
                echo "📝 ID {$signalement['id']}: {$signalement['photo_principale']} → $newPhoto\n";
            }
        }
    }
    
    echo "\n💡 Pour appliquer ces corrections, décommentez la section UPDATE ci-dessous\n";
    
    /*
    // DÉCOMMENTER CETTE SECTION POUR APPLIQUER LES CORRECTIONS :
    
    foreach ($updates as $update) {
        $stmt = $pdo->prepare("UPDATE signalements SET photo_principale = ? WHERE id = ?");
        $stmt->execute([$update['new'], $update['id']]);
        echo "✅ Mis à jour signalement ID {$update['id']}\n";
    }
    
    echo "\n🎉 Corrections appliquées avec succès !\n";
    */
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>