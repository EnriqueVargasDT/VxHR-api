FROM php:8.2-fpm-bookworm

ARG PHP_UID=1000
ARG PHP_GID=1000
ARG INSTALL_SQLSRV=true
ARG INSTALL_XDEBUG=false

RUN apt-get update && apt-get install -y \
    git curl unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libicu-dev libonig-dev libxml2-dev libxslt1-dev libpq-dev libssl-dev \
    zlib1g-dev libcurl4-openssl-dev gnupg2 ca-certificates apt-transport-https \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd intl mbstring pdo pdo_mysql opcache zip \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Xdebug opcional
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then \
      pecl install xdebug && docker-php-ext-enable xdebug ; \
    fi

# Drivers SQL Server (Azure) + toolchain para compilar PECL
RUN if [ "$INSTALL_SQLSRV" = "true" ]; then \
      set -eux; \
      # Toolchain para PECL y ODBC headers
      apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS curl gnupg2 ca-certificates apt-transport-https \
        unixodbc unixodbc-dev libgssapi-krb5-2; \
      \
      # Repo de Microsoft apuntando a Debian 12 (bookworm) — no existe trixie aún
      curl https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/ms.gpg; \
      ARCH="$(dpkg --print-architecture)"; \
      echo "deb [arch=${ARCH} signed-by=/usr/share/keyrings/ms.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/microsoft.list; \
      apt-get update; \
      ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 mssql-tools18; \
      echo 'export PATH="$PATH:/opt/mssql-tools18/bin"' > /etc/profile.d/mssql-tools.sh; \
      \
      # Compilar e instalar extensiones PECL
      pecl channel-update pecl.php.net; \
      pecl install sqlsrv pdo_sqlsrv; \
      docker-php-ext-enable sqlsrv pdo_sqlsrv; \
      \
      # Limpieza
      apt-get purge -y --auto-remove $PHPIZE_DEPS; \
      rm -rf /var/lib/apt/lists/*; \
    fi

# Config PHP
COPY php.ini /usr/local/etc/php/conf.d/php-custom.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

# Usuario no root para evitar problemas de permisos en host
RUN groupadd -g ${PHP_GID} app && useradd -u ${PHP_UID} -g app -m app
USER app

WORKDIR /var/www/html
