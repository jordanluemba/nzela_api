# 🚀 Nouveaux Endpoints CRUD - API NZELA

## 📋 Résumé des Ajouts

Ajout des opérations CRUD manquantes pour compléter l'API NZELA :

### ✅ **Endpoints Créés**

#### 🔄 **Signalements**
- `PUT /signalements/update.php` - Mise à jour complète d'un signalement
- `DELETE /signalements/delete.php` - Suppression (soft delete) d'un signalement

#### 🏷️ **Types de Signalements**  
- `DELETE /types/delete.php` - Suppression d'un type (avec vérifications)

#### 👤 **Utilisateurs**
- `PUT /auth/update.php` - Mise à jour du profil utilisateur
- `DELETE /auth/delete.php` - Suppression du compte utilisateur

---

## 📖 Documentation des Endpoints

### 🔄 Mettre à jour un signalement
```http
PUT /signalements/update.php
Content-Type: application/json
Cookie: PHPSESSID={session_id}

{
  "id": 1,
  "description": "Nouvelle description",
  "urgence": "Élevé",
  "commune": "Nouvelle commune"
}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Signalement mis à jour avec succès",
  "data": { /* signalement complet */ }
}
```

### 🗑️ Supprimer un signalement
```http
DELETE /signalements/delete.php
Content-Type: application/json
Cookie: PHPSESSID={session_id}

{
  "id": 1
}
```

**Réponse :**
```json
{
  "success": true,
  "message": "Signalement supprimé avec succès",
  "data": {
    "id": 1,
    "deleted_at": "2025-10-04 12:30:00"
  }
}
```

### 🗑️ Supprimer un type
```http
DELETE /types/delete.php
Content-Type: application/json
Cookie: PHPSESSID={session_id}

{
  "id": 1,
  "force": true  // Optionnel: forcer même si utilisé
}
```

### 🔄 Mettre à jour profil utilisateur
```http
PUT /auth/update.php
Content-Type: application/json
Cookie: PHPSESSID={session_id}

{
  "firstName": "Nouveau prénom",
  "email": "nouveau@email.com",
  "currentPassword": "ancien_mdp",  // Pour changer le MDP
  "newPassword": "nouveau_mdp"
}
```

### 💀 Supprimer compte utilisateur
```http
DELETE /auth/delete.php
Content-Type: application/json
Cookie: PHPSESSID={session_id}

{
  "password": "mot_de_passe_confirmation",
  "keepSignalements": true  // Optionnel: conserver signalements anonymisés
}
```

---

## 🧪 Comment Tester

### 1. **Interface Web**
Ouvrez dans votre navigateur :
```
http://localhost/api/tests/api-tester.html
```

**Nouveaux onglets disponibles :**
- **Signalements** : Tests UPDATE et DELETE pour signalements
- **Types** : Test DELETE pour types  
- **Authentification** : Tests UPDATE et DELETE pour profil utilisateur

### 2. **Tests Manuels PowerShell**

#### Test Mise à jour signalement :
```powershell
# D'abord se connecter pour obtenir session
$loginResponse = Invoke-WebRequest -Uri "http://localhost/api/auth/login.php" -Method POST -ContentType "application/json" -Body '{"email":"test@email.com","password":"motdepasse"}' -SessionVariable session

# Puis tester la mise à jour
$updateData = @{
    id = 1
    description = "Description mise à jour"
    urgence = "Élevé"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost/api/signalements/update.php" -Method PUT -ContentType "application/json" -Body $updateData -WebSession $session
```

#### Test Suppression type :
```powershell
$deleteData = @{
    id = 1
    force = $true
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://localhost/api/types/delete.php" -Method DELETE -ContentType "application/json" -Body $deleteData -WebSession $session
```

---

## 🔐 Sécurité et Permissions

### **Authentification Requise**
Tous les nouveaux endpoints nécessitent une authentification (session active).

### **Contrôles d'Accès**
- **Signalements** : Utilisateur peut modifier/supprimer ses propres signalements uniquement
- **Types** : Admin uniquement (TODO: implémenter système de rôles)
- **Profil** : Utilisateur peut modifier son propre profil uniquement

### **Validations**
- Confirmation par mot de passe pour suppression de compte
- Vérification d'existence avant modification/suppression
- Protection contre suppression de types utilisés (force=true pour override)
- Soft delete pour signalements (statut = "Supprimé")

---

## 🎯 Tableau Récapitulatif CRUD

| Entité | CREATE | READ | UPDATE | DELETE |
|--------|--------|------|--------|--------|
| **Signalements** | ✅ create.php | ✅ list.php, detail.php, user.php | ✅ **update.php** *(nouveau)* | ✅ **delete.php** *(nouveau)* |
| **Types** | ✅ create.php | ✅ list.php, detail.php | ✅ update.php, update-order.php | ✅ **delete.php** *(nouveau)* |
| **Utilisateurs** | ✅ register.php | ✅ me.php | ✅ **update.php** *(nouveau)* | ✅ **delete.php** *(nouveau)* |

### **Status : 🎉 CRUD Complet !**

Votre API NZELA dispose maintenant de toutes les opérations CRUD standard pour chaque entité principale.

---

## 🔄 Prochaines Améliorations Recommandées

1. **Système de rôles** : Admin/User/Moderator
2. **Validation avancée** : Règles métier plus strictes  
3. **Audit trail** : Traçabilité des modifications
4. **Bulk operations** : Opérations en masse
5. **API REST** : Structure URLs et codes HTTP standards

La base CRUD est maintenant solide ! 🚀