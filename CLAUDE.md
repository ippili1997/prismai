# Prism AI - Development Guide

## Project Vision
Prism AI is a privacy-first, self-hosted cloud storage platform where users connect their own AWS S3 or Cloudflare R2 buckets. Users maintain complete ownership of their data while getting intelligent file management capabilities.

## Core Principles
1. **Privacy First**: No file content ever touches our servers
2. **User Ownership**: All data stays in user's own buckets
3. **Progressive Enhancement**: Each phase builds on the previous
4. **Optional Intelligence**: AI features are always optional

## Current Status
**Project Phase**: Phase 1 COMPLETE ✅ + Phase 2 & 3 Features In Progress
**Status**: Core Storage Platform + Advanced Features - Production Deployed
**Frontend**: Vue 3 + Inertia.js (Laravel Breeze)
**Backend**: Laravel 12 with AWS SDK
**Database**: Migrated to Supabase (PostgreSQL)
**Production**: Deployed on Railway.app
**Last Updated**: August 2, 2025

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
  - Activate/deactivate buckets (multiple buckets can be active)
  - Direct bucket navigation - click any active bucket to view its files
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
  - ✅ **Bulk delete operations** (multi-select and delete)
- **UI Features**:
  - Table view with file details (size, last modified)
  - Folder icons and file icons with distinct colors by type
  - Upload progress indicator
  - Responsive design
  - ✅ **Multi-select checkboxes** for bulk operations
  - ✅ **File type icons** with unique colors and shapes (images, videos, PDFs, code, etc.)
  - ✅ **Hover tooltips** on action buttons
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
   - `resources/js/Pages/Files/Index.vue` - File browser with bulk operations
   - `resources/js/Components/FlashMessages.vue` - Toast notifications
   - ✅ `resources/js/Components/FileViewer.vue` - **File preview modal**

5. **Routes**:
   - Bucket management routes
   - File management routes (now includes bucket ID: `/buckets/{bucket}/files`)
   - Pre-signed URL generation endpoints:
     - Upload URL: `POST /buckets/{bucket}/upload-url`
     - ✅ View URL: `POST /buckets/{bucket}/view-url` (for file preview)

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

# Build assets for production
npm run build

# Run migrations
php artisan migrate

# Clear caches if needed
php artisan cache:clear
php artisan config:clear

# View logs
tail -f storage/logs/laravel.log

