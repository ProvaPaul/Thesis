# Bangla WSD — Annotation Platform, Research Benchmark & Thesis

A three-part **Bangla Word Sense Disambiguation (WSD)** project, developed as a thesis at the Dept. of Computer Science and Engineering, Khulna University of Engineering & Technology (KUET):

1. **Annotation Platform** — a PHP/MySQL web application for importing sense-tagged datasets, managing annotator accounts, collecting per-annotator sense labels, and computing inter-annotator agreement (Cohen's κ). Used to build the corpus described below.
2. **Research Benchmark** — a large-scale Python/PyTorch study comparing lexical, static-embedding, and transformer representations across classical, recurrent, and fine-tuned classifiers, packaged with a live **Gradio demo app** for interactive sense prediction.
3. **Thesis Paper** — *"Bangla Word Sense Disambiguation: A Large-Scale Benchmark of Embedding and Classifier Combinations"*, the written academic report of the final, published benchmark configuration.

> **Authors:** Prova Rani Paul, Dr. K. M. Azharul Hasan
> **Affiliation:** Dept. of Computer Science and Engineering, Khulna University of Engineering & Technology (KUET), Khulna, Bangladesh
> **Contact:** provapaul123@gmail.com

> **⚠️ A note on numbers in this repo.** The repository contains two related but distinct experimental snapshots:
> - The **thesis paper** reports the *final, published* benchmark: 12,059 sentences, 402 target words, 1,048 word–sense classes, and **29 experiments** (best result: fine-tuned BanglaBERT at **89.72%** accuracy).
> - The **shipped notebook and Gradio demo app** in `final/` reflect an *earlier/working* experimental configuration over a smaller filtered subset: 7,409 sentences, 181 target words, 657 word-sense classes, and **44 experiments** (best result: fine-tuned BanglaBERT at **87.45%** accuracy).
>
> Both are kept in this README, clearly labeled, rather than merged into one set of numbers — treat the **paper's numbers as the citable, official results**, and the **notebook/demo's numbers as what you'll actually see if you run the code in this repo today**.

---

## 📑 Table of Contents

1. [Motivation](#-motivation)
2. [Features](#-features)
3. [Tech Stack](#-tech-stack)
4. [Project Structure](#-project-structure)
5. [Database Schema](#-database-schema)
6. [Getting Started — Annotation Platform](#-getting-started--annotation-platform)
7. [Admin Workflow](#-admin-workflow)
8. [Annotator Workflow](#-annotator-workflow)
9. [Evaluation Dashboard](#-evaluation-dashboard)
10. [User Details](#-user-details)
11. [Exporting Annotations](#-exporting-annotations)
12. [Python Evaluation Engine (`kappa.py`)](#-python-evaluation-engine-kappapy)
13. [The Corpus](#-the-corpus)
14. [Research Benchmark — Methodology](#-research-benchmark--methodology)
15. [Results — Thesis Paper (Official, 29 Experiments)](#-results--thesis-paper-official-29-experiments)
16. [Results — Shipped Notebook (44 Experiments)](#-results--shipped-notebook-44-experiments)
17. [Key Findings](#-key-findings)
18. [Comparison with Prior Bangla WSD Work](#-comparison-with-prior-bangla-wsd-work)
19. [Live Demo App (Gradio)](#-live-demo-app-gradio)
20. [Limitations](#-limitations)
21. [Future Work](#-future-work)
22. [Helper Functions in `config.php`](#-helper-functions-in-configphp)
23. [Security Checklist](#-security-checklist)
24. [Deployment Notes](#-deployment-notes)
25. [Troubleshooting](#-troubleshooting)
26. [Related Artifacts](#-related-artifacts)
27. [Citation](#-citation)
28. [Acknowledgments](#-acknowledgments)
29. [License](#-license)
30. [Contact](#-contact)

---

## 🎯 Motivation

Word Sense Disambiguation is the task of selecting the correct sense of an ambiguous word given its context. Bangla, spoken by roughly 300 million people, is rich in polysemy (e.g. জল / *jal* can mean water, rain, tears, or exhausting toil), but prior Bangla WSD research relied on small, private datasets and hand-engineered features — no study had fine-tuned a language model for this task before this work. The five prior published studies evaluate on between 160 and 3,860 sentences, none uses a learned embedding, none fine-tunes a transformer, and none reports inter-annotator reliability.

This project closes those gaps with:

- **A custom-built annotation platform** (this repo) to collect reliable, reliability-checked sense labels at scale.
- **A new large-scale, human-annotated Bangla WSD corpus** — up to 12,059 sentences covering 402 ambiguous target words with 1,085 distinct sense glosses, the largest Bangla WSD corpus reported to date and the only one with quantified inter-annotator reliability (Cohen's κ = 0.836 / 0.874, "almost perfect").
- **A controlled, large-scale benchmark** — from lexical baselines to fully fine-tuned transformers, sharing one fixed data split, one preprocessing pipeline, and one evaluation suite.
- **An interactive demo app** to make the trained models tangible and testable.

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
- **Evaluation dashboard** (`evaluation.php`) — computes Cohen's κ between annotator pairs; see [below](#-evaluation-dashboard).
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

### Research Benchmark (`final/bangla-wsd-final.ipynb`)
- Fully automated experiment grid across embeddings (TF-IDF, Word2Vec, fastText, frozen mBERT/XLM-R/BanglaBERT) and classifiers (Naive Bayes, Logistic Regression, Linear SVM, LSTM, GRU, BiLSTM, BiGRU) plus end-to-end fine-tuned transformers (mBERT, XLM-R, BanglaBERT).
- **Word-specific label construction** so the same gloss text is never confused across different target words.
- **Stratified 70/15/15 train/validation/test split** with disk-cached embeddings to avoid recomputation.
- **Full metrics suite per experiment**: accuracy, macro/weighted precision-recall-F1, balanced accuracy, MCC, Cohen's κ, ROC-AUC, training/inference timing, parameter counts, model size, GPU/CPU memory.
- **Target-word-constrained evaluation** — an inference-time restriction of predictions to a word's own valid senses, isolating cross-word errors from true within-word sense confusions.
- **Publication-ready exports**: styled comparison table, bar charts, an all-metrics heatmap, and results in CSV/Excel/Markdown/LaTeX/Word formats.

### Live Demo App (`final/wsd_demo_app/`)
- **Gradio web UI** for side-by-side, real-time inference with the fine-tuned transformer models.
- **Confidence charts + top-5 sense breakdown** per model, plus one-click example inputs pulled from the real dataset.
- **Sense-restricted softmax** — predictions are limited to the target word's own valid senses, mirroring the training notebook exactly.
- **Full benchmark table & chart** available in-app for context (Benchmark Results tab).

---

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
| ML research | **PyTorch**, `transformers` (HuggingFace), `scikit-learn`, Word2Vec/fastText | Embedding + classifier benchmark |
| Live demo | **Gradio 4.x**, `matplotlib` | Interactive inference UI |

---

## 📁 Project Structure

```
.
├── annotation.php                    # Annotator workspace (one sentence at a time)
├── dashboard.php                     # Admin dashboard (stats + sense chart + search)
├── evaluation.php                    # Admin Evaluation Dashboard (calls /calculate_metrics.php)
├── user_details.php                  # Admin user browser (list + per-user detail)
├── import_dataset.php                # .xlsx dataset importer (admin only)
├── export_csv.php                    # CSV export of annotations
├── calculate_metrics.php             # JSON endpoint → runs kappa.py, returns metrics
├── submit_annotation.php             # POST handler that saves an annotation
├── login.php                         # Email/password login
├── logout.php                        # Session destroy
├── register.php                      # Self-service annotator signup
├── config.php                        # DB connection, session, helpers
├── composer.json                     # PhpSpreadsheet dependency
├── database.sql                      # Schema + seed users (XAMPP)
├── database_infinityfree.sql         # Schema for InfinityFree
├── wsd_bangla.sql                    # Full production data dump (InfinityFree export)
├── dataset_updated.xlsx              # Canonical labeled dataset used by the notebook + demo app
├── peek_nb.py                        # CLI utility: lists Jupyter notebook cells (type + source preview)
├── assets/
│   ├── css/style.css
│   └── js/script.js
└── final/                            # Research benchmark, trained artifacts, and live demo
    ├── bangla-wsd-final.ipynb        # Main benchmark notebook (19-step pipeline)
    ├── bangla-wsd(with testing).ipynb# Variant of the above with extended manual test/inspection cells
    ├── banglabert1.ipynb             # Early single-model exploration notebook
    ├── kappa/
    │   ├── kappa.py                  # Cohen's κ calculator (JSON output)
    │   └── wsd_annotations.csv       # Sample annotations export for offline evaluation
    ├── wsd_demo_app/                 # Gradio live-inference demo (see below)
    │   ├── app.py
    │   ├── requirements.txt
    │   └── README.md
    ├── bangla_wsd_benchmark_all_artifacts/   # Full results: models, metrics, plots (see below)
    ├── artifacts_without_model/              # Same reports/plots/tables, without model weights (lightweight)
    ├── prev/                                 # Superseded notebook + dataset versions (kept for history)
    ├── models/                               # Misc. saved model scratch space
    ├── Bangla_WSD_Defense_Part1.pptx         # Thesis defense slides (part 1)
    ├── Bangla_WSD_Defense_Part2.pptx         # Thesis defense slides (part 2)
    ├── testing.pdf                           # Manual testing log / report
    └── wsd_random_test_report.pdf            # Random-sample test report
```

---

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

`wsd_bangla.sql` is a **full data dump** (schema + live rows) exported from the InfinityFree production database — use it to seed a local copy of the exact production dataset. `database.sql` / `database_infinityfree.sql` are lightweight **schema + seed-user only** scripts for fresh setups.

### Required Excel Columns

The importer expects the following columns (order-independent):

| Column | Required | Description |
|--------|----------|-------------|
| `word_id` | optional | Identifier of the ambiguous word |
| `target_word` | **required** | The Bangla word to be disambiguated |
| `sense_id` | optional | Sense identifier |
| `sense_gloss` | **required** | Gloss / definition for this sense |
| `text` | **required** | The full sentence containing the target word |

---

## 🚀 Getting Started — Annotation Platform

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
- Open phpMyAdmin and import `database.sql` (schema + seed users), or `wsd_bangla.sql` for a full production data snapshot.

This creates the `bangla_wsd` database, all tables, and (with `database.sql`) two seed accounts.

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

Sign in as admin → **Import Dataset** → upload your `.xlsx` file (e.g. `dataset_updated.xlsx`). Duplicate rows (same `source_row_hash`) are skipped automatically.

### 7. (Optional) Enable the Evaluation Dashboard

```bash
pip install pandas scikit-learn
```

`calculate_metrics.php` will probe for `python`, `python3`, or `py` and surface a friendly error if Python is missing or the dependencies aren't installed.

---

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

---

## 🧑‍🏫 Annotator Workflow

1. Annotator logs in → lands on `annotation.php`.
2. The next **unannotated** sentence is shown with the target word highlighted.
3. All distinct senses (glosses) for that target word are listed in randomized order.
4. Annotator picks a sense → submits → next sentence loads automatically.
5. The `Skip` button defers a sentence to the end of the queue (session-scoped).
6. When all sentences are labeled, the UI shows the completion screen.

Progress (`Completed X / Y`) is shown in the top bar at all times.

---

## 📤 Exporting Annotations

`export_csv.php` (admin-only) downloads `wsd_annotations.csv` with columns:

```
sentence_id, target_word, sentence, <annotator_1>, <annotator_2>, ...
```

- One column per registered annotator.
- UTF-8 with **BOM** so it opens cleanly in Excel.
- Rows are aligned by `sentence_id`; missing labels are blank cells (not `NaN`).
- The Evaluation Dashboard (`evaluation.php`) builds the **same shape in memory** and pipes it to `kappa.py` — you can re-run evaluation offline by exporting and feeding the file directly to `kappa.py --csv path/to/file.csv`.

---

## 🐍 Python Evaluation Engine (`final/kappa/kappa.py`)

`kappa.py` reads an annotations CSV (one column per annotator + `sentence_id`, `target_word`, `sentence`) and emits a single JSON object on **stdout**. A sample export (`final/kappa/wsd_annotations.csv`) is included for offline testing without a live database.

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

---

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

---

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

---

## 📖 The Corpus

The dataset produced by the annotation platform, in its final published form:

- **12,059 sentences**, 402 unique ambiguous target words, 1,085 distinct sense glosses
- First 100 target words adopted from the public [Das Dawn et al. Bengali WSD dataset](https://www.kaggle.com/datasets/debapratimdasdawn/bengali-wsd-dataset); the remaining 302 words and all sentences/annotations are newly contributed
- Senses validated against the *Sansad Bangla Abhidhan* dictionary
- Two independent annotators labeled every sentence on this platform (sentence deduplication by SHA-256, randomized sense ordering to reduce positional bias, one-label-per-sentence-per-annotator database constraint)
- **Inter-annotator reliability:** Cohen's κ = 0.836 and 0.874 (both "almost perfect" on the Landis–Koch scale) — the first reliability statistic ever reported for a Bangla WSD dataset
- After preprocessing, word-specific label construction (to eliminate cross-word sense-label collisions), and rare-class filtering: **11,151 sentences, 373 target words, 1,048 word–sense classes**
- Fixed **70/15/15** stratified train/validation/test split (seed 42), reused identically across every experiment in the paper: 7,805 / 1,673 / 1,673 sentences

### Corpus schema

Each row contains: `word_id`, `target_word`, `sense_id`, `sense_gloss`, `text`

| word_id | target_word | sense_id | sense_gloss | text |
|---|---|---|---|---|
| 114 | রূপ | 1 | আকৃতি (shape) | মেঘের রূপ ক্রমশ বদলে গিয়ে ঝড়ের আভাস দিচ্ছিল। |
| 114 | রূপ | 2 | সৌন্দর্য (beauty) | তার রূপ দেখে কবিরা অনেক কবিতা লিখেছেন। |
| 114 | রূপ | 3 | অবস্থা (state) | দেশের বর্তমান রূপ দেখে অনেকেই হতাশ। |

> The shipped notebook/demo in `final/` trains on a smaller, filtered subset of this corpus (7,409 sentences, 181 target words, 657 word-sense classes after dropping rare senses with fewer than 3 samples) — see [Results — Shipped Notebook](#-results--shipped-notebook-44-experiments).

---

## 🧪 Research Benchmark — Methodology

### Text representations
1. **TF-IDF** — word-level 1–2 grams, 30,000 max features, sublinear TF, fit on train split only
2. **Pretrained static embeddings** — Bangla Word2Vec (100-D) and Bengali fastText (300-D, `wiki.bn`), combined into target-aware features: `[left-context mean; target-word vector; right-context mean]` with a 5-word window (rather than a whole-sentence mean, which would dilute the disambiguation signal)
3. **Frozen transformer embeddings** — mBERT, BanglaBERT, XLM-R; `[CLS]` vector concatenated with mean hidden state over the `[TGT]`-marked target span (1,536-D), no gradient updates to the backbone

### Classifiers and sequence models
- **Classical classifiers**: Naive Bayes, Logistic Regression, Linear SVM over every representation, with features standardized for the latter two
- **Recurrent architectures**: LSTM, GRU, BiLSTM, BiGRU applied only to Word2Vec and fastText (the two representations with real per-token sequence structure — feeding a recurrent network a single pooled vector isn't sequence modeling), hidden size 128, batch size 32, learning rate 1e-3, up to 30 epochs with early stopping (patience 5), sequences padded/truncated to 20 tokens

### Transformer fine-tuning
End-to-end fine-tuning of mBERT, BanglaBERT, and XLM-R on marked text (`[TGT] word [/TGT]`, registered as special tokenizer tokens so they stay atomic), with a shared classification head (LayerNorm → Dropout 0.3 → Linear 768×768 → GELU → Dropout 0.3 → Linear 768×num_classes), AdamW (weight decay 0.01) with discriminative learning rates (head at 10× the backbone rate), linear warm-up, class-balanced cross-entropy, gradient clipping at 1.0, bfloat16 mixed precision, batch size 16, max sequence length 128, up to 15 epochs with early stopping, best-validation-accuracy checkpoint restored before final test evaluation. mBERT/BanglaBERT use backbone rate 2e-5 with 10% warm-up; XLM-R needed a gentler 1e-5 with 20% warm-up after collapsing under the shared recipe. Trained on a Tesla T4 GPU (15.6 GB).

### Evaluation protocol
Every model is scored with accuracy, macro/weighted precision-recall-F1, balanced accuracy, MCC, and Cohen's kappa, under two regimes:
- **Global evaluation** — over the full word-sense label space
- **Target-word-constrained evaluation** — an inference-time-only restriction of predictions to the known target word's own candidate senses (the realistic deployment setting), which separates cross-word errors from true within-word sense confusions:

  ŷᵢᶜ = argmax₍k ∈ L(wᵢ)₎ sᵢₖ

  where `sᵢₖ` is the model's native score for class *k* on sentence *i*, and `L(wᵢ)` is the set of label indices belonging to target word `wᵢ`. The same trained model and score matrix are reused — no per-word classifier is ever trained.

---

## 📈 Results — Thesis Paper (Official, 29 Experiments)

Held-out test split: 1,673 sentences, global evaluation over all 1,048 classes. All values in %, except MCC.

| Embedding | Classifier | Accuracy | Macro F1 | Weighted F1 | MCC |
|---|---|---|---|---|---|
| TF-IDF | Naive Bayes | 14.23 | 8.85 | 12.96 | 0.157 |
| TF-IDF | Logistic Regression | 63.72 | 51.53 | 61.01 | 0.637 |
| TF-IDF | Linear SVM | 66.35 | 54.19 | 64.56 | 0.663 |
| Word2Vec | Naive Bayes | 61.09 | 49.46 | 59.08 | 0.611 |
| Word2Vec | Logistic Regression | 74.48 | 62.34 | 73.15 | 0.745 |
| Word2Vec | Linear SVM | 63.42 | 51.04 | 60.63 | 0.634 |
| Word2Vec | LSTM | 13.27 | 9.33 | 12.09 | 0.132 |
| Word2Vec | GRU | 29.05 | 20.53 | 26.65 | 0.290 |
| Word2Vec | BiLSTM | 58.46 | 46.12 | 55.67 | 0.584 |
| Word2Vec | BiGRU | 60.67 | 47.73 | 57.65 | 0.606 |
| fastText | Naive Bayes | 61.92 | 50.36 | 59.84 | 0.619 |
| fastText | Logistic Regression | 85.06 | 71.42 | 83.61 | 0.850 |
| fastText | Linear SVM | 79.74 | 66.30 | 78.26 | 0.797 |
| fastText | LSTM | 25.40 | 17.80 | 22.71 | 0.253 |
| fastText | GRU | 53.56 | 42.05 | 51.13 | 0.535 |
| fastText | BiLSTM | 70.23 | 57.35 | 68.05 | 0.702 |
| fastText | BiGRU | 71.49 | 58.67 | 69.47 | 0.715 |
| mBERT (frozen) | Naive Bayes | 75.85 | 63.54 | 74.65 | 0.758 |
| mBERT (frozen) | Logistic Regression | 79.74 | 67.42 | 78.37 | 0.797 |
| mBERT (frozen) | Linear SVM | 75.49 | 62.86 | 73.45 | 0.755 |
| XLM-R (frozen) | Naive Bayes | 85.89 | 72.00 | 84.66 | 0.859 |
| XLM-R (frozen) | Logistic Regression | 86.55 | 73.68 | 85.75 | 0.865 |
| XLM-R (frozen) | Linear SVM | 85.77 | 72.15 | 84.55 | 0.858 |
| BanglaBERT (frozen) | Naive Bayes | 85.59 | 72.52 | 85.05 | 0.856 |
| BanglaBERT (frozen) | Logistic Regression | 89.30 | 76.04 | 88.61 | 0.893 |
| BanglaBERT (frozen) | Linear SVM | 88.22 | 75.11 | 87.43 | 0.882 |
| mBERT | Fine-tuned | 84.16 | 71.68 | 83.59 | 0.841 |
| XLM-R | Fine-tuned | 86.01 | 73.05 | 85.02 | 0.860 |
| **BanglaBERT** | **Fine-tuned** | **89.72** | **76.42** | **88.97** | **0.897** |

### Target-word-constrained evaluation (selected models)

| Model | Global | Constrained | Gain |
|---|---|---|---|
| TF-IDF + Naive Bayes | 14.23 | 72.44 | +58.21 |
| Word2Vec + LSTM | 13.27 | 62.94 | +49.67 |
| Word2Vec + BiLSTM | 58.46 | 78.54 | +20.08 |
| TF-IDF + Linear SVM | 66.35 | 72.38 | +6.03 |
| Word2Vec + Log. Regr. | 74.48 | 80.10 | +5.62 |
| fastText + Log. Regr. | 85.06 | 85.30 | +0.24 |
| XLM-R (frozen) + Log. Regr. | 86.55 | 87.39 | +0.84 |
| BanglaBERT (frozen) + Log. Regr. | 89.30 | 89.54 | +0.24 |
| mBERT fine-tuned | 84.16 | 84.16 | 0.00 |
| XLM-R fine-tuned | 86.01 | 86.07 | +0.06 |
| **BanglaBERT fine-tuned** | 89.72 | 89.78 | +0.06 |

The constraint transforms weak models by removing cross-word errors for free, but barely moves the strongest models — showing their remaining errors are almost entirely **within-word sense confusions**, not word misidentification.

---

## 📊 Results — Shipped Notebook (44 Experiments)

`final/bangla-wsd-final.ipynb` is a self-contained, 19-step Kaggle/Jupyter notebook that trains and evaluates its own experiment grid on a filtered subset of the corpus (7,409 annotated sentences · 181 target words · 657 unique word-sense classes after dropping rare senses with <3 samples), producing a full comparison table and every plot/artifact needed for a writeup.

### Pipeline overview

| Step | What it does |
|------|---------------|
| 1–3 | Install/import libraries, fix random seeds, configure device (CPU/GPU) + mixed precision, centralize all hyperparameters |
| 4 | Load and validate `dataset_updated.xlsx` |
| 5 | Full preprocessing — drops broken/misleading rows |
| 6 | **Word-specific label construction** — labels are built as `target_word_sense_gloss` so identical glosses across different words never collide |
| 7 | Insert `[TGT] ... [/TGT]` markers around the target word (used by the fine-tuning stage) |
| 8 | Stratified **70/15/15** train/validation/test split |
| 9 | Shared utilities — metrics, timing, memory profiling, results-table builder used by every experiment |
| 10–11 | Tokenization + **embedding generation with disk caching** for all embedding types: TF-IDF, Word2Vec (trained on the train split only), fastText, and frozen-CLS BERT / mBERT / BanglaBERT |
| 12 | Embedding registry — single dict driving the automated experiment loop |
| 13 | Classical ML models — Naive Bayes, Logistic Regression, Linear SVM |
| 14 | Sequence deep-learning models — LSTM, GRU, BiLSTM, BiGRU (one generic configurable PyTorch module) |
| 15 | **End-to-end transformer fine-tuning** — mBERT and BanglaBERT, both trained fully (not frozen) |
| 16 | Final comparison — styled results table, bar charts per metric, all-metrics heatmap |
| 17 | Export everything (CSV, Excel, Markdown, LaTeX, Word) + zip artifacts for download |
| 18 | Summary & discussion of findings |
| 19 | Manual sanity-check of the best fine-tuned transformer via `predict_sense()` |

### Headline results (full table in `results.md` / `results.csv`)

| Embedding | Model | Accuracy | Macro F1 | ROC-AUC (macro) |
|---|---|---|---|---|
| **Fine-Tuned-Transformer** | **BanglaBERT-FineTuned** | **87.45%** | **85.57%** | **99.98%** |
| Fine-Tuned-Transformer | mBERT-FineTuned | 82.13% | 78.40% | 99.98% |
| TF-IDF | LinearSVM | 57.13% | 53.29% | 97.95% |
| TF-IDF | LogisticRegression | 52.53% | 47.82% | 98.29% |
| TF-IDF | BiGRU | 46.03% | 42.09% | 97.54% |
| … | … (39 more rows) | … | … | … |

Fine-tuned transformers massively outperform every classical embedding + classifier combination on this subset; BanglaBERT (Bangla-specific pre-training) beats multilingual BERT by ~5 accuracy points; the best classical baseline (TF-IDF + LinearSVM) trails BanglaBERT-FineTuned by ~30 points — consistent with the paper's overall conclusions even though the exact figures differ due to the smaller label space.

### Output artifacts

| Folder | Contents |
|--------|----------|
| `final/bangla_wsd_benchmark_all_artifacts/` | Full results — `results.{csv,md,tex,xlsx,docx}`, trained model files in `saved_models/` (`.pt` for PyTorch sequence/transformer models, `.pkl` for classical sklearn models), `classification_reports/`, `confusion_matrices/` (raw + normalized), `plots/` (PR curves, ROC curves, training curves per experiment) |
| `final/artifacts_without_model/` | Identical reports/plots/tables **without** the model weight files — a lightweight copy for sharing results without the multi-GB checkpoints |
| `final/prev/` | Superseded notebook and dataset versions kept for historical reference (`notebook1.ipynb`, `notebook2.ipynb`, `banglabert.ipynb`, and their `-fixed` variants, `dataset.xlsx`) |
| `final/models/` | Scratch space for miscellaneous saved model files |
| `final/Bangla_WSD_Defense_Part1/2.pptx` | Thesis defense presentation slides |
| `final/testing.pdf`, `final/wsd_random_test_report.pdf` | Manual and random-sample testing logs |

### Reproducing the benchmark

1. Open `final/bangla-wsd-final.ipynb` in Jupyter or upload it to Kaggle.
2. Attach/upload `dataset_updated.xlsx` as the input dataset.
3. Run all cells top to bottom — embeddings are cached to disk so re-runs after the first are much faster.
4. Results are written incrementally to `results_store` and exported at Step 17 into `bangla_wsd_benchmark_all_artifacts/`.

`peek_nb.py` is a small utility for quickly inspecting any notebook's cell structure without opening Jupyter:

```bash
python peek_nb.py final/bangla-wsd-final.ipynb
# prints: cell index, cell type, and a truncated source preview for every cell
```

---

## 🔑 Key Findings

These hold consistently across both the paper's 29-experiment run and the notebook's 44-experiment run:

- **Representation quality dominates classifier choice.** Holding the classifier fixed at logistic regression (paper numbers), accuracy climbs from 63.72% (TF-IDF) to 89.30% (frozen BanglaBERT) — a 23-point spread that dwarfs any within-representation classifier difference. Bangla WSD is bottlenecked by representation, not classifier choice.
- **Subword-aware fastText clearly outperforms word-level Word2Vec** (+10.6 points with logistic regression), consistent with Bangla's heavy inflectional morphology.
- **Language-specific pretraining beats multilingual scale.** BanglaBERT outperforms both multilingual backbones (mBERT, XLM-R) in every configuration, frozen and fine-tuned — monolingual pretraining evidently buys more Bangla-relevant capacity than the hundredfold-larger multilingual corpora behind mBERT/XLM-R.
- **Recurrent models underperform classical classifiers** on the same embeddings (best RNN in the paper: fastText BiGRU at 71.49% vs. 85.06% for fastText + logistic regression), likely because with only ~7.4 training sentences per class on average, sequence encoders overfit before they can exploit word order.
- **Fine-tuning is expensive relative to its gain.** Fine-tuned BanglaBERT (89.72%) beats frozen BanglaBERT + logistic regression (89.30%) by only 0.42 points, despite fine-tuning taking ~70× longer (~64 min vs. under 1 min on a T4 GPU). For deployments without a GPU training budget, frozen BanglaBERT + a linear head is a near-optimal substitute.
- **Optimization sensitivity across backbones.** XLM-R fine-tuning collapsed under the learning-rate recipe that worked for mBERT/BanglaBERT and required a gentler, separately-tuned schedule — a caution for benchmarks that fine-tune all backbones under one blind recipe.
- **Target-word-constrained inference is a free win for weak models, but not for strong ones** — the strongest models' remaining errors are almost entirely within-word sense confusions, which the constraint cannot fix. A deployed system should always apply this cost-free constraint; future gains for strong models must come from better sense discrimination, not better word identification.

---

## 📚 Comparison with Prior Bangla WSD Work

| Source | Words | Sentences/Docs | Reliability |
|---|---|---|---|
| Pal and Saha (2019) | 7 | 2,478 sent. | — |
| Nazah et al. (2017) | 50 | 160 sent. | — |
| Saha et al. (2019) | 100 | 3,860 sent. | — |
| Kaysar and Khan (2018) | unspec. | 100–1,600 patt. | — |
| Das Dawn et al. (2023) | 100 | 345 docs | — |
| **This work** | **402** | **12,059 sent.** | **κ = 0.836 / 0.874** |

Direct numeric comparison with prior studies is necessarily approximate, since each was evaluated on its own private dataset. The strongest previously reported Bangla WSD accuracy is 80.82% on 100 target words, with other supervised studies reporting 77.5–80% on 50–100 words. Fine-tuned BanglaBERT reaches 89.72% on a task that is harder on every axis: 373 target words, a 1,048-class label space, and three to four times more sentences than the largest prior dataset.

---

## 🖥️ Live Demo App (Gradio)

`final/wsd_demo_app/` is a standalone Gradio application for live-testing the fine-tuned transformer models trained by the benchmark notebook. It only **reads** from `bangla_wsd_benchmark_all_artifacts/` and `dataset_updated.xlsx` — it never modifies the training artifacts or the PHP annotation platform.

### Launch

```bash
cd final/wsd_demo_app
pip install -r requirements.txt
python app.py
```

Then open **http://127.0.0.1:7860**. First load takes ~30–60s while the transformer checkpoints (~650MB each) load into memory.

### Tabs

| Tab | What it shows |
|-----|----------------|
| **Predict** | Enter a Bangla sentence + the target word → side-by-side comparison table (predicted sense, confidence, benchmark accuracy, latency), a confidence bar chart, and a top-5 sense breakdown per model. Click any row under "Example Inputs" to auto-fill from the real dataset. A collapsible "Known Words" list shows all 181 words the models were trained on. |
| **Benchmark Results** | The full experiment table and accuracy chart from `results.csv`, for context on how the two live models compare to every other configuration tried. |
| **About** | Task description, model architecture, training hyperparameters, and dataset statistics. |

### Models wired up for live inference

| Model | Test Accuracy | Macro F1 | Params |
|---|---|---|---|
| BanglaBERT-FineTuned | 87.45% | 85.57% | 165M |
| mBERT-FineTuned | 82.13% | 78.40% | 179M |

Only these two are runnable in the demo — the TF-IDF vectorizer and Word2Vec/fastText embedding models used by the other experiments were not exported alongside the classifier weights, so they can't process new text.

### How prediction works

The demo's dataset covers only **181 target words** (657 total word-sense classes after filtering). The model is a single 657-way classifier, but each word only ever has a handful of valid senses — so the app **restricts logits to the target word's own valid sense indices before softmax**, exactly mirroring `predict_sense()` in `bangla-wsd-final.ipynb` (cell 79). Typing a word outside the known list shows a warning instead of a meaningless prediction.

### Demo app troubleshooting

| Symptom | Fix |
|---------|-----|
| Slow predictions (1–2s) | Expected on CPU (no CUDA detected); fine for single-input live demos |
| `ImportError` about TensorFlow DLLs | `app.py` sets `USE_TF=0` / `USE_FLAX=0` so `transformers` never touches TensorFlow — make sure you're running the `app.py` from this folder |
| Gradio/FastAPI/Starlette version conflicts on install | `requirements.txt` pins known-good ranges; if `pip` still resolves a broken combo, force: `pip install "gradio>=4.40,<5.0" "huggingface-hub>=0.23.2,<1.0" "fastapi==0.115.5" "starlette==0.41.3"` |

---

## ⚠️ Limitations

- The sense inventory follows a single dictionary and averages 10.6 sentences per sense, so rare senses remain hard (macro F1 trails weighted F1 by ~12 points for all strong models in the paper's evaluation).
- Validation and test splits do not contain all 1,048 classes (918 and 914 present, respectively); metrics are computed over the full label set so averages are not artificially inflated.
- Results are single-run on one fixed split by design; conclusions rest on pattern consistency across all experiments rather than repeated runs.
- The corpus follows the lexical-sample (target-word) formulation rather than all-words WSD, since no adequately covered all-words Bangla sense inventory yet exists.
- The demo app and shipped notebook operate on a smaller, filtered subset of the full corpus (181 words / 657 classes vs. the paper's 373 words / 1,048 classes) — see the note at the top of this README.

---

## 🔮 Future Work

- Adding examples for rare senses to improve macro-level performance
- Parameter-efficient fine-tuning approaches (e.g., LoRA/adapters)
- Extending toward all-words Bangla WSD as sense-inventory coverage improves

---

## 🛠️ Helper Functions (`config.php`)

| Function | Purpose |
|----------|---------|
| `db()` | Lazy PDO singleton with utf8mb4 + safe prepares |
| `e($value)` | HTML-escape for safe output |
| `current_user()` | Fetch the logged-in user (or `null`) |
| `require_login()` / `require_role($role)` | Guard helpers; redirect on failure |
| `highlight_target($text, $w)` | Wrap the target word in `<mark>` (first occurrence) |
| `flash($type, $msg)` / `consume_flash()` | One-shot flash messages |

---

## 🔐 Security Checklist

- ✅ All queries use **prepared statements** (PDO with `ATTR_EMULATE_PREPARES => false`).
- ✅ Passwords are stored with **bcrypt** (`password_hash` / `password_verify`).
- ✅ `session_regenerate_id(true)` runs on successful login.
- ✅ All output is escaped via `e()`.
- ✅ Admin pages call `require_role('admin')` before rendering.
- ⚠️ `shell_exec` is used to launch Python — disable it on production if you don't need the Evaluation Dashboard (`disable_functions = shell_exec` in `php.ini`).
- ⚠️ If you deploy to InfinityFree, rotate the credentials in `config.php` before sharing the repo.
- ⚠️ `wsd_bangla.sql` is a **production data export** — treat it like any other credential/data artifact; do not publish it in a public repository without scrubbing personal data.

---

## 🚢 Deployment Notes

| Environment | DB port | DB host | Notes |
|-------------|---------|---------|-------|
| **XAMPP (local)** | `3307` | `localhost` | Default in `config.php` |
| **InfinityFree (hosted)** | `3306` | `sql*.infinityfree.com` | Set credentials in the `if ($_SERVER['HTTP_HOST'] !== 'localhost')` branch of `config.php` |

- `database_infinityfree.sql` is a variant prepared for the hosted MySQL.
- Sessions are default file-based — fine for small-scale annotation work. Switch to a DB-backed session handler before going to a multi-server setup.
- The dev build never modifies the `dataset` table from the annotator side; only admins import into it.
- The research notebook and Gradio demo app are **standalone Python components** — they don't need to be deployed alongside the PHP app; they only require the `final/` artifacts and `dataset_updated.xlsx`.

---

## 🛠️ Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Login page shows "Database connection failed" | Wrong port or credentials in `config.php` | Verify `$port` (3307 on XAMPP, 3306 elsewhere) and re-test via `php -r "require 'config.php'; var_dump(db());"` |
| Importing `.xlsx` silently skips every row | All `source_row_hash` values already exist | Drop duplicates in the spreadsheet, or clear the `dataset` table |
| Evaluation shows "Python is not installed on the server" | Missing interpreter on hosted env | Install Python 3.10+ + `pandas scikit-learn`, or run `kappa.py` offline with the exported CSV |
| `evaluation.php` returns `403 Forbidden` | Cookie expired or non-admin session | Re-login as `admin@example.com` |
| User Details list shows `0 submitted` for everyone | Annotators have only used Skip | Skip does not persist — labels only count after a sense is chosen |
| `composer install` fails | PHP version < 8.0 | Upgrade PHP, or pin to `phpoffice/phpspreadsheet ^1.25` |
| Notebook `Step 4` fails to find the dataset | `dataset_updated.xlsx` not uploaded/attached | Upload it as a Kaggle Dataset or place it next to the notebook |
| Demo app can't find `saved_models/` | Wrong working directory | Run `python app.py` from inside `final/wsd_demo_app/` so relative paths resolve to `../bangla_wsd_benchmark_all_artifacts/` |

---

## 📚 Related Artifacts

The `final/` directory contains the full research trail behind the platform:

- **`bangla-wsd-final.ipynb`** — the canonical benchmark notebook (see [Results — Shipped Notebook](#-results--shipped-notebook-44-experiments)).
- **`bangla-wsd(with testing).ipynb`** — the same pipeline with extended manual test/inspection cells.
- **`banglabert1.ipynb`** and **`prev/`** — earlier exploration notebooks and dataset versions, kept for historical/reproducibility reference.
- **`bangla_wsd_benchmark_all_artifacts/`** / **`artifacts_without_model/`** — full trained models, metrics, plots, and reports.
- **`wsd_demo_app/`** — the live Gradio demo (see [Live Demo App](#-live-demo-app-gradio)).
- **`kappa/`** — the standalone Cohen's κ CLI tool shared with the PHP Evaluation Dashboard.
- **`Bangla_WSD_Defense_Part1/2.pptx`**, **`testing.pdf`**, **`wsd_random_test_report.pdf`** — thesis defense slides and testing reports.

None of these are required to run the annotation platform itself — they document and support the underlying WSD research.

---

## 📄 Citation

If you use this corpus, platform, or benchmark in your work, please cite:

```bibtex
@article{paul_bangla_wsd,
  title   = {Bangla Word Sense Disambiguation: A Large-Scale Benchmark of Embedding and Classifier Combinations},
  author  = {Paul, Prova Rani and Hasan, K. M. Azharul},
  school  = {Khulna University of Engineering \& Technology},
  note    = {Thesis}
}
```

---

## 🙏 Acknowledgments

The first 100 target words in this corpus are adopted from the public [Bengali WSD dataset by Das Dawn et al.](https://www.kaggle.com/datasets/debapratimdasdawn/bengali-wsd-dataset), gratefully acknowledged as the starting point of this work.

---

## 📄 License

Internal thesis/research project. Add an explicit license before public distribution.

---

## 📬 Contact

Prova Rani Paul — provapaul123@gmail.com
Dept. of Computer Science and Engineering, KUET, Khulna, Bangladesh
