# Production Deployment Guide (WinSCP / SFTP)

This guide details the deployment process and the exact sequence of actions to perform on the production server after uploading code changes via **WinSCP**.

---

## 📁 1. Transferring Files via WinSCP

Before running commands on the server, upload your changed files using WinSCP:

### What to Upload:
- Changed PHP files (`app/`, `config/`, `routes/`, `database/`, etc.)
- Changed frontend files (`resources/`)
- `composer.json` / `composer.lock` (if backend packages changed)
- `package.json` / `package-lock.json` (if frontend packages changed)
- If you build assets locally: upload `public/build/`

### ⚠️ Files & Directories to NEVER Overwrite in WinSCP:
- **`.env`** (Never overwrite server's production environment file)
- **`storage/`** (Never overwrite server logs, app data, or user uploads)
- **`bootstrap/cache/*.php`** (Never upload local cached configs/routes)

---

## ⚡ Quick Post-Upload Actions (TL;DR)

Open your SSH terminal (PuTTY or built-in terminal in WinSCP) and execute:

```bash
cd /var/www/html/bidora.com.ng # or /var/www/ngcarrygo.com/digital

# 1. Put app in maintenance mode (optional, allows bypass with cookie)
php artisan down --secret="deploy-bypass-token" --retry=60

# 2. Update PHP packages (run if composer.json/lock changed)
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Run database migrations
php artisan migrate --force

# 4. Compile frontend assets (skip if built locally & uploaded public/build)
npm ci --prefer-offline
npm run build

# 5. Refresh and optimize Laravel caches
php artisan optimize:clear
php artisan optimize
php artisan view:cache

# 6. Restart queue workers and Reverb WebSocket server
php artisan queue:restart
sudo supervisorctl restart carrygo-worker:*
sudo supervisorctl restart carrygo-reverb

# 7. Reload web server / PHP-FPM
sudo systemctl reload apache2    # or sudo systemctl reload nginx && sudo systemctl reload php8.4-fpm

# 8. Bring application back online
php artisan up

# 9. Verify storage and cache permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 📋 Detailed Step-by-Step Action List

### Step 1: Upload Files with WinSCP
1. Connect to the server in WinSCP using your SSH/SFTP credentials.
2. Navigate to your local project folder on the left pane and the server app folder on the right pane (`/var/www/html/bidora.com.ng`).
3. Transfer updated files and directories.

---

### Step 2: Enter Maintenance Mode (Recommended)
While applying database migrations and optimizing caches, enable maintenance mode so users see a clean waiting screen:

```bash
php artisan down --secret="deploy-bypass-token" --retry=60
```
> **Tip:** You can access the site while in maintenance mode by visiting `https://bidora.com.ng/deploy-bypass-token` in your browser.

---

### Step 3: Install/Update Composer Dependencies
If any `composer.json` or `composer.lock` changes were uploaded:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

> If new packages were added, automatically discover packages:
> `php artisan package:discover --ansi`

---

### Step 4: Run Database Migrations
Apply any new database tables, columns, or indexes:

```bash
php artisan migrate --force
```

If the update requires running specific seeders or reconciliation commands:

```bash
# Example if a specific seeder is needed:
# php artisan db:seed --class=SpecificSeeder --force

# Example reconciliation command:
# php artisan auctions:reconcile
```

---

### Step 5: Frontend Build (Vite + Vue 3 + Tailwind CSS)
You have two options for frontend assets:

- **Option A (Recommended if Node is installed on server):**
  Run the build on the server:
  ```bash
  npm ci --prefer-offline
  npm run build
  ```
- **Option B (Built on local machine):**
  Run `npm run build` locally, then upload the generated `public/build/` directory directly through WinSCP.

> **Important:** If any `.env` variables beginning with `VITE_` were updated (e.g. `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`), building assets is **mandatory** because Vite embeds these values into the compiled static JS files.

---

### Step 6: Clear & Refresh Application Caches
Always clear out stale cached configuration, routes, and views so the new code is immediately recognized:

```bash
# Clear old compiled files
php artisan optimize:clear

# Cache configuration, routes, and events
php artisan optimize

# Cache Blade & Inertia views
php artisan view:cache
```

---

### Step 7: Restart Queue Workers & Reverb WebSockets
Long-running PHP background processes in Supervisor keep old code loaded in RAM until explicitly restarted:

```bash
# Gracefully signal queue workers to restart after finishing current task
php artisan queue:restart

# Restart worker processes in Supervisor
sudo supervisorctl restart carrygo-worker:*

# Restart Reverb WebSocket daemon in Supervisor
sudo supervisorctl restart carrygo-reverb
```

---

### Step 8: Reload Web Server / PHP-FPM
Reload your HTTP server to clear the PHP OPcache:

**For Apache:**
```bash
sudo systemctl reload apache2
```

**For Nginx + PHP-FPM:**
```bash
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
```

---

### Step 9: Bring Application Back Online
Once all commands succeed, disable maintenance mode:

```bash
php artisan up
```

---

### Step 10: Ensure Storage and Cache Permissions
Uploading files via SFTP can sometimes change file owners to your SSH login user. Re-verify that the web server user (`www-data`) owns writable folders:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 🔍 Post-Deployment Verification & Health Checks

| Check | Command / Action | Expected Result |
|-------|------------------|-----------------|
| **Website HTTP Check** | `curl -I https://bidora.com.ng` | `HTTP/2 200` or `HTTP/1.1 200 OK` |
| **Worker Status** | `sudo supervisorctl status carrygo-worker:*` | `RUNNING` for all worker processes |
| **Reverb Status** | `sudo supervisorctl status carrygo-reverb` | `RUNNING` (steady pid, no crash loop) |
| **Reverb Port** | `ss -tlnp \| grep 8080` | `127.0.0.1:8080` listening |
| **Cron Scheduler** | `php artisan schedule:list` | Displays active tasks (e.g. `auctions:reconcile`) |
| **Laravel Error Logs** | `tail -n 50 storage/logs/laravel.log` | No fatal errors or exceptions |
| **Worker Logs** | `tail -n 50 storage/logs/worker.log` | Normal processing logs |
| **Browser WS Check** | Browser DevTools → Network → `WS` | Connection to `wss://bidora.com.ng/app/...` status 101 |

---

## 🤖 Server Post-Deploy Script (`post-deploy.sh`)

To avoid typing all server commands manually every time you finish an upload via WinSCP, save this script on the server:

Create `/var/www/html/bidora.com.ng/post-deploy.sh`:

```bash
#!/usr/bin/env bash
set -e

echo "🚀 Running post-deployment routine..."

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

# 1. Maintenance Mode
echo "⏸️  Activating maintenance mode..."
php artisan down --retry=60 || true

# 2. Dependencies
echo "📦 Updating composer packages..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "🎨 Building frontend assets..."
if [ -f "package.json" ]; then
    npm ci --prefer-offline || npm install --production=false
    npm run build
fi

# 3. Database Migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# 4. Cache Optimization
echo "⚡ Refreshing caches..."
php artisan optimize:clear
php artisan optimize
php artisan view:cache

# 5. Restart Daemons
echo "🔄 Restarting queue workers and Reverb..."
php artisan queue:restart
sudo supervisorctl restart carrygo-worker:*
sudo supervisorctl restart carrygo-reverb

# 6. Reload Web Server
echo "🌐 Reloading web server..."
if systemctl is-active --quiet apache2; then
    sudo systemctl reload apache2
elif systemctl is-active --quiet nginx; then
    sudo systemctl reload nginx
    sudo systemctl reload php8.4-fpm || true
fi

# 7. Permissions
echo "🔒 Fixing permissions on storage and bootstrap/cache..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 8. Bring Online
echo "▶️ Bringing application online..."
php artisan up

echo "✅ Post-deployment routine completed successfully!"
```

Make it executable on the server once:
```bash
chmod +x post-deploy.sh
```

Now, every time you finish uploading files via WinSCP, simply open SSH and run:
```bash
./post-deploy.sh
```

---

## 🚨 Rollback Procedures

If an issue occurs after an upload:

1. **Re-upload previous backup files** using WinSCP.
2. **Rollback Migrations (if applicable):**
   ```bash
   php artisan migrate:rollback --step=1 --force
   ```
3. **Re-clear Caches and Rebuild Assets:**
   ```bash
   npm run build
   php artisan optimize:clear
   php artisan optimize
   ```
4. **Restart Services:**
   ```bash
   php artisan queue:restart
   sudo supervisorctl restart carrygo-worker:*
   sudo supervisorctl restart carrygo-reverb
   sudo systemctl reload apache2
   php artisan up
   ```
