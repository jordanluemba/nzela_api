<?php
/**
 * Health Check Endpoint pour Render
 * Vérifie le statut de l'API et de la base de données
 */

// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
error_reporting(0);

// Headers de réponse
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Fonction pour gérer les erreurs fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur interne du serveur',
            'timestamp' => date('c')
        ]);
    }
});

try {
    $healthStatus = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'environment' => $_ENV['ENVIRONMENT'] ?? 'development',
        'checks' => []
    ];

    // Test 1: Vérification des fichiers critiques
    $criticalFiles = [
        'config/database.php',
        'models/Database.php',
        'auth/login.php',
        'signalements/create.php'
    ];

    $filesOk = true;
    foreach ($criticalFiles as $file) {
        if (!file_exists($file)) {
            $filesOk = false;
            break;
        }
    }

    $healthStatus['checks']['files'] = [
        'status' => $filesOk ? 'ok' : 'error',
        'message' => $filesOk ? 'Tous les fichiers critiques présents' : 'Fichiers manquants'
    ];

    // Test 2: Vérification de la base de données
    try {
        require_once 'config/database.php';
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Test simple de connexion
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        $healthStatus['checks']['database'] = [
            'status' => 'ok',
            'message' => 'Connexion base de données active'
        ];
    } catch (Exception $e) {
        $healthStatus['checks']['database'] = [
            'status' => 'error',
            'message' => 'Erreur de connexion base de données'
        ];
        $healthStatus['status'] = 'degraded';
    }

    // Test 3: Vérification des permissions d'écriture
    $uploadsWritable = is_writable('uploads/');
    $healthStatus['checks']['uploads'] = [
        'status' => $uploadsWritable ? 'ok' : 'warning',
        'message' => $uploadsWritable ? 'Dossier uploads accessible' : 'Problème permissions uploads'
    ];

    // Test 4: Vérification de l'espace disque (approximatif)
    $freeSpace = disk_free_space('.');
    $healthStatus['checks']['disk'] = [
        'status' => $freeSpace > 100 * 1024 * 1024 ? 'ok' : 'warning', // 100MB
        'message' => 'Espace disque: ' . number_format($freeSpace / 1024 / 1024, 2) . ' MB'
    ];

    // Test 5: Extensions PHP requises
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
    $extensionsOk = true;
    $missingExtensions = [];

    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $extensionsOk = false;
            $missingExtensions[] = $ext;
        }
    }

    $healthStatus['checks']['php_extensions'] = [
        'status' => $extensionsOk ? 'ok' : 'error',
        'message' => $extensionsOk ? 'Extensions PHP OK' : 'Extensions manquantes: ' . implode(', ', $missingExtensions)
    ];

    // Déterminer le statut global
    $hasErrors = false;
    foreach ($healthStatus['checks'] as $check) {
        if ($check['status'] === 'error') {
            $hasErrors = true;
            break;
        }
    }

    if ($hasErrors) {
        $healthStatus['status'] = 'unhealthy';
        http_response_code(503);
    } else {
        // Vérifier s'il y a des warnings
        $hasWarnings = false;
        foreach ($healthStatus['checks'] as $check) {
            if ($check['status'] === 'warning') {
                $hasWarnings = true;
                break;
            }
        }
        
        if ($hasWarnings && $healthStatus['status'] === 'healthy') {
            $healthStatus['status'] = 'degraded';
        }
    }

    // Ajouter des métriques supplémentaires si disponibles
    $healthStatus['metrics'] = [
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true),
        'uptime' => $_SERVER['REQUEST_TIME'] - ($_SERVER['SERVER_ADMIN'] ?? $_SERVER['REQUEST_TIME'])
    ];

    echo json_encode($healthStatus, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors du health check',
        'timestamp' => date('c')
    ]);
}
?>