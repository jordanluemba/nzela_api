# API NZELA - Système de Signalement Citoyen

[![Version PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)](h### 📂 Upload de Fichiers

### Upload de Photo de Signalement
```http
POST /upload/photo.php
Content-Type: multipart/form-data

photo: [données binaires du fichier]
```

**Formats supportés :** JPG, PNG, WebP  
**Taille max :** 5MB  
**Redimensionnement auto :** Oui (largeur max 1200px)  
**Stockage :** `uploads/signalements/photo_timestamp_random.ext`

### Upload d'Image de Type
```http
POST /upload/type-image.php
Content-Type: multipart/form-data

image: [données binaires du fichier]
```

**Formats supportés :** JPG, PNG, WebP, SVG  
**Taille max :** 2MB  
**Optimisation :** Oui (128x128px pour icônes)  
**Stockage :** `uploads/types/type_timestamp_random.ext`m)
[![Licence](https://img.shields.io/badge/Licence-MIT-green)](LICENSE)
[![Statut](https://img.shields.io/badge/Statut-Prêt%20Production-brightgreen)](https://github.com)

> **API REST professionnelle pour le signalement d'incidents citoyens et la gestion des services municipaux**

L'API NZELA est un système backend complet conçu pour les municipalités et les organismes gouvernementaux afin de collecter, gérer et suivre les signalements citoyens concernant les problèmes d'infrastructure, les services publics et les préoccupations communautaires.

---

## 📋 Table des Matières

- [Fonctionnalités](#-fonctionnalités)
- [Démarrage Rapide](#-démarrage-rapide)
- [Installation](#-installation)
- [Documentation API](#-documentation-api)
- [Authentification](#-authentification)
- [Tests](#-tests)
- [Déploiement](#-déploiement)
- [Sécurité](#-sécurité)
- [Contribution](#-contribution)

---

## ✨ Features

### 🎯 **Core Functionality**
- **Multi-type Reporting** - Infrastructure, services, security, environment
- **User Management** - Registration, authentication, profile management
- **File Upload** - Photo attachments with automatic processing
- **Geolocation** - GPS coordinates for precise incident mapping
- **Status Tracking** - Real-time status updates (Pending → In Progress → Resolved)

### �️ **Security & Performance**
- **JWT Authentication** - Secure session management
- **Input Validation** - Comprehensive data sanitization
- **Error Handling** - Structured error responses
- **CORS Support** - Cross-origin resource sharing
- **Rate Limiting** - API abuse prevention

### 📊 **Administrative Features**
- **Report Analytics** - Statistics and insights
- **Bulk Operations** - Mass status updates
- **Export Capabilities** - Data export functionality
- **Audit Trail** - Complete action logging

---

## 🚀 Démarrage Rapide

### Prérequis
- **PHP 7.4+** avec extensions : `pdo`, `pdo_mysql`, `json`, `mbstring`
- **MySQL 8.0+** ou MariaDB 10.4+
- **Serveur Web** (Apache/Nginx) avec mod_rewrite

### Installation

1. **Cloner le dépôt**
   ```bash
   git clone https://github.com/your-org/nzela-api.git
   cd nzela-api
   ```

2. **Configuration Base de Données**
   ```bash
   mysql -u root -p < nzela_db.sql
   ```

3. **Configuration**
   ```bash
   cp config/database.example.php config/database.php
   # Éditer les identifiants de base de données
   ```

4. **Permissions**
   ```bash
   chmod 755 uploads/
   chmod 644 config/database.php
   ```

5. **Tester l'Installation**
   ```bash
   curl http://localhost/api/types/list.php
   ```

---

## 📚 Documentation API

### URL de Base
```
Production: https://api.nzela.cd/v1
Développement: http://localhost/api
```

### Format de Réponse
Toutes les réponses suivent une structure JSON cohérente :

```json
{
  "success": true|false,
  "message": "Message lisible",
  "data": { ... },           // En cas de succès
  "error": "Détails d'erreur",  // En cas d'échec
  "timestamp": "2025-10-02T10:30:00Z"
}
```

---

## 🔐 Authentification

### Inscription
```http
POST /auth/register.php
Content-Type: application/json

{
  "firstName": "Jean",
  "lastName": "Dupont",
  "email": "jean.dupont@email.com",
  "password": "MotDePasseSecurise123!",
  "phone": "+243123456789",
  "province": "Kinshasa"
}
```

### Connexion
```http
POST /auth/login.php
Content-Type: application/json

{
  "email": "jean.dupont@email.com",
  "password": "MotDePasseSecurise123!"
}
```

### Endpoints Protégés
Inclure le cookie de session ou l'en-tête Authorization :
```http
Authorization: Bearer {session_token}
Cookie: PHPSESSID={session_id}
```

---

## 📝 Gestion des Signalements

### Créer un Signalement
```http
POST /signalements/create.php
Content-Type: application/json

{
  "type_signalement_id": 1,
  "province": "Kinshasa",
  "ville": "Kinshasa",
  "commune": "Gombe",
  "quartier": "Centre-ville",
  "nom_rue": "Avenue de la Paix",
  "latitude": -4.3317,
  "longitude": 15.3139,
  "description": "Nid-de-poule dangereux sur la chaussée principale",
  "urgence": "Urgent",
  "circulation": "Partiellement",
  "nom_citoyen": "Jean Dupont",
  "telephone": "+243987654321"
}
```

### Lister les Signalements
```http
GET /signalements/list.php?limit=20&offset=0&statut=En%20attente&province=Kinshasa
```

### 🏷️ Gestion des Types

### Lister les Types
```http
GET /types/list.php
```

### Détail d'un Type
```http
GET /types/detail.php?id=1
```

### Créer un Type (Admin)
```http
POST /types/create.php
Content-Type: application/json

{
  "nom": "Nouveau Type",
  "description": "Description du type",
  "image_path": "uploads/types/type_timestamp_random.jpg",
  "ordre_affichage": 9
}
```

### Mettre à Jour un Type (Admin)
```http
PUT /types/update.php
Content-Type: application/json

{
  "id": 1,
  "nom": "Nom modifié",
  "description": "Description mise à jour",
  "ordre_affichage": 5
}
```

### Réorganiser l'Ordre des Types
```http
PUT /types/update-order.php
Content-Type: application/json

{
  "updates": [
    {"id": 1, "ordre_affichage": 1},
    {"id": 2, "ordre_affichage": 2},
    {"id": 3, "ordre_affichage": 3}
  ]
}
```

### Mettre à Jour le Statut (Admin)
```http
PUT /signalements/update-statut.php
Content-Type: application/json

{
  "id": 1,
  "statut": "En cours"
}
```

**Statuts disponibles :** `En attente`, `En cours`, `Traité`, `Rejeté`

---

## � File Upload

### Photo Upload
```http
POST /upload/photo.php
Content-Type: multipart/form-data

photo: [binary file data]
```

**Supported formats:** JPG, PNG, WebP  
**Max size:** 5MB  
**Auto-resize:** Yes (1200px max width)

---

## 🧪 Tests

### Tests Automatisés
```bash
# Exécuter les tests API
php tests/run-tests.php

# Vérifier les endpoints
curl -X GET http://localhost/api/diagnostic.php
```

### Tests Manuels
Utiliser l'interface de test incluse :
```
http://localhost/api/tests/api-tester-pro.html
```

### Tests de Charge
```bash
# Utilisation d'Apache Bench
ab -n 1000 -c 10 http://localhost/api/types/list.php
```

---

## 🚀 Déploiement

### Hébergement Gratuit sur Render

**NZELA API peut être hébergée gratuitement sur [Render.com](https://render.com)** avec 750h/mois incluses.

#### 🎯 **Déploiement Rapide (5 minutes)**

1. **Créer un compte Render** (gratuit)
2. **Pousser le code sur GitHub**
3. **Connecter le repository à Render**
4. **Configurer les variables d'environnement**

```bash
# Variables d'environnement Render
ENVIRONMENT=production
DB_HOST=[Render Database URL]
DB_NAME=nzela_db
DB_USER=[Render Database User]
DB_PASS=[Render Database Password]
```

#### 📋 **Avantages du Plan Gratuit**
- ✅ **0€/mois** - Complètement gratuit
- ✅ **SSL automatique** - HTTPS inclus
- ✅ **Auto-deploy** - Déploiement automatique sur Git push
- ✅ **Monitoring** - Logs et métriques inclus
- ✅ **Base de données MySQL** incluse

#### 🔗 **URL de Production**
```
https://nzela-api.onrender.com
```

> 📖 **Guide détaillé :** Voir [DEPLOY_RENDER.md](DEPLOY_RENDER.md) pour les instructions complètes

### Liste de Contrôle Production

- [ ] **Configuration Environnement**
  ```php
  // config/database.php
  $this->host = 'production-db-host';
  $this->password = 'mot-de-passe-production-fort';
  ```

- [ ] **En-têtes de Sécurité**
  ```apache
  # .htaccess
  Header always set X-Content-Type-Options nosniff
  Header always set X-Frame-Options DENY
  Header always set X-XSS-Protection "1; mode=block"
  ```

- [ ] **Supprimer les Fichiers de Debug**
  ```bash
  rm -f test-*.php debug-*.php repair-*.php diagnostic.php
  rm -rf tests/
  ```

- [ ] **Certificat SSL**
  ```bash
  # S'assurer que HTTPS est correctement configuré
  ```

### Déploiement Docker
```dockerfile
FROM php:8.1-apache
COPY . /var/www/html/
RUN docker-php-ext-install pdo pdo_mysql
EXPOSE 80
```

### Configuration Nginx
```nginx
server {
    listen 80;
    root /var/www/nzela-api;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass php-fpm:9000;
        fastcgi_index index.php;
        include fastcgi_params;
    }
    
    location /config/ { deny all; }
    location ~ /test-.*\.php$ { deny all; }
}
```

---

## 🛡️ Sécurité

### Bonnes Pratiques Implémentées

- ✅ **Validation des Entrées** - Toutes les entrées nettoyées et validées
- ✅ **Prévention Injection SQL** - Requêtes préparées uniquement
- ✅ **Protection XSS** - Encodage de sortie et en-têtes CSP
- ✅ **Protection CSRF** - Validation basée sur des tokens
- ✅ **Limitation de Débit** - Prévient l'abus API
- ✅ **Gestion d'Erreurs** - Aucune donnée sensible dans les messages d'erreur
- ✅ **Sécurité Upload** - Validation de type et limites de taille

### En-têtes de Sécurité
```apache
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Content-Security-Policy "default-src 'self'"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## 📊 Surveillance

### Endpoint de Santé
```http
GET /health
```

### Métriques
- Surveillance du temps de réponse
- Suivi du taux d'erreur
- Performance de la base de données
- Taux de succès des uploads

### Journalisation
```php
// Logs stockés dans : logs/api-{date}.log
[2025-10-02 10:30:15] INFO: Utilisateur inscrit - email: user@example.com
[2025-10-02 10:31:22] ERROR: Échec connexion base de données
```

---

## 🔧 Configuration

### Variables d'Environnement
```bash
# .env
DB_HOST=localhost
DB_NAME=nzela_db
DB_USER=api_user
DB_PASS=mot_de_passe_securise
UPLOAD_MAX_SIZE=5242880
API_RATE_LIMIT=100
```

### Schéma Base de Données
```sql
-- Tables principales
users           # Comptes utilisateurs
signalements    # Rapports citoyens
types_signalements  # Catégories de signalements
```

---

## 📈 Performance

### Fonctionnalités d'Optimisation
- **Indexation base de données** sur les colonnes fréquemment interrogées
- **Optimisation des requêtes** avec JOINs appropriés
- **Stratégie de cache** pour les données statiques
- **Compression d'images** pour les uploads
- **Prêt CDN** pour les assets statiques

### Benchmarks
- **Temps de réponse :** < 200ms (95e percentile)
- **Débit :** 1000+ requêtes/minute
- **Disponibilité :** Objectif 99.9%

---

## 🤝 Contribution

### Configuration Développement
```bash
git clone https://github.com/your-org/nzela-api.git
cd nzela-api
composer install
cp .env.example .env
```

### Standards de Code
- **PSR-12** Standards de codage PHP
- **PHPDoc** pour toutes les méthodes publiques
- **Tests unitaires** pour les nouvelles fonctionnalités
- **Revue de sécurité** pour tous les changements

### Processus Pull Request
1. Fork le dépôt
2. Créer une branche feature (`git checkout -b feature/fonctionnalite-incroyable`)
3. Commit les changements (`git commit -m 'Ajouter fonctionnalité incroyable'`)
4. Push vers la branche (`git push origin feature/fonctionnalite-incroyable`)
5. Ouvrir une Pull Request

---

## 📞 Support

### Documentation
- **Référence API :** [docs/api-reference.md](docs/api-reference.md)
- **Guide d'Intégration :** [docs/integration.md](docs/integration.md)
- **Dépannage :** [docs/troubleshooting.md](docs/troubleshooting.md)

### Contact
- **Support Technique :** tech@nzela.cd
- **Documentation :** docs@nzela.cd
- **Problèmes de Sécurité :** security@nzela.cd

---

## 📄 Licence

Ce projet est sous licence MIT - voir le fichier [LICENSE](LICENSE) pour les détails.

---

## 🏆 Remerciements

- Construit avec les meilleures pratiques PHP modernes
- Inspiré par les initiatives d'engagement citoyen
- Développé pour les municipalités de République Démocratique du Congo
- Contributeurs et retours de la communauté

---

**Fait avec ❤️ pour de meilleures villes et l'engagement citoyen**