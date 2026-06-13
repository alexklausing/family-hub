FROM php:8.4-fpm-bookworm

# --------------------------------------------------------------------
# Fix bad proxy / flaky apt environments
# --------------------------------------------------------------------
RUN echo "Acquire::http::Pipeline-Depth 0; Acquire::http::No-Cache true; Acquire::BrokenProxy true;" \
    > /etc/apt/apt.conf.d/99fixbadproxy

# --------------------------------------------------------------------
# System dependencies + PHP extensions
# --------------------------------------------------------------------
RUN apt-get update --fix-missing \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        procps \
        pkg-config \
        libzip-dev \
        libpq-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        wget \
        gnupg \
        libnss3 \
        libatk-bridge2.0-0 \
        libgtk-3-0 \
        libx11-xcb1 \
        libxcomposite1 \
        libxdamage1 \
        libxrandr2 \
        libgbm1 \
        libasound2 \
        libpangocairo-1.0-0 \
        libxshmfence1 \
        fonts-liberation \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
        mbstring \
        exif \
        pcntl \
        gd \
    && rm -rf /var/lib/apt/lists/*

# --------------------------------------------------------------------
# Install Chromium + matching ChromeDriver
# --------------------------------------------------------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        chromium \
        chromium-driver \
    && rm -rf /var/lib/apt/lists/*

# --------------------------------------------------------------------
# Install Composer
# --------------------------------------------------------------------
RUN curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer

# --------------------------------------------------------------------
# Create Sail User
# --------------------------------------------------------------------
RUN groupadd --force -g 1000 sail
RUN useradd -ms /bin/bash --no-user-group -g 1000 -u 1000 sail

# --------------------------------------------------------------------
# Set working directory & fix home permissions for Composer cache
# --------------------------------------------------------------------
WORKDIR /var/www/html
RUN mkdir -p /var/www/.cache/composer && chown -R sail:sail /var/www

# --------------------------------------------------------------------
# Copy application files
# --------------------------------------------------------------------
COPY --chown=sail:sail . .

# --------------------------------------------------------------------
# Fix Git ownership warnings
# --------------------------------------------------------------------
RUN git config --global --add safe.directory /var/www/html

# --------------------------------------------------------------------
# Laravel permissions
# --------------------------------------------------------------------
RUN chown -R sail:sail storage bootstrap/cache

# --------------------------------------------------------------------
# Drop root privileges
# --------------------------------------------------------------------
USER sail

# --------------------------------------------------------------------
# PHP-FPM
# --------------------------------------------------------------------
EXPOSE 9000
CMD ["php-fpm"]
