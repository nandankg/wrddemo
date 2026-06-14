# Deploying to Hostinger (shared hosting / hPanel)

The demo is plain **PHP 8.2 + MySQL**; all front-end libraries load from CDNs.
The installer (`setup.php`) is written for shared hosting — it connects to an
**existing** database (it does not run `CREATE DATABASE`).

---

## First-time setup

1. **Create the database** — hPanel → Databases → *MySQL Databases*. Create a
   database + user and assign the user to it. Note the DB name, user, password
   (Hostinger prefixes them, e.g. `u123456789_wrd` / `u123456789_admin`). Host is
   usually `localhost`.

2. **Set credentials (once, never committed)** — copy the template and fill it in
   **on the server**:
   ```
   cp config/config.local.php.example config/config.local.php
   # then edit config/config.local.php with your DB host/name/user/pass
   ```
   `config/config.local.php` is gitignored, so deploys never overwrite it. You do
   **not** need to edit `config/config.php`.

3. **Upload the code** to `public_html` (pick one):
   - **Git (recommended):** hPanel → Advanced → *Git* → repo
     `https://github.com/nandankg/wrddemo.git`, branch `main`, deploy into `public_html`.
   - **File Manager / SFTP:** upload the repo contents.

4. **PHP version** — hPanel → Advanced → *PHP Configuration* → select **8.1 or 8.2**.

5. **Install the database** — visit `https://yourdomain/setup.php` once. It creates
   all 17 tables + seed data. Then open `https://yourdomain/`.
   All demo logins use password **`demo123`**.

6. **Remove the installer** — delete (or rename) `setup.php` afterward; it is
   destructive (drops & re-seeds) and must not be publicly reachable.

---

## Deploying changes (updates)

1. **Get the new code:**
   - Git: `git push` is already done upstream → in hPanel → Git click **Deploy**
     (or `git pull` on the server).
   - File Manager/SFTP: re-upload changed files. Do **not** upload
     `config/config.local.php` (keep the server's).

   Because `config/config.local.php` is separate and gitignored, your credentials
   are untouched by the deploy.

2. **Re-run `setup.php` ONLY if the database schema changed** (new tables/columns/
   seed users). Upload `setup.php`, visit `https://yourdomain/setup.php`, then
   delete it again. ⚠️ This resets demo data to a clean state.

   > Schema-affecting changes so far include: `progress_updates` table (PPMS),
   > `contractors.login_user` column + `ACCOUNTS` user (Contractor / E-Tariff).
   > After pulling those for the first time, re-run `setup.php` once.

3. **If a change doesn't appear**, clear the LiteSpeed/OPcache cache in hPanel.

---

## Notes

- **Stack:** this is the demo build (PHP/MySQL). The production stack in the RFP is
  React 18 + TypeScript + PostgreSQL 16 — present that in the architecture slides.
- **Unbuilt products:** Industrial Water Allocation and Departmental Website + CMS
  are not built yet, so their launcher cards 404. The other four (PPMS, E-Tariff,
  Contractor) + the launcher are fully testable.
- **`config/config.php` precedence:** it loads `config/config.local.php` first (if
  present), then only fills in any DB_* constant that wasn't already defined.
