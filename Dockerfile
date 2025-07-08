FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    ca-certificates \
    apt-transport-https \
    unixodbc-dev \
    libssl-dev

RUN curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
  | gpg --dearmor > /etc/apt/trusted.gpg.d/microsoft.gpg

RUN echo "deb [arch=amd64 signed-by=/etc/apt/trusted.gpg.d/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
  > /etc/apt/sources.list.d/mssql-release.list

# ⚠️ Usa msodbcsql18, no 17
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18

# Instalar extensiones SQL Server de PHP
RUN pecl install sqlsrv pdo_sqlsrv \
  && docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
