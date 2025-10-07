# 🧪 Test de Création de Signalement avec Photo

## Guide d'utilisation de l'API Tester amélioré

### 🎯 Nouvelles fonctionnalités ajoutées

L'interface `api-tester.html` a été améliorée pour permettre le test complet de la création de signalements avec photos.

### 📋 Onglet Signalements - Fonctionnalités

#### ✨ Nouveau formulaire de création avec photo

Le formulaire de création de signalement inclut maintenant :

**Champs requis :**
- Type de signalement ID
- Province  
- Ville
- Description

**Champs optionnels :**
- Commune
- Quartier
- Nom de la rue
- Latitude/Longitude (géolocalisation)
- Urgence (Urgent/Moyen/Faible)
- Impact sur la circulation
- Nom du citoyen
- Téléphone
- **Photo (nouveau !)**

#### 🔄 Deux modes de test

1. **🚀 Créer avec photo** - Mode multipart/form-data
   - Utilise FormData pour envoyer fichiers + données
   - Supporte photos JPEG, PNG, WebP, GIF (max 5MB)
   - Validation côté client

2. **📝 Créer sans photo (JSON)** - Mode JSON classique
   - Utilise application/json
   - Compatible avec l'ancienne API

### 🧪 Comment tester

1. **Ouvrir l'interface :**
   ```
   http://localhost/api/tests/api-tester.html
   ```

2. **Aller à l'onglet "Signalements"**

3. **Remplir le formulaire :**
   - Les champs sont pré-remplis avec des valeurs de test
   - Optionnel : Sélectionner une photo

4. **Tester les deux modes :**
   - Avec photo : Cliquer sur "🚀 Créer avec photo"
   - Sans photo : Cliquer sur "📝 Créer sans photo (JSON)"

### 📊 Réponses attendues

#### ✅ Succès avec photo
```json
{
  "success": true,
  "message": "Signalement créé avec succès avec photo",
  "data": {
    "id": 15,
    "code": "SIG-20241004-001",
    "statut": "En attente",
    "created_at": "2024-10-04 14:30:25",
    "photo": {
      "filename": "signalement_67000f819a3b2_photo.jpg",
      "url": "/api/image.php?type=signalement&name=signalement_67000f819a3b2_photo.jpg",
      "direct_url": "/api/uploads/signalements/signalement_67000f819a3b2_photo.jpg"
    }
  }
}
```

L'interface affichera aussi un aperçu visuel avec les liens vers la photo uploadée.

#### ✅ Succès sans photo
```json
{
  "success": true,
  "message": "Signalement créé avec succès",
  "data": {
    "id": 16,
    "code": "SIG-20241004-002",
    "statut": "En attente",
    "created_at": "2024-10-04 14:35:10"
  }
}
```

### 🔍 Validation automatique

L'interface inclut une validation côté client :
- **Taille de fichier :** Maximum 5MB
- **Types autorisés :** JPEG, PNG, WebP, GIF
- **Aperçu du fichier :** Nom et taille affichés après sélection

### 💡 Points techniques

1. **FormData automatique** : L'interface utilise FormData pour les uploads de fichiers
2. **Content-Type automatique** : Le navigateur gère automatiquement le boundary multipart
3. **Validation double** : Côté client (interface) + côté serveur (API)
4. **Stockage automatique** : Photos sauvées dans `uploads/signalements/`
5. **Noms uniques** : Évite les conflits avec timestamps + hash

### 🔗 Intégration complète

- Les photos sont automatiquement incluses dans les listes de signalements
- URLs d'accès disponibles via l'API image et en direct
- Compatible avec le système d'affichage existant

---

**Interface mise à jour le :** 4 octobre 2025
**Compatibilité :** API NZELA v1.0 avec photos intégrées