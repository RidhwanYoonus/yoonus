# Moving the shop to cPanel — checklist

Work through it top to bottom. Nothing here needs SSH; where a command is shown you can use
**cPanel → Terminal** if your host offers it, otherwise the admin buttons mentioned.

---

## A. What to take from the PC (`C:\xampp\htdocs\yoonus`)

| Take | Why |
|---|---|
| The whole folder **including `vendor\`** and **`public\build\`** | `vendor` = PHP libraries, `public\build` = design files (CSS/JS). Without them the site is blank or unstyled. |
| **`storage\app\`** (both `public\` and `private\`) | All uploaded product photos, review photos, lab logos, certificates — and customers' **ID photos** (private). |
| A **database export** (`.sql`) | Products, orders, customers, affiliates, settings (incl. API keys you typed in Settings). |
| Your **`.env`** | You will edit it on the server (section D). |

**Do NOT take** (delete from the copy before zipping):

- `node_modules\` (not needed on the server, very big)
- everything inside `storage\logs\`, `storage\framework\cache\data\`, `storage\framework\sessions\`, `storage\framework\views\`
  (keep the folders themselves, empty)
- `bootstrap\cache\config.php`, `routes-v7.php`, `events.php` — they contain **PC paths** (`C:\…`) and break the server.
  Easiest: on the PC run `C:\php83\php.exe artisan optimize:clear` **before** zipping.
- `public\storage` (the photo link — it is re-made on the server, section E)

### Export the database (PC)
XAMPP → **phpMyAdmin** (http://localhost/phpmyadmin) → database **`jewellery`** → **Export** → Quick → SQL → **Export**.

### Zip
Right-click the `yoonus` folder → *Send to → Compressed (zipped) folder* → `shop.zip`.

---

## B. Prepare cPanel

1. **PHP version**: cPanel → *MultiPHP Manager* (or *Select PHP Version*) → **PHP 8.3** for the domain.
2. **PHP extensions** (*Select PHP Version → Extensions*), tick: `bcmath, ctype, curl, exif, fileinfo, gd, intl, mbstring, mysqlnd / pdo_mysql, openssl, sodium, tokenizer, xml, zip, opcache`.
3. **PHP options**: `memory_limit 512M`, `upload_max_filesize 40M`, `post_max_size 40M`, `max_execution_time 120`.
4. **Database**: cPanel → *MySQL® Database Wizard* → create a database + user, give the user **ALL PRIVILEGES**.
   Note the full names (cPanel adds a prefix, e.g. `masroor_shop` / `masroor_user`) and the password.
5. **Import**: cPanel → *phpMyAdmin* → choose the new database → **Import** → your `.sql` file → Go.
   (Big file? Zip it first — phpMyAdmin accepts `.sql.zip`.)

---

## C. Upload the files

**Recommended (safest):** put the shop *next to* `public_html`, and point the domain at its `public` folder.

1. cPanel → *File Manager* → go to your home folder (`/home/YOURUSER`) → **Upload** `shop.zip` → **Extract** → you now have `/home/YOURUSER/yoonus`.
2. cPanel → *Domains* → your domain → **Document Root** → change to `yoonus/public` → Save.
   (Older cPanel without this option: use the simple way below.)

**Simple way:** extract the zip **inside `public_html`** so the files are directly in it
(`public_html/app`, `public_html/public`, `public_html/.htaccess`, …).
The included `.htaccess` sends visitors to `public/` and blocks `.env`, code, logs and backups — it's built for this.

Folder permissions (File Manager → right-click → *Change Permissions*): `storage` and `bootstrap/cache` → **775**.

---

## D. Edit `.env` on the server (File Manager → *Edit*; turn on *Show Hidden Files*)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.ae          ← your real domain, with https

DB_CONNECTION=mysql
DB_HOST=localhost                       ← cPanel MySQL is usually "localhost"
DB_PORT=3306
DB_DATABASE=masroor_shop                ← full name from B4
DB_USERNAME=masroor_user
DB_PASSWORD=the-password-from-B4

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database               ← emails/WhatsApp sent in the background by the cron
LOG_LEVEL=warning

MOCK_PAYMENTS=false                     ← no simulated card payments on the live shop
```

Keep **`APP_KEY` exactly as it is** — customers' saved ID numbers are encrypted with it.
A new key = those IDs can no longer be read.

---

## E. First start

1. **SSL**: cPanel → *SSL/TLS Status* → **Run AutoSSL** (free certificate). Open `https://yourdomain.ae`.
2. Sign in to `/admin`.
   - Yellow **"Update database now"** banner? Press it.
   - Dashboard **"Product photos cannot be shown"**? → *Settings → Speed cache → **Repair photo link***.
     (If the host blocks links: cPanel → *Terminal* → `cd yoonus && php artisan storage:link`.)
   - *Settings → Speed cache → **Speed up now***.
3. **Cron job** (needed for gold-rate updates, unpaid-order expiry, reminders, queued emails):
   cPanel → *Cron Jobs* → *Once Per Minute* → command:
   ```
   cd /home/YOURUSER/yoonus && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   (Simple way: `cd /home/YOURUSER/public_html && …`. If `/usr/local/bin/php` is not right, cPanel → *Terminal* → `which php`.)

---

## F. Update the outside services with the new address

| Service | Where | What |
|---|---|---|
| **Email** | Settings → Email | Use your domain mailbox (cPanel → *Email Accounts*), SMTP host `mail.yourdomain.ae`, port 465 SSL — or Resend with your domain verified. Press **Send test email**. |
| **Stripe** | Stripe dashboard → Developers → Webhooks | Endpoint `https://yourdomain.ae/webhooks/stripe`; switch to **live** keys in Settings → Payments. |
| **Mettpay / Telr / Tabby / Tamara** | their merchant portals | Allowed website / return URL = `https://yourdomain.ae`; webhooks `https://yourdomain.ae/webhooks/<gateway>`. |
| **Google sign-in** | Google Cloud → Credentials → OAuth client | Authorised redirect URI `https://yourdomain.ae/auth/google/callback`. |
| **WhatsApp (Meta)** | Meta developer app | Update the domain / webhook if you set one. |
| **Google Search Console / Merchant Center** | Google | Add the domain; submit `https://yourdomain.ae/sitemap.xml` and the feed `https://yourdomain.ae/feed/google.xml`. |
| **Apple Pay** (if used) | your card gateway | Register the new domain. |

---

## G. Check before announcing

- Dashboard **Security check** and **Speed check**: no red items.
- Place a test order end-to-end (card test mode first, then refund/cancel), check the confirmation email and invoice PDF.
- Open a product with photos, the Arabic version (`?lang=ar`), and your phone.
- Settings → Security: turn on **two-step sign-in** for admin.
- cPanel → *Backup* (or JetBackup): make sure daily backups are on (database + `storage/app`).

After this, future updates: upload the update zip into the shop folder → Extract (overwrite) → open `/admin` → press **Update database now** if shown. The speed cache refreshes itself.
