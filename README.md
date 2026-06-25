# Bangla WSD — Annotation Platform

A web-based annotation tool for **Bangla Word Sense Disambiguation (WSD)** research. The platform lets administrators import a sense-tagged sentence dataset, manages annotator accounts, and collects per-annotator sense labels that can later be exported for inter-annotator agreement analysis and model training.

## ✨ Features

- **Role-based access** — separate workflows for `admin` and `annotator` users.
- **Admin dashboard** with dataset statistics, sense-distribution chart (Chart.js), recent activity, and dataset search.
- **Excel dataset import** (`.xlsx`) via `PhpSpreadsheet` with duplicate detection (SHA-256 row hash).
- **One-sentence-at-a-time annotation UI** — the target word is highlighted inside the sentence and all known senses for that word are shown (in randomized order).
- **Per-annotator progress tracking** with a completion percentage bar.
- **Skip / resume** support for ambiguous sentences (skipped IDs are kept in the session).
- **CSV export** of annotations aligned by sentence for up to two annotators (UTF-8 with BOM, Excel-friendly).
- **Bangla-first UI strings** where they matter (e.g. the sense-selection prompt).
- **Demo deployment** on both XAMPP (local) and InfinityFree (hosted) — `config.php` auto-switches based on `HTTP_HOST`.

## 🧱 Tech Stack

- **Backend:** PHP 8+ (PDO with `utf8mb4`)
- **Database:** MySQL / MariaDB
- **Frontend:** Bootstrap 5.3, Chart.js, vanilla JS
- **Libraries:** [`phpoffice/phpspreadsheet`](https://github.com/PHPOffice/PhpSpreadsheet) for Excel parsing
- **Hosting:** XAMPP (development) and InfinityFree (production)

## 📁 Project Structure

```
.
├── annotation.php          # Annotator workspace (one sentence at a time)
├── dashboard.php           # Admin dashboard with stats & search
├── export_csv.php          # CSV export of annotations
├── import_dataset.php      # .xlsx dataset importer (admin only)
├── login.php               # Email/password login
├── logout.php              # Session destroy
├── register.php            # Self-service annotator signup
├── submit_annotation.php   # POST handler that saves an annotation
├── config.php              # DB connection, session, helpers
├── composer.json           # PhpSpreadsheet dependency
├── database.sql            # Schema + seed users
├── database_infinityfree.sql
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── final/                  # Trained model artifacts (notebook, model files)
```

## 🗃️ Database Schema

Three tables (see `database.sql`):

- `users` — `id`, `name`, `email`, `password` (bcrypt), `role` (`admin` / `annotator`), `created_at`.
- `dataset` — `id`, `source_row_hash` (unique SHA-256), `word_id`, `target_word`, `sense_id`, `sense_gloss`, `text`, `imported_at` (with FULLTEXT index for search).
- `annotations` — `id`, `sentence_id` (FK), `annotator_id` (FK), `selected_sense`, `created_at`. Unique constraint on `(sentence_id, annotator_id)` so each annotator can label a sentence only once.

### Required Excel Columns

The importer expects the following columns (order-independent):

| Column        | Description                                              |
|---------------|----------------------------------------------------------|
| `word_id`     | Identifier of the ambiguous word (optional)              |
| `target_word` | The Bangla word to be disambiguated                     |
| `sense_id`    | Sense identifier (optional)                             |
| `sense_gloss` | Gloss / definition for this sense                        |
| `text`        | The full sentence containing the target word             |

## 🚀 Getting Started

### 1. Install dependencies

```bash
composer install
```

### 2. Set up the database

- Start MySQL (XAMPP Control Panel → Start MySQL).
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

Place the project in XAMPP's `htdocs` (this repo already is) and open:

```
http://localhost/WSD/login.php
```

### 5. Seed accounts

Both seed users share the default password **`password`** (per the login page hint).

| Role      | Email                  |
|-----------|------------------------|
| Admin     | `admin@example.com`    |
| Annotator | `annotator@example.com`|

Annotators can also self-register via `register.php`.

### 6. Import a dataset

Sign in as admin → **Import Dataset** → upload your `.xlsx` file. Duplicate rows (same `source_row_hash`) are skipped automatically.

## 🧑‍🏫 Annotation Workflow

1. Annotator logs in → lands on `annotation.php`.
2. The next **unannotated** sentence is shown with the target word highlighted.
3. All distinct senses (glosses) for that target word are listed in randomized order.
4. Annotator picks a sense → submits → next sentence loads automatically.
5. The `Skip` button defers a sentence to the end of the queue (session-scoped).
6. When all sentences are labeled, the UI shows the completion screen.

Progress (`Completed X / Y`) is shown in the top bar at all times.

## 📤 Exporting Annotations

`export_csv.php` (admin-only) produces `wsd_annotations.csv` with columns:

```
sentence_id, target_word, sentence, annotator_1, annotator_2
```

The CSV is UTF-8 with BOM, so it opens cleanly in Excel. It aligns labels from the first two registered annotators — useful for inter-annotator agreement studies.

## 🛠️ Helper Functions (`config.php`)

| Function                       | Purpose                                              |
|--------------------------------|------------------------------------------------------|
| `db()`                         | Lazy PDO singleton with utf8mb4 + safe prepares     |
| `e($value)`                    | HTML-escape for safe output                         |
| `current_user()`               | Fetch the logged-in user (or `null`)                |
| `require_login()` / `require_role($role)` | Guard helpers; redirect on failure    |
| `highlight_target($text, $w)`  | Wrap the target word in `<mark>` (first occurrence) |
| `flash($type, $msg)` / `consume_flash()` | One-shot flash messages                  |

## 🚢 Deployment Notes

- `database_infinityfree.sql` is a variant prepared for the InfinityFree hosted MySQL.
- Sensitive credentials in `config.php` (InfinityFree block) should be moved to environment variables before going to a public repo.
- Sessions are not stored — they're default file-based sessions, fine for small-scale annotation work.

## 📚 Related Artifacts

The `final/` directory contains trained model notebooks and weights produced from this dataset (see `notebook1-fixed.ipynb`, `notebook2-fixed.ipynb`, and `models/`). These are not required to run the annotation platform.

## 📄 License

Internal thesis/research project. Add an explicit license before public distribution.