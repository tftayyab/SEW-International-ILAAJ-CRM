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

1. **Configure database** in `config/database.php` (host, database name, user, password).

2. **Import schema** (creates database, tables, demo data):

```bash
mysql -u root -p < database/schema.sql
```

3. **Install PHP dependencies**:

```bash
composer install
```

4. **Run the app**:

```bash
php -S localhost:8080
```

Open http://localhost:8080

Or place the project under your Apache/Nginx document root.

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
api/           JSON endpoints
assets/        CSS & JavaScript
config/        Database config
database/      schema.sql
includes/      Bootstrap, auth, layout
lib/           Repositories & Excel importer
pages/         Editor & Advisor UI
uploads/temp/  Temporary upload storage
index.php      Role selection landing
```

## Patient deletion

Requires typing exactly: `DELETE THIS PATIENT` (case-sensitive). Uses a DB transaction and cascading foreign keys.
