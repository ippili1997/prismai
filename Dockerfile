FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip

# Install Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy all files first
COPY . .

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Install npm dependencies and build assets
RUN npm install
RUN npm run build

# Run composer scripts after everything is in place
RUN composer run-script post-autoload-dump

# Expose port
EXPOSE 8000

# Start server
CMD php artisan migrate --force && php artisan config:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8000}