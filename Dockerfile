# ============================================
# Dockerfile para CompuTécnicos Project
# PHP 8.2 + Apache + Extensiones necesarias
# ============================================
FROM php:8.2-apache

# Metadatos de la imagen
LABEL maintainer="CompuTécnicos Team"
LABEL description="Plataforma e-commerce CompuTécnicos"
LABEL version="1.0"

# ─── Variables de entorno por defecto ───
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV DB_HOST=db
ENV DB_NAME=computecnicos
ENV DB_USER=root
ENV DB_PASS=computecnicos_secret

# ─── Instalar dependencias del sistema ───
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Para extensiones PHP
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    # Para DomPDF (fuentes y renderizado)
    libfontconfig1 \
    fonts-dejavu-core \
    # Utilidades
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
        xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ─── Habilitar módulos de Apache necesarios ───
RUN a2enmod rewrite headers expires

# ─── Configuración de Apache ───
RUN echo '<Directory /var/www/html>\n\
    Options -Indexes +FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# ─── Configuración PHP optimizada para producción ───
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/custom.ini $PHP_INI_DIR/conf.d/99-custom.ini

# ─── Instalar Composer ───
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ─── Copiar archivos de dependencias primero (cache de Docker) ───
COPY composer.json composer.lock* /var/www/html/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# ─── Copiar todo el código fuente ───
COPY . /var/www/html/

# ─── Crear directorios necesarios y asignar permisos ───
RUN mkdir -p /var/www/html/uploads \
             /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/logs

# ─── Script de entrada personalizado ───
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ─── Puerto expuesto ───
EXPOSE 80

# ─── Punto de entrada ───
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
