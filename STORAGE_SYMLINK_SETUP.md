# Storage Symlink Setup for Hostinger VPS

## Problem
Profile pictures are saved in the database but not displaying on the page. This is because the storage symlink doesn't exist on your Hostinger VPS.

## Solution for Hostinger VPS

### Step 1: Connect via SSH

1. Log into your Hostinger VPS control panel
2. Find your SSH credentials (usually in the VPS management section)
3. Connect using SSH client (PuTTY for Windows, Terminal for Mac/Linux):
   ```bash
   ssh root@your-vps-ip
   # or
   ssh username@your-vps-ip
   ```

### Step 2: Navigate to Your Project

```bash
# Find your project directory (usually in /home/username/domains/yourdomain.com/public_html or /var/www/html)
cd /path/to/your/laravel/project

# Common Hostinger paths:
# cd /home/username/domains/yourdomain.com/public_html
# or
# cd /var/www/html/WD
# or
# cd /home/username/public_html/WD
```

### Step 3: Create Storage Symlink

```bash
# Make sure you're in the Laravel project root (where artisan file is)
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`.

### Step 4: Verify Symlink Creation

```bash
# Check if symlink exists
ls -la public/storage

# Should show something like:
# lrwxrwxrwx 1 user user 25 Jan 26 12:00 storage -> ../storage/app/public
```

### Step 5: Set Proper Permissions

```bash
# Set permissions for storage directory
chmod -R 755 storage/app/public
chmod -R 755 public/storage

# Set ownership (adjust user/group as needed)
chown -R www-data:www-data storage/app/public
chown -R www-data:www-data public/storage

# If www-data doesn't work, try:
# chown -R apache:apache storage/app/public
# or
# chown -R nginx:nginx storage/app/public
```

### Option 2: Manual Symlink Creation

If `php artisan storage:link` doesn't work, create it manually:

**Linux/Mac:**
```bash
cd /path/to/your/project/public
ln -s ../storage/app/public storage
```

**Windows (PowerShell as Administrator):**
```powershell
cd C:\path\to\your\project\public
New-Item -ItemType SymbolicLink -Path "storage" -Target "..\storage\app\public"
```

### Option 3: Copy Files to Public Directory (Alternative)

If symlinks aren't supported, you can copy files:

```bash
# Create directory
mkdir -p public/storage/profile-pictures

# Copy existing files
cp -r storage/app/public/profile-pictures/* public/storage/profile-pictures/

# Set permissions
chmod -R 755 public/storage
```

**Note:** With this method, you'll need to copy new files manually or set up a cron job.

## Verify Setup

After creating the symlink, verify it works:

1. Check if the symlink exists:
   ```bash
   ls -la public/storage
   ```

2. Test the URL in your browser:
   ```
   http://yourdomain.com/storage/profile-pictures/user_1_1234567890.jpg
   ```

3. Check file permissions:
   ```bash
   chmod -R 755 storage/app/public
   chown -R www-data:www-data storage/app/public  # Adjust user/group as needed
   ```

## Troubleshooting

### Permission Issues
```bash
chmod -R 755 storage/app/public
chmod -R 755 public/storage
```

### Symlink Not Working
- Ensure your hosting provider supports symlinks
- Check if `public/storage` directory exists (delete it first if it's a regular directory)
- Verify the target path exists: `storage/app/public`

### Files Still Not Displaying
- Clear Laravel cache: `php artisan cache:clear`
- Clear config cache: `php artisan config:clear`
- Check browser console for 404 errors
- Verify the file actually exists: `ls -la storage/app/public/profile-pictures/`

## Current Code Behavior

The code now checks both:
1. Symlink path: `public/storage/profile-pictures/` (uses `asset()`)
2. Storage URL: Falls back to `Storage::url()` if symlink doesn't exist

This ensures compatibility with both local development and hosted servers.
