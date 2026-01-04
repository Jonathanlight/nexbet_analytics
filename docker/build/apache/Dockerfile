FROM php:8.3-apache

ARG TIMEZONE="Europe/Paris"
ENV ACCEPT_EULA=Y
ENV DEBIAN_FRONTEND=noninteractive

# Install system + GD prerequisites + MySQL + cron
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
    default-mysql-client \
    cron

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        zip \
        gd intl pdo pdo_mysql mysqli \
    && docker-php-ext-enable zip gd intl pdo_mysql mysqli

# Install Redis PHP extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install PCOV for code coverage (lighter than Xdebug)
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Apache modules
RUN a2enmod ssl rewrite headers \
    && a2ensite default-ssl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/symfony

# Add crontab for match synchronization
RUN echo "# Sync all sports every 30 minutes\n*/30 * * * * cd /var/www/symfony && /usr/local/bin/php bin/console app:sync:all-sports >> /var/log/cron.log 2>&1\n# Sync odds every 15 minutes\n*/15 * * * * cd /var/www/symfony && /usr/local/bin/php bin/console app:sync-matches >> /var/log/cron.log 2>&1\n" > /etc/cron.d/nexbet-cron \
    && chmod 0644 /etc/cron.d/nexbet-cron \
    && crontab /etc/cron.d/nexbet-cron \
    && touch /var/log/cron.log

# Startup script to run cron and apache
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 443

CMD ["/usr/local/bin/docker-entrypoint.sh"]
