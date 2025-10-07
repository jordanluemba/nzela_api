<?php
/**
 * NZELA API - Créer un signalement
 * POST /api/signalements/create.php
 * Correspond au formulaire confirmation-signalement.html
 */

// Désactiver l'affichage des erreurs pour éviter le HTML dans les réponses JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Capturer les erreurs PHP fatales et les convertir en JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erreur PHP: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
        exit;
    }
});

require_once '../config/cors.php';
require_once '../helpers/functions.php';
require_once '../models/Signalement.php';
require_once '../models/TypeSignalement.php';

/**
 * Gérer l'upload de photo pour un signalement
 */
function handlePhotoUpload($file) {
    // Vérifications de sécurité
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Type de fichier non autorisé. Utilisez JPG, PNG ou WebP');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('Fichier trop volumineux. Maximum 5MB');
    }
    
    // Vérifier que c'est bien une image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Le fichier n\'est pas une image valide');
    }
    
    // Générer un nom de fichier unique
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'photo_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extension);
    
    // Chemin de destination
    $uploadDir = __DIR__ . '/../uploads/signalements/';
    $filePath = $uploadDir . $fileName;
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Erreur lors de la sauvegarde du fichier');
    }
    
    return $fileName;
}

// Vérifier la méthode HTTP
checkMethod(['POST']);

try {
    $signalementData = [];
    $photoFileName = null;
    
    // Déterminer si c'est un envoi multipart (avec photo) ou JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // Envoi avec photo - récupérer les données du formulaire
        $input = $_POST;
        
        // Gérer l'upload de photo si présente
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoFileName = handlePhotoUpload($_FILES['photo']);
            // DEBUG: Vérifier que la photo est bien uploadée
            error_log("DEBUG PHOTO UPLOAD: photoFileName = " . $photoFileName);
        } else {
            // DEBUG: Vérifier pourquoi pas de photo
            error_log("DEBUG PHOTO: Pas de photo - FILES=" . print_r($_FILES, true));
        }
    } else {
        // Envoi JSON classique - récupérer les données JSON
        $input = getJsonInput();
    }
    
    // Validation des champs requis
    validateRequired($input, ['type_signalement_id', 'province', 'ville', 'description']);
    
    // Vérifier que le type de signalement existe
    $typeModel = new TypeSignalement();
    if (!$typeModel->exists($input['type_signalement_id'])) {
        jsonResponse(['error' => 'Type de signalement invalide'], 400);
    }
    
    // Générer un code unique
    $signalementModel = new Signalement();
    do {
        $code = generateSignalementCode();
    } while ($signalementModel->codeExists($code));
    
    // Préparer les données du signalement
    $signalementData = [
        'code' => $code,
        'user_id' => isLoggedIn() ? $_SESSION['user_id'] : null,
        'type_signalement_id' => (int)$input['type_signalement_id'],
        'province' => sanitizeString($input['province']),
        'ville' => sanitizeString($input['ville']),
        'commune' => isset($input['commune']) ? sanitizeString($input['commune']) : null,
        'quartier' => isset($input['quartier']) ? sanitizeString($input['quartier']) : null,
        'nom_rue' => isset($input['nom_rue']) ? sanitizeString($input['nom_rue']) : null,
        'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : null,
        'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : null,
        'description' => sanitizeString($input['description']),
        'urgence' => isset($input['urgence']) ? $input['urgence'] : 'Moyen',
        'circulation' => isset($input['circulation']) ? $input['circulation'] : null,
        'nom_citoyen' => isset($input['nom_citoyen']) ? sanitizeString($input['nom_citoyen']) : null,
        'telephone' => isset($input['telephone']) ? sanitizeString($input['telephone']) : null,
        'photo_principale' => $photoFileName, // Photo uploadée ou null
        'ip_address' => getClientIP()
    ];
    
    // DEBUG: Vérifier les données avant insertion
    error_log("DEBUG SIGNALEMENT DATA: " . print_r($signalementData, true));
    error_log("DEBUG PHOTO_PRINCIPALE: " . ($photoFileName ?? 'NULL'));
    
    // Validation des énums
    $urgenceValid = ['Urgent', 'Moyen', 'Faible'];
    if (!in_array($signalementData['urgence'], $urgenceValid)) {
        $signalementData['urgence'] = 'Moyen';
    }
    
    $circulationValid = ['Oui, totalement', 'Partiellement', 'Non'];
    if ($signalementData['circulation'] && !in_array($signalementData['circulation'], $circulationValid)) {
        $signalementData['circulation'] = null;
    }
    
    // Créer le signalement
    $signalementId = $signalementModel->create($signalementData);
    
    // DEBUG: Vérifier que l'insertion s'est bien passée
    error_log("DEBUG INSERTION: signalementId = " . $signalementId);
    
    // Préparer la réponse avec les informations de photo si elle existe
    $responseData = [
        'id' => $signalementId,
        'code' => $code,
        'statut' => 'En attente',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Ajouter les informations de photo si une photo a été uploadée
    if ($photoFileName) {
        $responseData['photo'] = [
            'filename' => $photoFileName,
            'url' => '/api/image.php?type=signalement&name=' . urlencode($photoFileName),
            'direct_url' => '/api/uploads/signalements/' . $photoFileName
        ];
    }
    
    // Créer les notifications pour la création du signalement
    try {
        $signalementData = [
            'titre' => $data['titre'],
            'type_id' => $data['type_id'],
            'localisation' => $data['localisation'] ?? null
        ];
        
        // Notification de confirmation pour le citoyen
        notifySignalementConfirmation($signalementId, $data['user_id'], $signalementData);
        
        // Notification pour tous les admins
        notifyAdminsNewSignalement($signalementId, $signalementData);
        
    } catch (Exception $notifError) {
        // Les erreurs de notification ne doivent pas faire échouer la création
        error_log("Erreur notifications signalement: " . $notifError->getMessage());
    }

    jsonResponse([
        'success' => true,
        'message' => $photoFileName ? 'Signalement créé avec succès avec photo' : 'Signalement créé avec succès',
        'data' => $responseData
    ], 201);
    
} catch (Exception $e) {
    logError("Erreur create signalement: " . $e->getMessage());
    
    // En mode développement, donner plus de détails
    $isDev = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
             strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
    
    if ($isDev) {
        jsonResponse([
            'error' => 'Erreur serveur',
            'debug' => [
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
            ]
        ], 500);
    } else {
        jsonResponse(['error' => 'Erreur serveur'], 500);
    }
}
?>