# Test bucket connections
php artisan tinker
# Then: App\Models\Bucket::find(1)->testConnection()
```

## Phase 1 Summary + Enhancements
All Phase 1 objectives have been successfully completed:
- ✅ User authentication and account management
- ✅ Bucket connection and management
- ✅ Basic file browser UI
- ✅ File upload/download functionality
- ✅ Folder navigation
- ✅ Privacy-first architecture (no file content on servers)

### Recent Enhancements (July 31 - August 1, 2025):
- ✅ **Multi-Bucket Support**: Multiple buckets can now be active simultaneously
- ✅ **Direct Bucket Navigation**: Click any active bucket to view its files directly
- ✅ **Improved Routing**: Files are now accessed via `/buckets/{bucket}/files`
- ✅ **Better UX**: No need to constantly switch between buckets
- ✅ **Bucket-Specific Operations**: All file operations now use the correct bucket context

### Phase 2 & 3 Features Implemented (August 1-2, 2025):
- ✅ **File Preview System**: 
  - Image viewer with zoom/pan
  - PDF viewer
  - Video player (up to 100MB)
  - Audio player
  - Text/code viewer with syntax highlighting
  - Keyboard navigation (arrow keys, escape)
- ✅ **Bulk Operations**:
  - Multi-select checkboxes
  - Select all functionality
  - Bulk delete with confirmation
- ✅ **Enhanced UI**:
  - File type icons with distinct colors and shapes
  - Hover tooltips on action buttons
  - Preview button for viewable files
  - Improved file navigation experience
  - ✅ **Loading States** (August 2, 2025):
    - Navigation loading overlay
    - Individual operation indicators
    - Disabled states during operations
    - Smooth transitions between states
- ✅ **Pagination** (August 2, 2025):
  - 100 items per page
  - Load More functionality
  - Continuation token support
  - Seamless infinite scroll experience
- ✅ **Folder Management**:
  - Create new folders with modal interface
  - Folder creation at any directory level
- ✅ **File/Folder Renaming**:
  - Rename files and folders in-place
  - Modal interface for renaming
  - Support for renaming folders with all contents
- ✅ **Drag & Drop Upload**:
  - Drag and drop files or entire folders
  - Visual feedback during drag operations
  - Maintains folder structure when dropping folders

## Next Phases (Future Development)

See `project-phases.md` for detailed phase breakdown and implementation roadmap.

**Current Focus**: Phase 2 - Production Readiness & Enhanced Features
- Database migration for multi-user support ✅ (Migrated to Supabase)
- Production deployment options (Railway.app configuration prepared, not deployed)
- Core feature improvements
- Phase 1 feedback implementation

## Production Status
- ✅ **Database**: Successfully migrated to Supabase (PostgreSQL)
- ✅ **Hosting**: Deployed on Railway.app
- ✅ **Configuration**: Railway deployment completed
- ✅ **Assets**: Pre-built and deployed for production
- ✅ **Production URL**: Active and running on Railway

## Security Considerations
- All credentials are encrypted at rest
- Pre-signed URLs expire after set time
- No file content passes through application servers
- User isolation enforced at policy level
- CORS configuration required for browser uploads

## Performance Notes
- File listings limited to 100 items per page (with Load More)
- Direct browser uploads reduce server load
- Pre-signed URLs cached for performance
- Pagination implemented for large directories
- Continuation token support for efficient S3 API usage

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

## Current Implementation Notes

### Bucket Navigation System
- Each bucket has a unique ID used in routes: `/buckets/{bucket}/files`
- The `is_active` field now means "validated/connected" rather than "currently viewing"
- Multiple buckets can be active (connected) simultaneously
- Clicking on any active bucket navigates directly to its files
- Inactive buckets show an "Activate" button to validate connection

### File Operations
- All file operations (upload, download, delete) now include bucket context
- Pre-signed URLs are generated for the specific bucket being accessed
- File browser maintains bucket context through navigation
- Breadcrumb navigation includes bucket ID in all links

### UI/UX Improvements
- Removed "Files" from main navigation (access files through bucket list)
- Active bucket names are clickable links (blue color)
- Inactive bucket names can be clicked to activate and navigate
- ✅ Multi-select checkboxes for bulk operations
- ✅ File type icons with distinct colors and shapes
- ✅ File preview modal with navigation between files
- ✅ Keyboard shortcuts in preview (arrow keys, escape)
- ✅ Download button in preview modal
- ✅ Smart file type detection for preview support
- ✅ Text file content fetching for small files (<1MB)

## Multi-Agent Development Guidelines

### Working with Multiple Agents
- **Concurrent Development**: Multiple AI agents may be working on different features simultaneously
- **Non-Interference**: Each agent should focus on their assigned task and avoid modifying files outside their scope
- **Communication**: Check git status before starting work to understand current state
- **Coordination**: Review recent commits to understand what other agents have implemented

### Git Commit Guidelines for Agents
- **Selective Staging**: Only stage and commit files directly related to your assigned task
- **Avoid Global Commits**: Never use `git add .` or `git add -A` unless specifically instructed
- **Check Before Committing**: Always run `git status` to verify only intended files are staged
- **Specific File Staging**: Use `git add <specific-file>` for each file you've modified
- **Clear Commit Messages**: Include task context in commit messages (e.g., "Add search functionality to file browser")
- **Branch Awareness**: If working on a feature branch, ensure you're on the correct branch before committing

### Best Practices for Multi-Agent Development
1. **Start with Status Check**: Begin work by checking `git status` and recent commits
2. **Document Changes**: Update relevant documentation only for your specific changes
3. **Test in Isolation**: Ensure your changes work independently without breaking other features
4. **Minimal Scope**: Make the minimum changes necessary to complete your task
5. **Preserve Others' Work**: Don't modify files unless required for your specific task
6. **Clear Handoffs**: Leave clear comments or documentation when your work affects shared components

## Build Process & Environment Management

### Development Environments
1. **Main Project Directory** (`/Users/ippili1997/prismai`)
   - Full Laravel installation with vendor/, node_modules/, .env
   - Run `npm run dev` and `php artisan serve` here
   - Primary development and testing environment

2. **Conductor Workspace** (`.conductor/colombo`)
   - Partial copy for isolated AI development
   - Missing vendor/, node_modules/, .env by design
   - Build works via Vite alias configuration

### Build Configuration
- **vite.config.js**: Dynamic alias for ZiggyVue module
  - Checks for vendor/tightenco/ziggy existence
  - Falls back to ziggy-mock.js in Conductor environment
  - Ensures builds work in both environments

- **Build Files Management**:
  - `/public/build` added to .gitignore (August 2, 2025)
  - Build files no longer tracked in Git
  - Railway generates fresh builds on deployment

### Git Workflow Best Practices
1. **Before Starting Work**:
   ```bash
   git status
   git fetch origin main
   git log origin/main --oneline -10
   ```

2. **Checking for Conflicts**:
   ```bash
   git merge origin/main --no-commit --no-ff
   git diff --name-only origin/main
   ```

3. **Committing Changes**:
   - Stage specific files only: `git add <file>`
   - Never use `git add .` unless necessary
   - Clear, descriptive commit messages
   - Verify staged files: `git status`

4. **Handling Build Files**:
   - Build files are git-ignored
   - Railway handles production builds
   - Local builds for testing only

## Development Guidelines
- **AI Interaction Guidelines**:
  - Never mention that Claude wrote my code or that I am committing through Claude. Please ensure this is not done.