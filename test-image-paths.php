<?php
/**
 * Script simple pour tester et mettre à jour les chemins d'images
 * Sans dépendance à PDO
 */

echo "🔍 Test des chemins d'images pour types\n";
echo "========================================\n\n";

// Simulation des données comme elles apparaissent dans la base
$typesActuels = [
    ["id" => 1, "nom" => "Nid de poule", "image_path" => "../assets/img/nid-de-poule.png"],
    ["id" => 2, "nom" => "Caniveau bouché", "image_path" => "../assets/img/caniveau.png"],
    ["id" => 3, "nom" => "Route impraticable", "image_path" => "../assets/img/route.png"]
];

echo "📋 Correction des chemins :\n";
foreach ($typesActuels as $type) {
    echo "- {$type['nom']}\n";
    echo "  Ancien: {$type['image_path']}\n";
    
    if (strpos($type['image_path'], '../assets/img/') === 0) {
        $filename = basename($type['image_path']);
        $nouveauChemin = 'uploads/types/' . $filename;
        echo "  Nouveau: {$nouveauChemin}\n";
        
        // SQL pour mise à jour (à exécuter manuellement)
        echo "  SQL: UPDATE types_signalements SET image_path = '{$nouveauChemin}' WHERE id = {$type['id']};\n";
    }
    echo "\n";
}

echo "🎯 Résumé :\n";
echo "===========\n";
echo "1. Les images des types doivent être dans: uploads/types/\n";
echo "2. Les chemins en base doivent être: uploads/types/filename.ext\n";
echo "3. L'URL générée sera: http://localhost/api/image.php?path=uploads%2Ftypes%2Ffilename.ext\n\n";

echo "📁 Création des dossiers nécessaires :\n";
$uploadsTypes = __DIR__ . '/uploads/types';
if (!is_dir($uploadsTypes)) {
    mkdir($uploadsTypes, 0755, true);
    echo "✅ Dossier uploads/types créé\n";
} else {
    echo "✅ Dossier uploads/types existe\n";
}

echo "\n🔧 Pour corriger en base, exécutez ces requêtes SQL :\n";
echo "UPDATE types_signalements SET image_path = REPLACE(image_path, '../assets/img/', 'uploads/types/');\n";
?>