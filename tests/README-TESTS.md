# 🧪 Guide de Tests - API NZELA

## 📁 Fichiers Créés

- `api-tests.http` - Tests REST Client pour VS Code
- `upload-tests.sh` - Tests d'upload avec cURL
- `test-api.php` - Script PHP de tests automatisés
- `NZELA-API-Tests.postman_collection.json` - Collection Postman

## 🚀 Comment Utiliser

### Option 1: VS Code REST Client
1. Installer l'extension "REST Client"
2. Ouvrir `api-tests.http`
3. Cliquer sur "Send Request" au-dessus de chaque test

### Option 2: Script PHP Automatisé
```bash
cd c:\wamp64\www\api\tests
php test-api.php
```

### Option 3: Postman
1. Importer `NZELA-API-Tests.postman_collection.json`
2. Configurer l'environnement avec les variables
3. Exécuter la collection complète

### Option 4: Tests Upload
```bash
# Windows PowerShell
cd c:\wamp64\www\api\tests
bash upload-tests.sh
```

## ⚙️ Configuration Préalable

### 1. Vérifier WampServer
- ✅ Apache et MySQL démarrés
- ✅ Base de données `nzela_db` créée
- ✅ Tables importées

### 2. Variables à Modifier
```
baseUrl = http://localhost/votre-dossier/api
testEmail = votre-email@test.com
testPassword = votre-mot-de-passe
```

### 3. Créer Images de Test
Créer le dossier `test-images/` avec:
- `photo-test.jpg` (< 5MB)
- `photo-test.png` (< 5MB) 
- `photo-test.webp` (< 5MB)
- `large-file.jpg` (> 5MB pour test d'erreur)

## 📊 Tests Recommandés par Ordre

### Phase 1: Tests de Base
1. ✅ Types - Liste et détail
2. ✅ Auth - Inscription et connexion
3. ✅ Signalements - Création et liste

### Phase 2: Tests Fonctionnels
4. ✅ Profil utilisateur
5. ✅ Filtres de signalements
6. ✅ Upload de photos

### Phase 3: Tests d'Erreurs
7. ⚠️ Données manquantes
8. ⚠️ Authentification échouée
9. ⚠️ Fichiers invalides

## 🔍 Points de Vérification

### Réponses Attendues
- ✅ Status HTTP corrects (200, 201, 400, 401, 500)
- ✅ Format JSON cohérent
- ✅ Champs obligatoires présents
- ✅ Messages d'erreur clairs

### Sécurité
- ✅ Mots de passe hashés
- ✅ Sessions PHP actives
- ✅ Validation des uploads
- ✅ Sanitisation des données

### Performance
- ⏱️ Temps de réponse < 1s
- 📊 Pagination fonctionnelle
- 🔄 Gestion des erreurs

## 🐛 Dépannage

### Erreur "Connexion refused"
- Vérifier que WampServer est démarré
- Tester: http://localhost/phpmyadmin

### Erreur "Base de données"
- Vérifier la configuration dans `config/database.php`
- Importer le schéma SQL

### Erreur CORS
- Vérifier les headers dans `config/cors.php`
- Tester depuis le même domaine

### Sessions non persistantes
- Vérifier que `session_start()` est appelé
- Cookies autorisés dans le navigateur

## 📈 Métriques de Succès

- **API Fonctionnelle**: Tous les endpoints répondent
- **Sécurité de Base**: Authentification + validation
- **Robustesse**: Gestion d'erreurs appropriée
- **Performance**: < 1s par requête
- **Documentation**: Tests reproductibles

## 🎯 Prochaines Étapes

1. Exécuter tous les tests
2. Corriger les erreurs trouvées
3. Ajouter des tests d'intégration
4. Implémenter les améliorations de sécurité
5. Déployer en staging