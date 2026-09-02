# Deployment & updates

Small CI4 + MariaDB app. Two environments:

| | where | data |
|---|---|---|
| **local** | your PC (XAMPP) | throwaway — safe to break |
| **production** | the host | the real company data |

Same code, different `.env`. Schema changes reach production through **migrations**
(`php spark migrate`), never by editing the DB by hand.

---

## The update loop (day to day)

```
edit + test locally
  → git add / git commit
  → git push            (to github.com/luixbm/accounting)
  → on the server:  git pull  &&  php spark migrate
```

That's it. New columns/tables come from the migration files you write; the app
code is just the pulled files. Roll back a bad deploy with
`git reset --hard <previous-commit> && php spark migrate:rollback` (only if the
last migration is reversible — most here are).

---

## First-time deploy — cPanel shared hosting (recommended)

Any provider with cPanel + Git + MariaDB + SSH/Terminal (Domainesia, Niagahoster,
Rumahweb, …).

1. **DNS** — point a subdomain (e.g. `acc.your-domain.com`) at the host.

2. **Database** (cPanel → MySQL® Databases)
   - create a database, a user, add the user to the database with *all privileges*
   - note the db name / user / password (they get a `cpaneluser_` prefix)

3. **Get the code** (cPanel → Git™ Version Control → Create)
   - Clone URL: `https://github.com/luixbm/accounting.git`
   - Repository path: e.g. `/home/cpaneluser/repos/accounting`
   - (private repo → use a GitHub deploy key or a PAT in the URL)

4. **Point the subdomain's document root at `public/`**
   cPanel → Domains → the subdomain → Document Root =
   `/home/cpaneluser/repos/accounting/public`

5. **Composer** (cPanel → Terminal)
   ```
   cd ~/repos/accounting
   composer install --no-dev --optimize-autoloader
   ```

6. **`.env`**
   ```
   cp .env.production.example .env
   nano .env          # fill db name / user / password, app.baseURL
   php spark key:generate     # writes a fresh encryption.key into .env
   ```

7. **Migrate + seed**
   ```
   php spark migrate --all
   php spark db:seed InitialSeeder        # ONLY on a brand-new empty DB
   ```
   If you are moving the *existing* HTI data, instead: `mysqldump` the local DB,
   import it into the new one (cPanel → phpMyAdmin), then just `php spark migrate`
   to apply anything newer.

8. **Permissions** — `writable/` must be writable by the web user
   ```
   find writable -type d -exec chmod 775 {} \;
   ```

9. **First login** — `admin@example.com` / `admin12345` → **change it immediately**
   ```
   php spark shield:user changepassword admin@example.com
   ```
   (or create your own admin and delete the seed one)

10. **HTTPS** — cPanel → SSL/TLS Status → run AutoSSL for the subdomain.
    `.env` already has `app.forceGlobalSecureRequests = true`.

11. **Smoke test** — log in, open a few reports, post a test journal, void it.
    Check `writable/logs/` for errors.

---

## First-time deploy — VPS (if you outgrow shared hosting)

Ubuntu + Nginx + PHP 8.2-FPM + MariaDB.

```
sudo apt install nginx mariadb-server php8.2-fpm php8.2-{mysql,intl,mbstring,xml,curl,gd,zip}
git clone https://github.com/luixbm/accounting.git /var/www/accounting
cd /var/www/accounting && composer install --no-dev --optimize-autoloader
cp .env.production.example .env && nano .env && php spark key:generate
mysql -e "CREATE DATABASE acc CHARACTER SET utf8mb4; CREATE USER 'acc'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON acc.* TO 'acc'@'localhost';"
php spark migrate --all
chown -R www-data:www-data writable
```

Nginx server block: `root /var/www/accounting/public;` and the standard CI4
`try_files $uri $uri/ /index.php?$query_string;` + a `fastcgi_pass` to
`php8.2-fpm.sock`. Certbot for TLS.

---

## Backups (do this before you rely on it)

- **cPanel**: enable daily backups, or a cron:
  ```
  0 2 * * *  mysqldump -u USER -pPASS DBNAME | gzip > ~/backups/acc-$(date +\%F).sql.gz
  ```
- Keep 14–30 days. Test a restore once.

---

## Production checklist (already done in the repo)

- [x] `app.csrfProtection` flag — CSRF is on when `.env` sets it (`= true` in the prod template); the API stays excluded. Off locally so dev is unaffected.
- [x] `writable/` skeleton committed with `.gitkeep`; runtime files stay ignored.
- [x] Debug toolbar / detailed errors auto-off when `CI_ENVIRONMENT = production`.
- [ ] Change the `admin12345` password (step 9).
- [ ] Fresh `encryption.key` on the server (step 6) — never the dev key.
- [ ] AutoSSL / Certbot (step 10).
- [ ] Backups (above).

## Notes

- `composer.lock` **is** committed — the server installs the exact same versions.
- Never run `php spark db:seed` against a database that already has data.
- After each `git pull` on the server: `composer install --no-dev` (only if
  `composer.lock` changed) then `php spark migrate`.
