# Hostinger VPS Setup Guide for Profile Pictures

## Quick Fix: Create Storage Symlink

### Via SSH (Recommended)

1. **Connect to your Hostinger VPS:**
   ```bash
   ssh root@your-vps-ip
   # Enter your password when prompted
   ```

2. **Navigate to your Laravel project:**
   ```bash
   # Common Hostinger paths:
   cd /home/username/domains/yourdomain.com/public_html
   # OR
   cd /var/www/html/WD
   # OR
   cd /home/username/public_html/WD
   
   # Verify you're in the right place (should see artisan file)
   ls -la artisan
   ```

3. **Create the storage symlink:**
   ```bash
   php artisan storage:link
   ```

4. **Set permissions:**
   ```bash
   chmod -R 755 storage/app/public
   chmod -R 755 public/storage
   chown -R www-data:www-data storage/app/public
   chown -R www-data:www-data public/storage
   ```

5. **Verify it works:**
   ```bash
   # Check symlink exists
   ls -la public/storage
   
   # Should show: storage -> ../storage/app/public
   ```

### Via Hostinger File Manager (Alternative)

If you don't have SSH access:

1. Log into Hostinger control panel
2. Go to File Manager
3. Navigate to your Laravel project's `public` folder
4. Create a symbolic link named `storage` pointing to `../storage/app/public`
   - In File Manager, look for "Create Symbolic Link" or "Create Link" option
   - Source: `../storage/app/public`
   - Link name: `storage`

## Troubleshooting

### Issue: "The [public/storage] link already exists"

**Solution:**
```bash
# Remove existing link/directory
rm -rf public/storage

# Create new symlink
php artisan storage:link
```

### Issue: Permission Denied

**Solution:**
```bash
# Check current permissions
ls -la storage/app/public

# Fix permissions
chmod -R 755 storage/app/public
chown -R www-data:www-data storage/app/public

# If www-data doesn't work, find your web server user:
ps aux | grep -E 'apache|nginx|httpd'

# Then use that user (e.g., apache, nginx, etc.)
```

### Issue: Files Still Not Displaying

1. **Clear Laravel cache:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

2. **Verify file exists:**
   ```bash
   ls -la storage/app/public/profile-pictures/
   # Should show your uploaded files
   ```

3. **Test URL directly:**
   Open in browser: `http://yourdomain.com/storage/profile-pictures/user_1_1234567890.jpg`
   - If 404: Symlink not working
   - If 403: Permission issue
   - If works: Check browser console for other errors

4. **Check web server configuration:**
   - Ensure `.htaccess` allows following symlinks (Apache)
   - Nginx: Ensure symlinks are enabled in config

### Issue: Can't Find Project Directory

**Find your project:**
```bash
# Search for artisan file
find / -name "artisan" -type f 2>/dev/null

# Or search in common locations
ls -la /home/*/domains/*/public_html
ls -la /var/www/html
ls -la /home/*/public_html
```

## Verify Setup

After setup, test:

1. **Check symlink:**
   ```bash
   ls -la public/storage
   # Should show: lrwxrwxrwx ... storage -> ../storage/app/public
   ```

2. **Check file access:**
   ```bash
   # List files in storage
   ls -la storage/app/public/profile-pictures/
   
   # Test if file is accessible via symlink
   ls -la public/storage/profile-pictures/
   ```

3. **Test in browser:**
   - Upload a profile picture
   - Check if it displays in navbar
   - Open browser console (F12) and check for 404 errors

## Additional Notes for Hostinger VPS

- **PHP Version:** Ensure you're using PHP 8.1+ (check: `php -v`)
- **Web Server:** Usually Nginx or Apache (check: `ps aux | grep -E 'nginx|apache'`)
- **Document Root:** Usually `/home/username/domains/yourdomain.com/public_html` or `/var/www/html`
- **User:** Usually `www-data`, `apache`, or `nginx` (check with `ps aux | grep -E 'nginx|apache'`)

## Need Help?

If symlink still doesn't work, you can use the alternative method in the code:
- The code automatically checks both symlink path and Storage::url()
- It will fallback to Storage::url() if symlink doesn't exist
- However, symlink is recommended for better performance
