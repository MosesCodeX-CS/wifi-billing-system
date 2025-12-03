# Hotspot System (PHP) - Full Implementation

This project includes:
- Frontend portal (`public/`)
- API endpoints (`api/`)
- M-Pesa Daraja integration (`core/mpesa.php`)
- MySQL DB schema (`sql/database.sql`)
- RouterOS API integration via composer (`evilfreelancer/routeros-api-php`)
- Admin panel (`public/admin/`)
- Cron reconciliation script (`cron/mpesa_reconcile.php`)

## Setup (summary)
1. Copy files to your server. `public/` should be served by your web server root.
2. Create MySQL database:
   ```
   mysql -u root -p < sql/database.sql
   ```
3. Create a `.env` file in the project root with:
   ```
   DB_HOST=127.0.0.1
   DB_NAME=bellamy_hotspot
   DB_USER=hotspot_user
   DB_PASS=your_db_password
   MPESA_ENV=sandbox
   MPESA_CONSUMER_KEY=...
   MPESA_CONSUMER_SECRET=...
   MPESA_SHORTCODE=174379
   MPESA_PASSKEY=...
   MPESA_CALLBACK=https://yourdomain.com/public/mpesa-callback.php
   ROUTER_HOST=192.168.88.1
   ROUTER_USER=apiUser
   ROUTER_PASS=StrongPass
   APP_BASE_URL=https://yourdomain.com
   ADMIN_USER=admin
   ADMIN_PASS=strongadminpassword
   ```
4. Install composer dependencies (for RouterOS API):
   ```
   composer install
   ```
5. Ensure `storage/` is writable by the web server user.
6. Point your hotspot walled-garden to allow access to your domain and the MPesa domains.

## Nginx config (example)
```
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    root /var/www/hotspot/public;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location /api/ {
        try_files $uri =404;
    }
}
```

## Cron (reconcile)
Add to crontab for user that can run PHP:
```
*/5 * * * * /usr/bin/php /var/www/hotspot/cron/mpesa_reconcile.php >> /var/log/hotspot_reconcile.log 2>&1
```

## Security notes
- Use HTTPS (Let's Encrypt) for callbacks and site.
- Protect admin with strong password; in production use 2FA.
- Move sensitive files outside webroot and protect `core/` with server rules.
- Use a proper RouterOS client and secure router API access (management VLAN, firewall).
