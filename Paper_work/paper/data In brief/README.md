# Data in Brief: A Sense-Annotated Dataset for Bangla Word Sense Disambiguation

This folder contains a complete, self-contained Data in Brief (Elsevier) manuscript
describing `wsd_dataset.xlsx` (the file one level up, at `../wsd_dataset.xlsx`).

## Contents

| File | Purpose |
|---|---|
| `main.tex` | The manuscript, following the Data in Brief template structure. |
| `references.bib` | Bibliography (9 entries, all cited in-text). |
| `numbers.tex` | **Auto-generated.** Numeric macros (`\NRows`, `\NWords`, ...) used throughout `main.tex`, sourced from `stats.json`. Do not hand-edit. |
| `sample_table.tex` | **Auto-generated.** Real sample rows (target word জল) rendered as a LaTeX table. |
| `analyze_dataset.py` | Reads `../wsd_dataset.xlsx` and computes every statistic reported in the article. Writes `stats.json`, `stats_report.txt`, and `dist_*.csv`. |
| `generate_tables.py` | Reads `stats.json` and the raw dataset, writes `numbers.tex` and `sample_table.tex`. |
| `generate_figures.py` | Reads `stats.json` and `dist_*.csv`, writes `Figure_1..4` (`.pdf` + `.png`). |
| `generate_banner.py` | Draws `logo.png`, the red running-header banner used on every page (an original recreation of the Data in Brief template's colour/layout, not Elsevier's own trademarked logo artwork, which is not available in any local file). |
| `Figure_1_workflow.*` | Dataset construction pipeline diagram. |
| `Figure_2_word_distributions.*` | Senses-per-word-entry and examples-per-target-word distributions. |
| `Figure_3_class_sizes.*` | Examples-per-word-sense-class distribution. |
| `Figure_4_sentence_length.*` | Sentence-length (token count) distribution. |
| `stats.json` / `stats_report.txt` | Machine-readable / human-readable dump of every computed statistic. |
| `dist_*.csv` | Raw distribution series behind each figure. |
| `anomaly_*.csv` | Evidence for the two-row character-encoding anomaly reported in Limitations. |
| `main.pdf` | Compiled manuscript. |

## Reproducing the analysis and manuscript from scratch

Requires Python 3.11+ with `pandas`, `openpyxl`, `matplotlib`, and `Pillow`;
and a XeLaTeX distribution (tested with MiKTeX; TeX Live also works) with the
**carlito** and **elsarticle** packages installed (`mpm --install=carlito
--install=elsarticle` on MiKTeX, or `tlmgr install carlito elsarticle` on TeX
Live — both are standard, freely redistributable packages, already present
on Overleaf). The manuscript also needs a Bengali-capable font for mixed
Bangla/English text; the authoring machine has **Nirmala UI** (ships with
Windows). On a machine without these fonts, `main.tex`'s `\IfFontExistsTF`
fallbacks switch to **TeX Gyre Termes** / **Noto Serif Bengali** or **Noto
Sans Bengali** automatically — install `fonts-noto-bengali` (Linux) or use
Overleaf, which has Noto Bengali preinstalled.

```bash
python analyze_dataset.py   # -> stats.json, stats_report.txt, dist_*.csv
python generate_tables.py   # -> numbers.tex, sample_table.tex
python generate_figures.py  # -> Figure_1..4.{pdf,png}
python generate_banner.py   # -> logo.png (running header banner)

xelatex -interaction=nonstopmode main.tex
bibtex main
xelatex -interaction=nonstopmode main.tex
xelatex -interaction=nonstopmode main.tex   # -> main.pdf
```

Run the three Python scripts in that order whenever `../wsd_dataset.xlsx`
changes; every number, table, and figure in `main.tex` is sourced from their
output, not hand-typed.

## Before this can actually be submitted

Everything marked `[TO BE CONFIRMED]` in `main.tex` needs a real value from
the authors:

1. **Public data repository.** `wsd_dataset.xlsx` is not yet deposited
   anywhere public. Data in Brief requires the dataset to be publicly
   accessible (with an anonymous-access working URL) *before* submission.
   Upload it to Mendeley Data, Zenodo, or a similar repository, then fill in
   the Specifications Table's "Data accessibility" row and the
   `bangla_wsd_dataset` entry in `references.bib` with the real repository
   name, DOI, and URL.
2. **Related research article status.** The companion ICCIT-style paper
   (`../main.tex`) has no confirmed publication venue or DOI in the local
   files. Fill this in once it is accepted/published, in both the
   Specifications Table and the `paulhasan2025banglawsdbenchmark` entry in
   `references.bib`.
3. **Prova Rani Paul's institutional email and Twitter/X handle** are not
   present in any source file and must be supplied by the author.
4. **CRediT author statement** — the roles listed follow the single-author
   thesis / supervisor pattern found in the source materials; both authors
   should confirm or adjust them.
5. **Running header banner.** `logo.png` is an original recreation (colours
   and layout only, drawn by `generate_banner.py`) of the Data in Brief
   template's red header banner — no official Elsevier logo asset was found
   anywhere under the thesis directory, and none was downloaded from
   elsewhere. Before actual submission, replace `logo.png` with Elsevier's
   real template header image if the submission system requires the exact
   official artwork.

## Known data-quality findings (already disclosed in the article's Limitations)

The analysis in `analyze_dataset.py` found two real issues in
`wsd_dataset.xlsx`, both reported transparently in the manuscript rather than
silently fixed:

- **Two rows** have `target_word` spelled with a Latin "ī" (U+012B) instead of
  the correct Bangla vowel sign: `word_id=171` (রান + ī, should be রানী) and
  `word_id=179` (নদ + ī, should be নদী). See `anomaly_odd_wordchar_rows.csv`
  and `anomaly_wordid_171_179.csv`.
- **874 rows** have a `target_word` that does not occur verbatim inside
  `text` (typically due to Bangla inflectional suffixes in running text), and
  **8 rows** repeat an example-sentence value already used elsewhere. Neither
  has been filtered from the released file; see Limitations and Experimental
  Design in `main.tex` for why that decision was left to downstream reusers.
