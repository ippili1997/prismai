# Prism AI

Privacy-first cloud storage management platform that lets you connect your own AWS S3 or Cloudflare R2 buckets while maintaining complete ownership of your data.

## Features

- **Connect Multiple Storage Buckets** - Support for AWS S3 and Cloudflare R2
- **Direct Browser Uploads** - Files go straight from your browser to your bucket
- **File Management** - Browse, upload, download, delete, and organize files
- **Privacy Focused** - Your files never touch our servers
- **Bulk Operations** - Select multiple files for efficient management
- **File Preview** - View images, PDFs, videos, and text files directly in browser

## Tech Stack

- Laravel 12
- Vue 3 + Inertia.js
- Tailwind CSS
- AWS SDK for PHP
- PostgreSQL

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- PostgreSQL or MySQL

## Installation

```bash
# Clone the repository
git clone https://github.com/ippili1997/prismai.git
cd prismai

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env file

# Run migrations
php artisan migrate

# Build frontend assets
npm run build
```

## Development

```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in another terminal)
npm run dev
```

## Configuration

### R2 Bucket Setup

Users need to configure CORS for their R2 buckets to enable direct uploads:

```json
[
  {
    "AllowedOrigins": ["https://yourdomain.com"],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

## License

This project is private and proprietary. All rights reserved.