 # Production setup guide (Ubuntu + Apache)

Carrygo Digital production processes: **scheduler** (cron), **queue workers** (Supervisor), and **Reverb** (Supervisor + Apache WebSocket proxy).

| Item | Value |
|------|--------|
| App path | `/var/www/html/bidora.com.ng` |
| Web server | Apache |
| Domain | `bidora.com.ng` (adjust if different) |
| Reverb (internal) | `127.0.0.1:8080` |
| Public WebSocket path | `wss://bidora.com.ng/app/...` |

---

## Prerequisites

- Ubuntu server with PHP, Composer, Node (for builds), MySQL/PostgreSQL, and Apache installed.
- Application deployed to `/var/www/html/bidora.com.ng`.
- SSL certificate configured for Apache (e.g. Certbot).

---

## 1. Install Supervisor

Supervisor keeps queue workers and Reverb running after crashes or reboots.

```bash
sudo apt update
sudo apt install -y supervisor
```

---

## 2. Scheduler (cron)

Production uses **one cron entry** that runs every minute. Do **not** use `php artisan schedule:work` in production (that is for local development).

Edit crontab for the user that runs the app (typically `www-data`):

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/ngcarrygo.com/digital && php artisan schedule:run >> /dev/null 2>&1
```

Verify scheduled tasks:

```bash
cd /var/www/ngcarrygo.com/digital
php artisan schedule:list
```

This project runs `auctions:reconcile` every minute as a fallback when delayed `CloseAuctionJob` jobs fail.

---

## 3. Queue workers (Supervisor)

Use `queue:work`, not `queue:listen` (lower overhead in production).

Create `/etc/supervisor/conf.d/carrygo-worker.conf`:

```ini
[program:carrygo-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/ngcarrygo.com/digital/artisan queue:work database --sleep=1 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/ngcarrygo.com/digital
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ngcarrygo.com/digital/storage/logs/worker.log
stopwaitsecs=95
```

**Notes:**

- Change `user=www-data` if your deploy user owns the app files.
- Replace `database` with `redis` in the command if `.env` has `QUEUE_CONNECTION=redis`.
- Increase `numprocs` if the `jobs` table backlog grows.
- `stopwaitsecs` must be greater than the longest job timeout.

Enable workers:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start carrygo-worker:*
sudo supervisorctl status
```

After each deploy:

```bash
cd /var/www/ngcarrygo.com/digital
php artisan queue:restart
```

---

## 4. Reverb (Supervisor)

Reverb is a long-running WebSocket server. Bind it to localhost; Apache terminates TLS and proxies `/app`.

Create `/etc/supervisor/conf.d/carrygo-reverb.conf`:

```ini
[program:carrygo-reverb]
command=/usr/bin/php /var/www/ngcarrygo.com/digital/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/ngcarrygo.com/digital
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/ngcarrygo.com/digital/storage/logs/reverb.log
```

Enable Reverb:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start carrygo-reverb
sudo supervisorctl status carrygo-reverb
```

**High connection volume:** raise open-file limits in `/etc/supervisor/supervisord.conf`:

```ini
[supervisord]
minfds=10000
```

Then restart Supervisor:

```bash
sudo systemctl restart supervisor
```

**Horizontal scaling (optional):** set `REVERB_SCALING_ENABLED=true` and use a shared Redis instance. Run `reverb:start` on multiple servers behind a load balancer.

---

## 5. Production `.env`

Edit `/var/www/ngcarrygo.com/digital/.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-key
REVERB_APP_SECRET=your-secret

REVERB_HOST=ngcarrygo.com
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# NOTE: Do NOT define REVERB_TLS_CERT or REVERB_TLS_KEY in production.
# In production, SSL/TLS termination is handled by the reverse proxy (Apache or Nginx).

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Generate Reverb credentials once (if not already done):

```bash
cd /var/www/ngcarrygo.com/digital
php artisan reverb:install
```

Rebuild frontend after changing `VITE_*` variables:

```bash
npm run build
```

---

## 6. Reverse Proxy (Apache or Nginx) — WebSocket proxy to Reverb

Choose the reverse proxy option matching your production stack. The proxy handles SSL termination and forwards the websocket connection locally over HTTP.

### Option A: Apache Configuration

#### Enable modules (once)

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite headers ssl
sudo systemctl restart apache2
```

#### Virtual host configuration

Inside your SSL vhost for `ngcarrygo.com` (e.g. `/etc/apache2/sites-available/ngcarrygo.com-le-ssl.conf`), in the `<VirtualHost *:443>` block:

```apache
DocumentRoot /var/www/ngcarrygo.com/digital/public

<Directory /var/www/ngcarrygo.com/digital/public>
    AllowOverride All
    Require all granted
</Directory>

# Reverb WebSockets (Echo connects to wss://ngcarrygo.com/app/...)
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"
RequestHeader set X-Forwarded-Port "443"

ProxyPass        /app ws://127.0.0.1:8080/app
ProxyPassReverse /app ws://127.0.0.1:8080/app
```

#### Test and reload

```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### Option B: Nginx Configuration

If you are using Nginx instead of Apache in production, add the following configuration to your secure server block (listening on port 443 with SSL):

```nginx
server {
    listen 443 ssl http2;
    server_name ngcarrygo.com;

    root /var/www/ngcarrygo.com/digital/public;
    index index.php;

    # Reverb WebSockets proxy
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Scheme $scheme;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ... remaining standard Laravel Nginx config (PHP-FPM socket, SSL cert paths, etc.)
}
```

**Subdirectory deployment:** If the public URL is not the domain root (e.g. app under `/digital`), keep `DocumentRoot` / `root` pointed at `public` and ensure this vhost is the one serving the domain. Echo/Reverb still use `/app` at the host root unless you intentionally change paths in Reverb and Echo config.

---

## 7. Permissions

```bash
sudo chown -R www-data:www-data /var/www/ngcarrygo.com/digital/storage
sudo chown -R www-data:www-data /var/www/ngcarrygo.com/digital/bootstrap/cache
```

---

## 8. Deploy routine

For an in-depth checklist, automated script, and rollback procedures, see the complete [Deployment Guide](file:///Users/osanga/Herd/bidora/docs/deployment_guide.md).

Run after uploading new code (via WinSCP):

```bash
cd /var/www/ngcarrygo.com/digital # or /var/www/html/bidora.com.ng

composer install --no-dev --optimize-autoloader
npm ci && npm run build   # when frontend or VITE_* changed

php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan view:cache

php artisan queue:restart
sudo supervisorctl restart carrygo-worker:*
sudo supervisorctl restart carrygo-reverb
sudo systemctl reload apache2
```


---

## 9. Health checks

| Check | Command |
|--------|---------|
| Scheduled tasks | `php artisan schedule:list` |
| Queue workers | `sudo supervisorctl status carrygo-worker:*` |
| Reverb process | `sudo supervisorctl status carrygo-reverb` |
| Reverb listening | `ss -tlnp \| grep 8080` |
| Worker log | `tail -f storage/logs/worker.log` |
| Reverb log | `tail -f storage/logs/reverb.log` |

Test WebSocket locally on the server:

```bash
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  http://127.0.0.1:8080/app/your-app-key
```

In the browser (DevTools → Network → WS), confirm connections to `wss://ngcarrygo.com/app/...`.

---

## 10. Troubleshooting

| Symptom | Likely cause | Action |
|--------|----------------|--------|
| Auctions do not close on time | Queue worker down | `supervisorctl status`, check `jobs` table |
| Missed closures persist | Scheduler not running | Verify `www-data` crontab, `schedule:list` |
| No live bid updates | Reverb down or wrong broadcast driver | `BROADCAST_CONNECTION=reverb`, restart `carrygo-reverb` |
| WebSocket 502 | Reverb not on 8080 | `ss -tlnp \| grep 8080`, check `reverb.log` |
| WebSocket 404 | Apache proxy missing | Confirm `proxy_wstunnel`, `ProxyPass /app` in SSL vhost |
| Stale frontend Echo config | `VITE_*` not rebuilt | `npm run build` after `.env` changes |

---

## Quick reference

| Service | How it runs |
|---------|-------------|
| Scheduler | Cron → `php artisan schedule:run` (every minute) |
| Queue | Supervisor → `queue:work` (2 processes by default) |
| Reverb | Supervisor → `reverb:start` on `127.0.0.1:8080` |
| Public WSS | Apache `ProxyPass /app` → Reverb |

Do not run scheduler, queue, and Reverb in a single shell script without a process manager—if one process exits, auctions and real-time features can fail silently.
