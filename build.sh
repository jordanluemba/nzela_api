#!/bin/bash

# Script de build pour Render
echo "🚀 Déploiement NZELA API sur Render..."

# Créer les dossiers nécessaires
mkdir -p uploads/signalements
mkdir -p logs

# Définir les permissions
chmod 755 uploads/
chmod 755 uploads/signalements/
chmod 755 logs/

# Copier la configuration de production
if [ "$ENVIRONMENT" = "production" ]; then
    echo "📋 Configuration production..."
    cp config/database.render.php config/database.php
    cp .env.production .env
fi

# Créer le fichier .htaccess pour Render
cat > .htaccess << 'EOF'
# Configuration Apache pour Render
RewriteEngine On

# CORS Headers pour API
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"

# Sécurité
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Cache pour les assets statiques
<FilesMatch "\.(jpg|jpeg|png|gif|css|js)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>

# Redirect vers HTTPS en production
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Protection des fichiers sensibles
<Files ~ "^\.">
    Require all denied
</Files>

<FilesMatch "(\.env|config\.php|\.log)$">
    Require all denied
</FilesMatch>

# Routage API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ $1 [L]
EOF

echo "✅ Build terminé avec succès!"