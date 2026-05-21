# Hostinger Deployment Guide — Zero Dependency Tracker

## Overview

This is a **CodeIgniter 4** application. The `public/` directory is the only folder
that should be web-accessible. All PHP logic, configs, and secrets live **outside** `public/`.

---

## Folder Structure (Production)

```
zerodependencytracker/        ← upload this entire folder
├── app/                      ← PHP source (NOT web-accessible)
├── database/                 ← SQL schema/seeds (NOT web-accessible)
├── public/                   ← WEB ROOT — set as document root in Hostinger
│   ├── .htaccess
│   ├── index.php             ← CI4 front controller
│   └── assets/               ← CSS, JS, images
├── writable/                 ← Logs, cache, sessions (chmod 755)
├── vendor/                   ← Install via composer (NOT committed)
├── .env                      ← Create on server (NOT committed)
├── env                       ← Template for .env (committed)
├── composer.json
└── spark                     ← CI4 CLI tool
```

---

## Step-by-Step Deployment on Hostinger

### 1. Upload Files

Upload the **entire project folder** (excluding `vendor/`) to your Hostinger server.

Recommended location (via File Manager or FTP):
```
/home/youruser/zerodependencytracker/
```

> **Do NOT upload to `public_html/` directly.** Upload the whole project one level above.

---

### 2. Set Document Root in Hostinger hPanel

1. Log in to **Hostinger hPanel**
2. Go to **Hosting → Manage → Advanced → Document Root** (or Website section)
3. Change the document root from `public_html/` to:
   ```
   /home/youruser/zerodependencytracker/public
   ```
4. Save and wait for propagation (~1 minute)

> If your plan doesn't support changing the document root, use **Option B** below.

---

### 3. Install Composer Dependencies (via SSH)

Hostinger Business/Premium plans include SSH access:

```bash
cd /home/youruser/zerodependencytracker
composer install --no-dev --optimize-autoloader
```

If SSH is unavailable, install locally and upload the `vendor/` folder.

---

### 4. Create the `.env` File

Copy the `env` template to `.env` and fill in your real values:

```bash
cp env .env
nano .env      # or edit via File Manager
```

**Minimum required values:**
```ini
CI_ENVIRONMENT = production

app.baseURL = 'https://yourdomain.com'

database.default.hostname = localhost
database.default.database = your_hostinger_db_name
database.default.username = your_hostinger_db_user
database.default.password = your_hostinger_db_password

COMBOT_API_KEY = your_whatsapp_api_key
```

> Get your DB credentials from **Hostinger hPanel → Databases → MySQL Databases**

---

### 5. Set Folder Permissions

```bash
chmod -R 755 writable/
chmod -R 755 writable/logs/
chmod -R 755 writable/cache/
chmod -R 755 writable/session/
```

---

### 6. Import Database

1. Go to **hPanel → Databases → phpMyAdmin**
2. Select your database
3. Click **Import** → choose `database/schema.sql` → Go
4. Then import `database/seed.sql` for initial data

---

### 7. Verify SSL / HTTPS

Once SSL is active on Hostinger:

1. Update `.env`:
   ```ini
   app.baseURL = 'https://yourdomain.com'
   app.cookieSecure = true
   app.forceGlobalSecureRequests = true
   ```

2. Uncomment the HTTPS redirect in `public/.htaccess`:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

---

## Option B — If Document Root Cannot Be Changed

If Hostinger doesn't allow changing the document root to `public/`, use this workaround:

1. Upload project to `/home/youruser/zerodependencytracker/`
2. In `public_html/`, create/edit `.htaccess`:
   ```apache
   RewriteEngine On
   RewriteRule ^(.*)$ /zerodependencytracker/public/$1 [L,QSA]
   ```
3. The root `zerodependencytracker/.htaccess` already has the proxy fallback rules.

---

## Cron Job — Audit Reminders

Set up in **hPanel → Advanced → Cron Jobs**:

| Field | Value |
|---|---|
| Minute | `0` |
| Hour | `9` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | `php /home/youruser/zerodependencytracker/spark cron/send-audit-reminders` |

This sends WhatsApp reminders every day at 9:00 AM.

---

## Admin Login

After deployment, visit `https://yourdomain.com/admin` to access the admin portal.

Default admin credentials are set during database seed — **change the password immediately**.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| 500 Internal Server Error | Check `writable/logs/` for errors. Ensure `vendor/` is installed. |
| 404 on all routes | Confirm `public/.htaccess` mod_rewrite is working (`AllowOverride All`) |
| Assets not loading | Ensure `public/assets/` was uploaded. Check `app.baseURL` in `.env`. |
| Database errors | Verify DB credentials in `.env`. Import `schema.sql`. |
| Session not persisting | Check `writable/session/` is chmod 755 and writable. |
| WhatsApp not sending | Set `COMBOT_API_KEY` in `.env`. |
