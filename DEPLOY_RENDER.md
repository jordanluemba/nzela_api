# 🚀 Guide de Déploiement NZELA API sur Render

## 📋 Prérequis
- Compte GitHub (gratuit)
- Compte Render (gratuit - 750h/mois)
- Code source dans un repository Git

---

## 🎯 Étapes de Déploiement

### **1. Préparation du Repository**

1. **Créer un repository GitHub**
   ```bash
   git init
   git add .
   git commit -m "Initial commit - NZELA API"
   git branch -M main
   git remote add origin https://github.com/votre-username/nzela-api.git
   git push -u origin main
   ```

### **2. Configuration de la Base de Données**

1. **Se connecter à Render** → [dashboard.render.com](https://dashboard.render.com)
2. **Créer une base MySQL** :
   - Cliquer "New +" → "PostgreSQL" (ou MySQL si disponible)
   - Nom: `nzela-db`
   - Plan: **Free** (limité mais suffisant pour débuter)
   - Région: choisir la plus proche de vos utilisateurs

3. **Importer le schéma** :
   - Utiliser le fichier `database/nzela_db_render.sql`
   - Via l'interface Render ou client MySQL

### **3. Déploiement du Service Web**

1. **Créer un Web Service** :
   - Cliquer "New +" → "Web Service"
   - Connecter votre repository GitHub
   - Branch: `main`

2. **Configuration du service** :
   ```yaml
   Name: nzela-api
   Environment: Docker
   Build Command: chmod +x build.sh && ./build.sh
   Start Command: php -S 0.0.0.0:$PORT -t .
   ```

3. **Variables d'environnement** :
   ```
   ENVIRONMENT=production
   DB_HOST=[URL de votre base Render]
   DB_NAME=nzela_db
   DB_USER=[username base Render]
   DB_PASS=[password base Render]
   API_URL=https://nzela-api.onrender.com
   ```

### **4. Configuration DNS et Domaine**

1. **URL par défaut** : `https://nzela-api.onrender.com`
2. **Domaine personnalisé** (optionnel) :
   - Aller dans Settings → Custom Domains
   - Ajouter votre domaine
   - Configurer les DNS CNAME

---

## 🔧 Optimisations pour le Plan Gratuit

### **Limitations Render Free** :
- ⏰ **750 heures/mois** (environ 25 jours)
- 💤 **Sleep après 15min** d'inactivité
- 🐌 **Cold start** (3-5 secondes)
- 💾 **512MB RAM**, **0.1 CPU**

### **Solutions d'optimisation** :

1. **Keep-alive service** (optionnel) :
   ```javascript
   // keep-alive.js - à héberger séparément
   setInterval(() => {
     fetch('https://nzela-api.onrender.com/health')
       .then(r => console.log('API awake:', new Date()))
       .catch(e => console.log('Ping failed:', e))
   }, 14 * 60 * 1000) // Ping toutes les 14 minutes
   ```

2. **Cache agressif** :
   ```php
   // Dans vos endpoints
   header('Cache-Control: public, max-age=300'); // 5 minutes
   ```

3. **Optimisation base de données** :
   ```sql
   -- Index pour performance
   CREATE INDEX idx_signalements_status_date ON signalements(statut, created_at);
   CREATE INDEX idx_signalements_location ON signalements(province, ville);
   ```

---

## 🛡️ Configuration de Sécurité

### **Variables d'environnement sensibles** :
```bash
# Dans Render Dashboard > Environment
DB_PASS=votre_mot_de_passe_complexe
SESSION_SECRET=cle_secrete_aleatoire_32_caracteres
API_KEY=cle_api_pour_services_externes
```

### **CORS Configuration** :
```php
// Dans config/cors.php
$allowed_origins = [
    'https://votre-frontend.onrender.com',
    'https://nzela-app.onrender.com',
    'https://votre-domaine.com'
];
```

---

## 📊 Monitoring et Logs

### **Logs Render** :
- Dashboard → votre service → Logs
- Logs en temps réel des erreurs PHP
- Monitoring de performance

### **Health Check** :
```php
// health.php
<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'database' => 'connected' // tester la connexion DB
]);
?>
```

---

## 🚀 Déploiement Automatique

### **GitHub Actions** (optionnel) :
```yaml
# .github/workflows/deploy.yml
name: Deploy to Render
on:
  push:
    branches: [ main ]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Trigger Render Deploy
        run: |
          curl -X POST "https://api.render.com/deploy/srv-xxxxx" \
               -H "Authorization: Bearer ${{ secrets.RENDER_API_KEY }}"
```

---

## 💰 Coûts et Alternatives

### **Plan Gratuit Render** :
- ✅ **0€/mois** - Parfait pour débuter
- ❌ Limitations de performance
- ❌ Sleep automatique

### **Alternatives gratuites** :
1. **Vercel** - Excellent pour PHP avec Edge Functions
2. **Railway** - 5$/mois après période gratuite
3. **PlanetScale** - Base de données MySQL gratuite
4. **Supabase** - PostgreSQL + Auth gratuit

### **Migration vers plan payant** :
- **7$/mois** - Hobby plan
- Pas de sleep, SSL automatique
- Performances améliorées

---

## 🔧 Dépannage

### **Erreurs communes** :

1. **Build failed** :
   ```bash
   # Vérifier les permissions
   chmod +x build.sh
   ```

2. **Database connection failed** :
   ```php
   // Vérifier les variables d'environnement
   error_log("DB_HOST: " . $_ENV['DB_HOST']);
   ```

3. **502 Bad Gateway** :
   ```php
   // Vérifier que PHP écoute sur $PORT
   php -S 0.0.0.0:$PORT -t .
   ```

### **Commandes utiles** :
```bash
# Test local avant déploiement
export PORT=8000
php -S 0.0.0.0:8000 -t .

# Test des endpoints
curl https://nzela-api.onrender.com/types/list.php
```

---

## 📚 Ressources

- [Documentation Render](https://render.com/docs)
- [Limites Plan Gratuit](https://render.com/docs/free)
- [PHP sur Render](https://render.com/docs/deploy-php)
- [Variables d'environnement](https://render.com/docs/environment-variables)

---

**🎉 Votre API NZELA sera accessible à l'adresse :**
`https://nzela-api.onrender.com`

**⚡ Temps de déploiement :** 3-5 minutes  
**💸 Coût :** 0€ (plan gratuit)  
**🔄 Auto-deploy :** Activé sur push GitHub