# ILAAJ CRM — Patient Advisor & Writer Management System

PHP + MySQL application for patient conversations, meetings, Excel import, and a read-only Ameer Sahab advisor view.

## Google Drive images

Uploads use **OAuth + refresh token**. All secrets go in `.env` (works the same on deploy).

Editor → **Drive Setup**, or see [docs/GOOGLE_DRIVE_SETUP.md](docs/GOOGLE_DRIVE_SETUP.md).

## Requirements

- PHP 8.0+ (PDO MySQL extension)
- MySQL 5.7+ / MariaDB 10.3+
- Composer (for PhpSpreadsheet Excel support)
- Apache or PHP built-in server

## Setup

1. **Copy env** — `.env.example` → `.env` in the project root (next to `public_html`, not inside it). Set database and auth values.

2. **Import schema** (creates database, tables, demo data):

```bash
mysql -u root -p < database/schema.sql
```

3. **Install PHP dependencies** from the project root (creates `vendor/` next to `public_html`):

```bash
composer install
```

4. **Run the app**:

```bash
php -S localhost:8080 -t public_html
```

Open http://localhost:8080

On XAMPP, open `http://localhost/SEW-International-ILAAJ-CRM/` — requests are rewritten into `public_html`.

## Namecheap / cPanel deploy

Upload so the account home matches this repo layout. `public_html` is the domain document root; `vendor`, `uploads`, and `.env` stay one level above it (not publicly downloadable).

```
/home/USERNAME/
  public_html/     website files
  vendor/          composer packages
  uploads/         temp import files
  composer.json
  .env
  database/
  scripts/
```

On the server, run `composer install` from `/home/USERNAME/` (the folder that contains `composer.json`). Set `GOOGLE_OAUTH_REDIRECT_URI` to `https://your-domain.com/api/google_oauth.php`.

## Roles (no login)

| Role | Access |
|------|--------|
| **Editor** | Full CRUD: patients, messages, images, workers, meetings, Excel import, dashboard, forces Ameer Sahab’s current patient |
| **Ameer Sahab** | Read-only patients, conversations, gallery; polls for Editor-forced patient |

## Architecture

- Conversations are stored as individual rows in `messages` (`sender_type`: `patient` | `ameer_sahab`)
- Images store **Google Drive URLs** in `patient_images` (not binary files)
- Phone numbers are **not unique**
- Editor → Ameer sync uses `system_state.active_patient_id` + AJAX polling (~2.5s)
- Excel import normalizes alternating response columns into messages; historical dates are not invented

## Excel / CSV columns (expected)

`Sr No`, `Date`, `Patient Name`, `Patient's Mother's Name`, `Number`, `Remarks`, `Country`, `City`, `Occupation`, `Details of Concern`, then alternating `Ameer Sahab Response` / `Followup Remarks` (patient), any count.

- **Date** → date of the **last** message only (previous dates unknown → —)
- **Details of Concern** → first patient message
- Then **Ameer** → **patient** → **Ameer** → … in column order
- Followup Date columns in old sheets are ignored
- New messages added in the app can include dates normally

CSV is supported. Excel `.xlsx` / `.xls` requires PhpSpreadsheet (`composer install`).

## Project structure

```
public_html/           Namecheap web root
  api/                 JSON endpoints
  assets/              CSS & JavaScript
  config/              Database config
  includes/            Bootstrap, auth, layout
  lib/                 Repositories & Excel importer
  pages/               Editor & Advisor UI
  index.php            Role selection landing
vendor/                Composer packages (not public)
uploads/temp/          Temporary upload storage (not public)
composer.json
.env                   Secrets (not public)
database/              schema.sql
scripts/               CLI helpers
```

## Patient deletion

Requires typing exactly: `DELETE THIS PATIENT` (case-sensitive). Uses a DB transaction and cascading foreign keys.
