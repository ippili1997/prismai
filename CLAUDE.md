# Prism AI - Development Guide

## Project Vision
Prism AI is a privacy-first, self-hosted cloud storage platform where users connect their own AWS S3 or Cloudflare R2 buckets. Users maintain complete ownership of their data while getting intelligent file management capabilities.

## Core Principles
1. **Privacy First**: No file content ever touches our servers
2. **User Ownership**: All data stays in user's own buckets
3. **Progressive Enhancement**: Each phase builds on the previous
4. **Optional Intelligence**: AI features are always optional

## Current Status
**Project Phase**: Phase 1 COMPLETE ✅
**Status**: Core Storage Platform - Fully Implemented
**Frontend**: Vue 3 + Inertia.js (Laravel Breeze)
**Backend**: Laravel 12 with AWS SDK
**Last Updated**: July 30, 2025

## Phase 1: Core Storage Platform (COMPLETE)

### ✅ Authentication System
- Laravel Breeze with Vue.js + Inertia.js
- User registration, login, profile management
- Session-based authentication
- Responsive design with mobile support

### ✅ Bucket Management System
- **Database**: Buckets table with encrypted credential storage
- **Security**: Automatic encryption/decryption of sensitive credentials
- **Features**:
  - Add/remove bucket configurations
  - Support for both S3 and R2 providers
  - Test connection functionality
  - Activate/deactivate buckets (only one active at a time)
  - Visual status indicators
  - Last connected timestamp tracking
- **Authorization**: Policy-based access control (users only see their own buckets)

### ✅ File Browser System
- **File Operations**:
  - List files and folders from connected buckets
  - Navigate through folder hierarchy
  - Breadcrumb navigation
  - File upload with pre-signed URLs
  - File download with pre-signed URLs
  - File deletion
  - Multiple file upload support
- **UI Features**:
  - Table view with file details (size, last modified)
  - Folder icons and file icons
  - Upload progress indicator
  - Responsive design
- **Privacy Features**:
  - Direct browser-to-bucket uploads (files never touch our servers)
  - Pre-signed URLs for secure operations
  - No file content stored in database

### ✅ Technical Infrastructure
- **AWS SDK**: Fully integrated for S3/R2 operations
- **Encryption**: Laravel's built-in encryption for credentials
- **Flash Messages**: Toast notifications for user feedback
- **Navigation**: Complete navigation system with active states
- **Error Handling**: Comprehensive error messages and logging

## Implementation Details

### Database Schema
```sql
buckets:
- id
- user_id (foreign key)
- name (friendly name)
- provider (s3/r2)
- bucket_name
- region (nullable)
- access_key_id (encrypted)
- secret_access_key (encrypted)
- endpoint (nullable)
- public_url (nullable)
- is_active (boolean)
- last_connected_at (timestamp)
- timestamps
```

### Key Files Created/Modified

1. **Models**:
   - `app/Models/Bucket.php` - Bucket model with encryption
   - `app/Models/User.php` - Added buckets relationship

2. **Controllers**:
   - `app/Http/Controllers/BucketController.php` - Bucket management
   - `app/Http/Controllers/FilesController.php` - File operations

3. **Policies**:
   - `app/Policies/BucketPolicy.php` - Authorization rules

4. **Vue Components**:
   - `resources/js/Pages/Buckets/Index.vue` - Bucket listing
   - `resources/js/Pages/Buckets/Create.vue` - Add bucket form
   - `resources/js/Pages/Files/Index.vue` - File browser
   - `resources/js/Components/FlashMessages.vue` - Toast notifications

5. **Routes**:
   - Bucket management routes
   - File management routes
   - Pre-signed URL generation endpoint

6. **Configuration**:
   - Updated navigation in `AuthenticatedLayout.vue`
   - Added authorization in `AppServiceProvider.php`
   - Added flash messages in `HandleInertiaRequests.php`

## R2 Configuration Requirements

### User Requirements:
1. **Access Key ID** - From R2 API token
2. **Secret Access Key** - From R2 API token
3. **Endpoint** - Format: `https://ACCOUNT_ID.r2.cloudflarestorage.com`
4. **Bucket Name** - Exact name from Cloudflare R2

### CORS Configuration (Required for uploads):
```json
[
  {
    "AllowedOrigins": [
      "http://localhost:8000",
      "http://127.0.0.1:8000",
      "https://yourdomain.com"
    ],
    "AllowedMethods": ["GET", "PUT", "POST", "DELETE", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

## Development Commands
```bash
# Start Laravel server
php artisan serve

# Start Vite dev server (in separate terminal)
npm run dev

# Run migrations
php artisan migrate

# Clear caches if needed
php artisan cache:clear
php artisan config:clear

# View logs
tail -f storage/logs/laravel.log
```

## Phase 1 Summary
All Phase 1 objectives have been successfully completed:
- ✅ User authentication and account management
- ✅ Bucket connection and management
- ✅ Basic file browser UI
- ✅ File upload/download functionality
- ✅ Folder navigation
- ✅ Privacy-first architecture (no file content on servers)

## Next Phases (Future Development)

See `project-phases.md` for detailed phase breakdown and implementation roadmap.

**Current Focus**: Phase 2 - Production Readiness & Enhanced Features
- Database migration for multi-user support
- Production deployment options
- Core feature improvements
- Phase 1 feedback implementation

## Security Considerations
- All credentials are encrypted at rest
- Pre-signed URLs expire after set time
- No file content passes through application servers
- User isolation enforced at policy level
- CORS configuration required for browser uploads

## Performance Notes
- File listings limited to 1000 items per request
- Direct browser uploads reduce server load
- Pre-signed URLs cached for performance
- Pagination recommended for large directories

## Maintenance Notes
- Regularly update AWS SDK
- Monitor bucket connection failures
- Check for expired R2 API tokens
- Review security logs
- Update CORS policies as needed

## Hosting Recommendations

### Database Options:
1. **PostgreSQL** (Recommended)
   - Supabase (Free tier available)
   - DigitalOcean Managed Database
   - AWS RDS
   - Neon.tech (Serverless Postgres)

2. **MySQL**
   - PlanetScale (Serverless MySQL)
   - DigitalOcean Managed Database
   - AWS RDS

### Hosting Providers:
1. **VPS/Cloud Hosting**
   - DigitalOcean App Platform
   - AWS EC2 / Lightsail
   - Hetzner Cloud
   - Linode
   - Vultr

2. **Platform-as-a-Service**
   - Railway.app
   - Render.com
   - Fly.io
   - Heroku

### Deployment Considerations:
- **Domain**: Register domain through Namecheap, Cloudflare, or Google Domains
- **SSL**: Use Let's Encrypt (free) or Cloudflare SSL
- **CDN**: Cloudflare for static assets and DDoS protection
- **Email**: SendGrid, Postmark, or Amazon SES for transactional emails
- **Monitoring**: UptimeRobot, Pingdom, or Better Uptime
- **Backups**: Automated daily backups of database and user configurations