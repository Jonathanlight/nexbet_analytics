FROM php:8.3-apache

ARG TIMEZONE="Europe/Paris"
ENV ACCEPT_EULA=Y
ENV DEBIAN_FRONTEND=noninteractive

# Install system + GD prerequisites + PostgreSQL
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    curl \
    zip \
    wget \
    ssh \
    libssl-dev \
    libffi-dev \
    ca-certificates \
    nano \
    gnupg \
    gnupg2 \
    libicu-dev \
    unixodbc-dev \
    freetds-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpq-dev \
    postgresql-client

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        zip \
        gd intl pdo pdo_pgsql pgsql \
    && docker-php-ext-enable zip gd intl pdo_pgsql pgsql

# Apache modules
RUN a2enmod ssl rewrite headers \
    && a2ensite default-ssl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/symfony

EXPOSE 80 443

CMD ["apache2-foreground"]
