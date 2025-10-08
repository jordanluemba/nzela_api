# Dockerfile optimisé pour NZELA API sur Render
FROM php:8.1-apache

# Métadonnées du conteneur
LABEL maintainer="NZELA Team"
LABEL description="API NZELA - Backend PHP avec MySQL"
LABEL version="1.0"

# Variables d'environnement
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV UPLOAD_MAX_FILESIZE=5M
ENV POST_MAX_SIZE=5M
ENV MAX_FILE_UPLOADS=10
ENV MEMORY_LIMIT=256M

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    # Extensions PHP nécessaires
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    # Outils système
    zip \
    unzip \
    git \
    curl \
    # Nettoyage du cache
    && rm -rf /var/lib/apt/lists/*

# Installation et configuration des extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        mbstring \
        opcache

# Configuration PHP optimisée pour production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Configuration PHP personnalisée
RUN { \
    echo 'upload_max_filesize=${UPLOAD_MAX_FILESIZE}'; \
    echo 'post_max_size=${POST_MAX_SIZE}'; \
    echo 'max_file_uploads=${MAX_FILE_UPLOADS}'; \
    echo 'memory_limit=${MEMORY_LIMIT}'; \
    echo 'max_execution_time=300'; \
    echo 'max_input_time=300'; \
    echo 'default_socket_timeout=300'; \
    echo 'date.timezone=Africa/Kinshasa'; \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'error_log=/var/log/php_errors.log'; \
} > /usr/local/etc/php/conf.d/custom.ini

# Configuration Apache
RUN a2enmod rewrite headers expires deflate \
    && { \
        echo '<Directory /var/www/html>'; \
        echo '    Options Indexes FollowSymLinks'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
        echo ''; \
        echo '# Compression GZIP'; \
        echo '<IfModule mod_deflate.c>'; \
        echo '    AddOutputFilterByType DEFLATE text/plain'; \
        echo '    AddOutputFilterByType DEFLATE text/html'; \
        echo '    AddOutputFilterByType DEFLATE text/xml'; \
        echo '    AddOutputFilterByType DEFLATE text/css'; \
        echo '    AddOutputFilterByType DEFLATE application/xml'; \
        echo '    AddOutputFilterByType DEFLATE application/xhtml+xml'; \
        echo '    AddOutputFilterByType DEFLATE application/rss+xml'; \
        echo '    AddOutputFilterByType DEFLATE application/javascript'; \
        echo '    AddOutputFilterByType DEFLATE application/x-javascript'; \
        echo '    AddOutputFilterByType DEFLATE application/json'; \
        echo '</IfModule>'; \
        echo ''; \
        echo '# Sécurité'; \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'Header always set X-Content-Type-Options nosniff'; \
        echo 'Header always set X-Frame-Options DENY'; \
        echo 'Header always set X-XSS-Protection "1; mode=block"'; \
        echo 'Header always set Referrer-Policy "strict-origin-when-cross-origin"'; \
    } > /etc/apache2/conf-available/security.conf \
    && a2enconf security

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers de l'application
COPY . /var/www/html/

# Créer les dossiers nécessaires avec les bonnes permissions
RUN mkdir -p /var/www/html/uploads/signalements \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/log/apache2 \
    && touch /var/log/php_errors.log

# Configuration des permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/logs \
    && chmod 666 /var/log/php_errors.log

# Script de démarrage personnalisé
RUN { \
    echo '#!/bin/bash'; \
    echo 'set -e'; \
    echo ''; \
    echo '# Configuration dynamique du port pour Render'; \
    echo 'if [ ! -z "$PORT" ]; then'; \
    echo '    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf'; \
    echo '    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf'; \
    echo 'fi'; \
    echo ''; \
    echo '# Exécution du script de build si présent'; \
    echo 'if [ -f /var/www/html/build.sh ]; then'; \
    echo '    echo "🚀 Exécution du script de build..."'; \
    echo '    chmod +x /var/www/html/build.sh'; \
    echo '    cd /var/www/html && ./build.sh'; \
    echo 'fi'; \
    echo ''; \
    echo '# Créer le fichier .htaccess avec configuration CORS si absent'; \
    echo 'if [ ! -f /var/www/html/.htaccess ]; then'; \
    echo '    echo "📋 Création du fichier .htaccess..."'; \
    echo '    cat > /var/www/html/.htaccess << "EOF"'; \
    echo 'RewriteEngine On'; \
    echo ''; \
    echo '# CORS pour API'; \
    echo 'Header always set Access-Control-Allow-Origin "*"'; \
    echo 'Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"'; \
    echo 'Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"'; \
    echo ''; \
    echo '# Réponse aux requêtes OPTIONS'; \
    echo 'RewriteCond %{REQUEST_METHOD} OPTIONS'; \
    echo 'RewriteRule ^(.*)$ $1 [R=200,L]'; \
    echo 'EOF'; \
    echo 'fi'; \
    echo ''; \
    echo '# Vérification de la santé de l'\''application'; \
    echo 'echo "🔍 Vérification de la santé de l'\''API..."'; \
    echo 'php -l /var/www/html/health.php || { echo "❌ Erreur de syntaxe dans health.php"; exit 1; }'; \
    echo ''; \
    echo '# Démarrage d'\''Apache'; \
    echo 'echo "🌐 Démarrage du serveur Apache..."'; \
    echo 'exec apache2-foreground'; \
} > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Health check pour Render
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${PORT:-80}/health.php || exit 1

# Exposition du port
EXPOSE 80

# Point d'entrée
CMD ["/usr/local/bin/start.sh"]

# Optimisations pour la taille de l'image
RUN apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*