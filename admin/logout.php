<?php
/**
 * NZELA API - Déconnexion Unifiée
 * POST /api/admin/logout.php (maintenant compatible avec tous les utilisateurs)
 * 
 * Déconnexion sécurisée avec le nouveau système de sessions unifié
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../helpers/functions.php';

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
}

try {
    // Détruire la session avec le nouveau système (simple et sûr)
    destroyUserSession();
    
    jsonResponse([
        'success' => true,
        'message' => 'Déconnexion réussie'
    ]);
    
} catch (Exception $e) {
    // Si l'utilisateur n'est pas connecté, on considère que c'est un succès
    if (strpos($e->getMessage(), 'Authentification requise') !== false) {
        jsonResponse([
            'success' => true,
            'message' => 'Déjà déconnecté'
        ]);
    }
    
    error_log("Erreur logout: " . $e->getMessage());
    
    jsonResponse([
        'error' => 'Erreur lors de la déconnexion',
        'details' => $e->getMessage()
    ], 500);
}
?>