# Système de Permissions - NZELA

## 👑 Super-Administrateur (superadmin)
**Permissions complètes sur tout le système :**

### Gestion Utilisateurs
- ✅ Voir tous les utilisateurs (citoyens, admins, superadmins)
- ✅ Créer des utilisateurs de tous rôles
- ✅ Modifier tous les utilisateurs (rôle, email, informations)
- ✅ Supprimer tous les utilisateurs (sauf soi-même)
- ✅ Promouvoir/rétrograder les rôles
- ✅ Gérer les permissions avancées

### Système
- ✅ Accès à tous les endpoints administratifs
- ✅ Gestion des types de signalements
- ✅ Configuration système
- ✅ Statistiques complètes
- ✅ Audit trail complet

---

## 🛡️ Administrateur (admin)
**Permissions de gestion opérationnelle :**

### Gestion Utilisateurs
- ✅ Voir tous les utilisateurs
- ✅ Créer des citoyens uniquement
- ✅ Modifier les citoyens (nom, email, téléphone, province)
- ✅ Supprimer les citoyens uniquement
- ❌ Ne peut pas créer/modifier/supprimer d'autres admins
- ❌ Ne peut pas changer les rôles

### Gestion Signalements
- ✅ Voir tous les signalements
- ✅ Changer les statuts des signalements
- ✅ Ajouter des commentaires
- ✅ Gérer les types de signalements
- ✅ Statistiques des signalements

### Notifications
- ✅ Recevoir les notifications de nouveaux signalements
- ✅ Gérer ses propres notifications
- ✅ Envoyer des messages aux citoyens

---

## 👤 Citoyen (citoyen)
**Permissions utilisateur standard :**

### Signalements
- ✅ Créer des signalements
- ✅ Voir ses propres signalements
- ✅ Ajouter des photos
- ❌ Ne peut pas voir les signalements des autres
- ❌ Ne peut pas changer les statuts

### Notifications
- ✅ Recevoir les notifications sur ses signalements
- ✅ Marquer ses notifications comme lues
- ✅ Recevoir les messages des admins

### Profil
- ✅ Modifier ses informations personnelles
- ✅ Changer son mot de passe
- ❌ Ne peut pas changer son rôle

---

## 🔒 Règles de Sécurité

1. **Auto-protection :** Personne ne peut supprimer son propre compte
2. **Hiérarchie :** Un rôle ne peut pas modifier un rôle supérieur ou égal
3. **Promotion :** Seuls les superadmins peuvent promouvoir au rôle admin
4. **Audit :** Toutes les actions administratives sont loggées
5. **Sessions :** Authentification par token avec expiration

---

## 📊 Matrice des Permissions

| Action | Citoyen | Admin | SuperAdmin |
|--------|---------|--------|-----------|
| Voir utilisateurs | ❌ | ✅ | ✅ |
| Créer citoyens | ❌ | ✅ | ✅ |
| Créer admins | ❌ | ❌ | ✅ |
| Modifier citoyens | ❌ | ✅ | ✅ |
| Modifier admins | ❌ | ❌ | ✅ |
| Supprimer citoyens | ❌ | ✅ | ✅ |
| Supprimer admins | ❌ | ❌ | ✅ |
| Gérer signalements | Ses signalements | Tous | Tous |
| Changer statuts | ❌ | ✅ | ✅ |
| Statistiques | ❌ | Limitées | Complètes |
| Configuration système | ❌ | ❌ | ✅ |