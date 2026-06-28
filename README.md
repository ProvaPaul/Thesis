# Bangla WSD — Annotation Platform

A web-based annotation tool for **Bangla Word Sense Disambiguation (WSD)** research. The platform lets administrators import a sense-tagged sentence dataset, manages annotator accounts, collects per-annotator sense labels, and produces evaluation metrics (accuracy + Cohen's Kappa) for downstream inter-annotator agreement analysis and model training.

---

## 📑 Table of Contents

1. [Features](#-features)
2. [Tech Stack](#-tech-stack)
3. [Project Structure](#-project-structure)
4. [Database Schema](#-database-schema)
5. [Getting Started](#-getting-started)
6. [Admin Workflow](#-admin-workflow)
7. [Annotator Workflow](#-annotator-workflow)
8. [Evaluation Dashboard](#-evaluation-dashboard)
9. [User Details](#-user-details)
10. [Exporting Annotations](#-exporting-annotations)
11. [Python Evaluation Engine (`kappa.py`)](#-python-evaluation-engine-kappapy)
12. [Helper Functions in `config.php`](#-helper-functions-in-configphp)
13. [Security Checklist](#-security-checklist)
14. [Deployment Notes](#-deployment-notes)
15. [Troubleshooting](#-troubleshooting)
16. [Related Artifacts](#-related-artifacts)
17. [License](#-license)

---

## ✨ Features

### Authentication & Roles
- **Role-based access** with separate workflows for `admin` and `annotator` users.
- **Self-service annotator signup** via `register.php` (passwords ≥ 6 chars, bcrypt-hashed).
- **Session regeneration** on successful login to mitigate fixation attacks.

### Admin Tools
- **Admin dashboard** (`dashboard.php`) with:
  - Dataset, annotator, and annotation counts.
  - Overall completion percentage bar.
  - Top-10 sense distribution chart (Chart.js).
  - Recent activity feed (last 8 annotations).
  - FULLTEXT-backed dataset search.
- **Excel dataset import** (`import_dataset.php`, `.xlsx`) via `PhpSpreadsheet` with **SHA-256 duplicate detection** (column order-independent).
- **CSV export** (`export_csv.php`) aligned by sentence for every registered annotator (UTF-8 with BOM, Excel-friendly).
- **Evaluation dashboard** (`evaluation.php`) — see [below](#-evaluation-dashboard).
- **User Details** (`user_details.php`) — see [below](#-user-details).

### Annotation
- **One-sentence-at-a-time UI** (`annotation.php`) with the target word highlighted inside the sentence.
- **All known senses** for the target word are listed in **randomized order** to avoid position bias.
- **Bangla-first UI strings** where they matter (e.g. the sense-selection prompt).
- **Per-annotator progress tracking** with completion percentage bar.
- **Skip / resume** for ambiguous sentences (skipped IDs are kept in the session; the queue resets automatically once finished).

### Cross-cutting
- **Dual-environment config** — `config.php` auto-switches between XAMPP (port `3307`) and InfinityFree based on `HTTP_HOST`.
- **No frontend build step** — vanilla JS + Bootstrap 5 CDN.

## 🧱 Tech Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| Backend | **PHP 8+** with PDO (`utf8mb4`) | Server-side logic, prepared statements |
| Database | **MySQL / MariaDB** | Persistence + FULLTEXT search |
| Frontend | **Bootstrap 5.3** (CDN), **Chart.js** (CDN), vanilla JS | UI + charts |
| Excel parsing | [`phpoffice/phpspreadsheet`](https://github.com/PHPOffice/PhpSpreadsheet) | `.xlsx` import |
| Evaluation engine | **Python 3.10+**, `pandas`, `scikit-learn` (`cohen_kappa_score`) | Cohen's κ + agreement level |
| Inter-process | `shell_exec` from PHP → Python subprocess | Run evaluation |
| Auth | PHP sessions + `password_hash` (bcrypt) | Login / role gating |
| Hosting | XAMPP (development) and InfinityFree (production) | `config.php` auto-switches via `HTTP_HOST` |

## 📁 Project Structure

```
.
├── annotation.php            # Annotator workspace (one sentence at a time)
├── dashboard.php             # Admin dashboard (stats + sense chart + search)
├── evaluation.php            # Admin Evaluation Dashboard (calls /calculate_metrics.php)
├── user_details.php          # Admin user browser (list + per-user detail)
├── import_dataset.php        # .xlsx dataset importer (admin only)
├── export_csv.php            # CSV export of annotations
├── calculate_metrics.php     # JSON endpoint → runs kappa.py, returns metrics
├── submit_annotation.php     # POST handler that saves an annotation
├── login.php                 # Email/password login
├── logout.php                # Session destroy
├── register.php              # Self-service annotator signup
├── config.php                # DB connection, session, helpers
├── composer.json             # PhpSpreadsheet dependency
├── database.sql              # Schema + seed users (XAMPP)
├── database_infinityfree.sql # Schema for InfinityFree
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── final/                    # Trained model artifacts + evaluation engine
    ├── kappa/
    │   └── kappa.py          # Cohen's κ calculator (JSON output)
    ├── notebook1-fixed.ipynb
    ├── notebook2-fixed.ipynb
    └── models/               # Saved model weights
```

## 🗃️ Database Schema

Three tables (see `database.sql`):

### `users`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK, auto-increment | |
| `name` | VARCHAR(120) | |
| `email` | VARCHAR(190), unique | |
| `password` | VARCHAR(255) | bcrypt via `password_hash()` |
| `role` | ENUM(`admin`,`annotator`) | |
| `created_at` | TIMESTAMP | |

### `dataset`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK, auto-increment | |
| `source_row_hash` | CHAR(64), unique | SHA-256 over the row's text fields |
| `word_id` | VARCHAR(64), nullable | |
| `target_word` | VARCHAR(120) | |
| `sense_id` | VARCHAR(64), nullable | |
| `sense_gloss` | TEXT | |
| `text` | TEXT | FULLTEXT indexed |
| `imported_at` | TIMESTAMP | |

### `annotations`
| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK, auto-increment | |
| `sentence_id` | INT, FK → `dataset.id` | ON DELETE CASCADE |
| `annotator_id` | INT, FK → `users.id` | ON DELETE CASCADE |
| `selected_sense` | VARCHAR(64) | sense_id chosen by the annotator |
| `created_at` | TIMESTAMP | |

**Unique constraint:** `(sentence_id, annotator_id)` — each annotator can label a sentence only once. Re-submitting overwrites the row in `submit_annotation.php`.

### Required Excel Columns

The importer expects the following columns (order-independent):

| Column | Required | Description |
|--------|----------|-------------|
| `word_id` | optional | Identifier of the ambiguous word |
| `target_word` | **required** | The Bangla word to be disambiguated |
| `sense_id` | optional | Sense identifier |
| `sense_gloss` | **required** | Gloss / definition for this sense |
| `text` | **required** | The full sentence containing the target word |

## 🚀 Getting Started

### Prerequisites

| Tool | Version | Why |
|------|---------|-----|
| PHP | **8.0+** (tested on 8.2) | Runtime + Composer |
| MySQL / MariaDB | 5.7+ / 10.3+ | Persistence |
| Composer | 2.x | Pulls in PhpSpreadsheet |
| Python *(only for the Evaluation Dashboard)* | **3.10+** with `pandas` + `scikit-learn` | Runs `kappa.py` |
| Web server | XAMPP **or** the PHP built-in dev server | Hosting |

### 1. Install PHP dependencies

```bash
composer install
```

### 2. Set up the database

- Start MySQL (XAMPP Control Panel → **Start MySQL**).
- Open phpMyAdmin and import `database.sql`.

This creates the `bangla_wsd` database, all tables, and two seed accounts.

### 3. Configure `config.php`

`config.php` already switches between **XAMPP** (default port `3307`) and **InfinityFree** automatically based on `HTTP_HOST`. Adjust credentials / ports if your local setup differs:

```php
$host = 'localhost';
$dbname = 'bangla_wsd';
$username = 'root';
$password = '';
$port = '3307';
```

### 4. Run the app

#### Option A — XAMPP (recommended)

Place the project in XAMPP's `htdocs` (this repo already is) and open:

```
http://localhost/WSD/login.php
```

#### Option B — PHP built-in dev server (no XAMPP needed)

```bash
php -S 127.0.0.1:8000 -t .
```

Then open `http://127.0.0.1:8000/login.php`.

### 5. Seed accounts

Both seed users share the default password **`password`** (per the login page hint).

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `password` |
| Annotator | `annotator@example.com` | `password` |

Annotators can also self-register via `register.php`.

### 6. Import a dataset

Sign in as admin → **Import Dataset** → upload your `.xlsx` file. Duplicate rows (same `source_row_hash`) are skipped automatically.

### 7. (Optional) Enable the Evaluation Dashboard

```bash
pip install pandas scikit-learn
```

`calculate_metrics.php` will probe for `python`, `python3`, or `py` and surface a friendly error if Python is missing or the dependencies aren't installed.

## 🧑‍💼 Admin Workflow

After signing in as an admin, the sidebar exposes four tools:

| Tool | File | What it does |
|------|------|--------------|
| **Dashboard** | `dashboard.php` | Dataset + annotator stats, sense-distribution chart, recent activity, FULLTEXT search |
| **Import Dataset** | `import_dataset.php` | Upload an `.xlsx` file; rows are de-duplicated via SHA-256 |
| **Export CSV** | `export_csv.php` | Download a per-annotator CSV (UTF-8 with BOM) |
| **Evaluation** | `evaluation.php` | Compute Cohen's κ between every pair of annotators |
| **User Details** | `user_details.php` | Browse annotators and inspect their progress + last 20 labels |

Typical end-to-end flow:

1. **Import** a sense-tagged `.xlsx` dataset.
2. Have annotators register (or seed `annotator@example.com`) and label sentences.
3. Open **User Details** to monitor progress per annotator.
4. Once at least two annotators have labelled a shared subset, open **Evaluation** and click **Calculate Metrics** to see Cohen's κ per pair and the overall average.
5. **Export** the CSV when you're ready to train a model offline.

## 🧑‍🏫 Annotation Workflow

1. Annotator logs in → lands on `annotation.php`.
2. The next **unannotated** sentence is shown with the target word highlighted.
3. All distinct senses (glosses) for that target word are listed in randomized order.
4. Annotator picks a sense → submits → next sentence loads automatically.
5. The `Skip` button defers a sentence to the end of the queue (session-scoped).
6. When all sentences are labeled, the UI shows the completion screen.

Progress (`Completed X / Y`) is shown in the top bar at all times.

## 📤 Exporting Annotations

`export_csv.php` (admin-only) downloads `wsd_annotations.csv` with columns:

```
sentence_id, target_word, sentence, <annotator_1>, <annotator_2>, ...
```

- One column per registered annotator.
- UTF-8 with **BOM** so it opens cleanly in Excel.
- Rows are aligned by `sentence_id`; missing labels are blank cells (not `NaN`).
- The Evaluation Dashboard (`evaluation.php`) builds the **same shape in memory** and pipes it to `kappa.py` — you can re-run evaluation offline by exporting and feeding the file directly to `kappa.py --csv path/to/file.csv`.

## 🐍 Python Evaluation Engine (`final/kappa/kappa.py`)

`kappa.py` reads an annotations CSV (one column per annotator + `sentence_id`, `target_word`, `sentence`) and emits a single JSON object on **stdout**.

### CLI usage

```bash
python final/kappa/kappa.py wsd_annotations.csv > metrics.json
# or, with no args, read wsd_annotations.csv from the current directory:
python final/kappa/kappa.py
```

### JSON shape

```jsonc
{
  "annotators": ["amit", "Arefin Nahid"],
  "total_sentences": 120,
  "pairwise": {
    "amit_vs_Arefin Nahid": {
      "kappa": 0.9264,
      "agreement_level": "Almost Perfect",
      "n_compared": 120
    }
  },
  "average_kappa": 0.9264,
  "overall_agreement": "Almost Perfect",
  "summary": "Cohen's kappa across 1 pair(s), mean κ = 0.9264 (Almost Perfect)."
}
```

### Agreement level mapping (Landis & Koch, 1977)

| κ range | Label |
|---------|-------|
| `< 0.00` | Poor |
| `0.00 – 0.20` | Slight |
| `0.21 – 0.40` | Fair |
| `0.41 – 0.60` | Moderate |
| `0.61 – 0.80` | Substantial |
| `0.81 – 1.00` | Almost Perfect |

### Robustness details

- NaN values from `sklearn.metrics.cohen_kappa_score` are normalized to `null` in the JSON (`json.dumps(..., allow_nan=False)`).
- A single annotator → `average_kappa: null`, `overall_agreement: "N/A"`.
- `n_compared` reflects the number of sentences both annotators labelled (the intersection).
- Sentences where **either** annotator left a blank cell are dropped from that pairwise comparison.

## 📊 Evaluation Dashboard

`evaluation.php` is an admin-only page that runs `kappa.py` over the live database and renders the result with Bootstrap cards, a per-annotator table, progress bars, and color-coded agreement pills.

### Endpoint contract

| Property | Value |
|----------|-------|
| Method | `GET` (admin session cookie required) |
| URL | `/calculate_metrics.php` |
| Content-Type | `application/json; charset=utf-8` |
| Side effects | None (read-only) |

### Success response

```json
{
  "ok": true,
  "metrics": { /* full kappa.py output, see above */ },
  "stderr": "",
  "python": "C:\\Python311\\python.exe",
  "csv_rows": 120
}
```

### Error shapes

| Condition | HTTP | `ok` | Extra fields |
|-----------|------|------|--------------|
| Not logged in / not admin | `403` | `false` | `error: "Forbidden"` |
| Python interpreter missing | `200` | `false` | `error: "Python is not installed on the server."`, `hosted: true|false` |
| `kappa.py` crashes | `200` | `false` | `error`, `exit_code`, `stderr` |
| No annotators with overlapping labels | `200` | `true` | `metrics.overall_agreement: "N/A"` (UI shows a friendly empty state) |

The page is robust on hosted environments: if `shell_exec` is disabled or Python is missing, the UI shows actionable guidance instead of breaking.

## 👤 User Details

`user_details.php` is an admin-only browser for annotators. Two views:

### List view (default)

Four summary cards on top (total annotators, completed, in-progress, average completion), then a responsive table with one row per non-admin user:

| Column | Notes |
|--------|-------|
| ID | |
| Name | |
| Email | |
| Status | `Completed` (green) or `In Progress` (yellow) |
| Submitted | Number of labels the annotator has saved |
| Completion | `% of dataset labelled` |
| Last submission | `YYYY-MM-DD HH:MM` or `—` |
| Actions | **View Details** button → detail view |

### Detail view (`?id=N`)

Three cards:

1. **Profile** — name, email, role, joined-on.
2. **Statistics** — submissions, completion %, last submission.
3. **Last 20 annotations** — table JOINed against `dataset` showing target word, sense chosen, sentence, and timestamp.

### Caching & joins

The list view runs a single grouped SQL aggregation against `annotations` (no N+1); the detail view runs one extra query per user (the JOIN for the last 20 rows).

## 🛠️ Helper Functions (`config.php`)

| Function | Purpose |
|----------|---------|
| `db()` | Lazy PDO singleton with utf8mb4 + safe prepares |
| `e($value)` | HTML-escape for safe output |
| `current_user()` | Fetch the logged-in user (or `null`) |
| `require_login()` / `require_role($role)` | Guard helpers; redirect on failure |
| `highlight_target($text, $w)` | Wrap the target word in `<mark>` (first occurrence) |
| `flash($type, $msg)` / `consume_flash()` | One-shot flash messages |

## 🔐 Security Checklist

- ✅ All queries use **prepared statements** (PDO with `ATTR_EMULATE_PREPARES => false`).
- ✅ Passwords are stored with **bcrypt** (`password_hash` / `password_verify`).
- ✅ `session_regenerate_id(true)` runs on successful login.
- ✅ All output is escaped via `e()`.
- ✅ Admin pages call `require_role('admin')` before rendering.
- ⚠️ `shell_exec` is used to launch Python — disable it on production if you don't need the Evaluation Dashboard (`disable_functions = shell_exec` in `php.ini`).
- ⚠️ If you deploy to InfinityFree, rotate the credentials in `config.php` before sharing the repo.

## 🚢 Deployment Notes

| Environment | DB port | DB host | Notes |
|-------------|---------|---------|-------|
| **XAMPP (local)** | `3307` | `localhost` | Default in `config.php` |
| **InfinityFree (hosted)** | `3306` | `sql*.infinityfree.com` | Set credentials in the `if ($_SERVER['HTTP_HOST'] !== 'localhost')` branch of `config.php` |

- `database_infinityfree.sql` is a variant prepared for the hosted MySQL.
- Sessions are default file-based — fine for small-scale annotation work. Switch to a DB-backed session handler before going to a multi-server setup.
- The dev build never modifies the `dataset` table from the annotator side; only admins import into it.

## 🛠️ Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Login page shows "Database connection failed" | Wrong port or credentials in `config.php` | Verify `$port` (3307 on XAMPP, 3306 elsewhere) and re-test via `php -r "require 'config.php'; var_dump(db());"` |
| Importing `.xlsx` silently skips every row | All `source_row_hash` values already exist | Drop duplicates in the spreadsheet, or clear the `dataset` table |
| Evaluation shows "Python is not installed on the server" | Missing interpreter on hosted env | Install Python 3.10+ + `pandas scikit-learn`, or run `kappa.py` offline with the exported CSV |
| `evaluation.php` returns `403 Forbidden` | Cookie expired or non-admin session | Re-login as `admin@example.com` |
| User Details list shows `0 submitted` for everyone | Annotators have only used Skip | Skip does not persist — labels only count after a sense is chosen |
| `composer install` fails | PHP version < 8.0 | Upgrade PHP, or pin to `phpoffice/phpspreadsheet ^1.25` |

## 📚 Related Artifacts

The `final/` directory contains trained model notebooks and weights produced from this dataset (see `notebook1-fixed.ipynb`, `notebook2-fixed.ipynb`, and `models/`). These are not required to run the annotation platform.

## 📄 License

Internal thesis/research project. Add an explicit license before public distribution.