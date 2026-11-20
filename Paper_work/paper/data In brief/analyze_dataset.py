"""
Reproducible statistical analysis of wsd_dataset.xlsx for the Data in Brief article.
Reads the raw dataset only -- no numbers are hand-typed into the manuscript.
Writes stats.json (machine-readable) and stats_report.txt (utf-8, human-readable)
into this folder.
"""
import json
import os
import sys

import pandas as pd
import numpy as np

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_PATH = os.path.join(BASE_DIR, "..", "wsd_dataset.xlsx")

df = pd.read_excel(DATASET_PATH)

report = []


def log(line=""):
    report.append(str(line))


log("=" * 70)
log("WSD DATASET -- STATISTICAL ANALYSIS")
log(f"Source file: {os.path.abspath(DATASET_PATH)}")
log("=" * 70)

# ---- Basic shape ---------------------------------------------------------
n_rows, n_cols = df.shape
log(f"\n[Shape] rows={n_rows}, cols={n_cols}")
log(f"[Columns] {list(df.columns)}")
log(f"[Dtypes]\n{df.dtypes.to_string()}")

# ---- Missing values -------------------------------------------------------
missing = df.isna().sum()
log(f"\n[Missing values per column]\n{missing.to_string()}")
log(f"[Total missing cells] {int(missing.sum())}")

# ---- Duplicates -------------------------------------------------------
dup_rows_full = df.duplicated(keep=False).sum()
dup_rows_exact_extra = df.duplicated(keep="first").sum()
dup_text = df["text"].duplicated(keep=False).sum()
dup_text_extra = df["text"].duplicated(keep="first").sum()
log(f"\n[Duplicate full rows] rows involved={int(dup_rows_full)}, "
    f"extra copies beyond first={int(dup_rows_exact_extra)}")
log(f"[Duplicate 'text' values] rows involved={int(dup_text)}, "
    f"extra copies beyond first={int(dup_text_extra)}")

# ---- Target words -------------------------------------------------------
n_unique_words = df["target_word"].nunique()
n_unique_word_ids = df["word_id"].nunique()
log(f"\n[Unique target_word strings] {n_unique_words}")
log(f"[Unique word_id values] {n_unique_word_ids}  "
    f"(word_id counts word-entries; a word string can have >1 entry "
    f"if re-used with a separate sense inventory)")

words_with_multiple_ids = (
    df.groupby("target_word")["word_id"].nunique()
    .loc[lambda s: s > 1]
)
log(f"[target_word strings mapped to >1 word_id] {len(words_with_multiple_ids)}")
if len(words_with_multiple_ids):
    log(words_with_multiple_ids.to_string())

# ---- Senses -------------------------------------------------------
n_unique_gloss_strings = df["sense_gloss"].nunique()
word_sense_pairs = df[["target_word", "sense_gloss"]].drop_duplicates()
n_word_sense_pairs = len(word_sense_pairs)
wordid_sense_pairs = df[["word_id", "sense_id"]].drop_duplicates()
n_wordid_sense_pairs = len(wordid_sense_pairs)
log(f"\n[Unique sense_gloss strings (raw text, may repeat across words)] {n_unique_gloss_strings}")
log(f"[Unique (target_word, sense_gloss) pairs] {n_word_sense_pairs}")
log(f"[Unique (word_id, sense_id) pairs -- word-entry-specific classes] {n_wordid_sense_pairs}")

# senses per word entry (word_id)
senses_per_word_id = df.groupby("word_id")["sense_id"].nunique()
log(f"\n[Senses per word_id] min={senses_per_word_id.min()}, "
    f"max={senses_per_word_id.max()}, mean={senses_per_word_id.mean():.3f}, "
    f"median={senses_per_word_id.median()}")
senses_per_word_id_counts = senses_per_word_id.value_counts().sort_index()
log(f"[Distribution: number of word entries by sense count]\n{senses_per_word_id_counts.to_string()}")

# ---- Examples per word / per sense -------------------------------------------------------
examples_per_word = df.groupby("target_word").size()
log(f"\n[Examples (sentences) per target_word] min={examples_per_word.min()}, "
    f"max={examples_per_word.max()}, mean={examples_per_word.mean():.3f}, "
    f"median={examples_per_word.median()}")

examples_per_wordid_sense = df.groupby(["word_id", "sense_id"]).size()
log(f"\n[Examples per (word_id, sense_id) class] min={examples_per_wordid_sense.min()}, "
    f"max={examples_per_wordid_sense.max()}, mean={examples_per_wordid_sense.mean():.3f}, "
    f"median={examples_per_wordid_sense.median()}")
class_size_counts = examples_per_wordid_sense.value_counts().sort_index()
log(f"[Distribution: number of classes by example count]\n{class_size_counts.to_string()}")

# ---- Target word occurs verbatim in sentence? -------------------------------------------------------
present_mask = df.apply(lambda r: str(r["target_word"]) in str(r["text"]), axis=1)
n_target_missing_from_text = int((~present_mask).sum())
log(f"\n[Rows where target_word does NOT appear verbatim in text] {n_target_missing_from_text}")

# ---- Sentence length -------------------------------------------------------
df["_char_len"] = df["text"].astype(str).str.len()
df["_word_len"] = df["text"].astype(str).str.split().apply(len)
log(f"\n[Sentence length in whitespace-separated tokens] "
    f"min={df['_word_len'].min()}, max={df['_word_len'].max()}, "
    f"mean={df['_word_len'].mean():.3f}, median={df['_word_len'].median()}, "
    f"std={df['_word_len'].std():.3f}")
log(f"[Sentence length in characters] "
    f"min={df['_char_len'].min()}, max={df['_char_len'].max()}, "
    f"mean={df['_char_len'].mean():.3f}, median={df['_char_len'].median()}, "
    f"std={df['_char_len'].std():.3f}")

word_len_hist = df["_word_len"].value_counts().sort_index()
log(f"\n[Sentence length histogram (tokens -> count)]\n{word_len_hist.to_string()}")

# ---- Top / bottom represented target words -------------------------------------------------------
top20 = examples_per_word.sort_values(ascending=False).head(20)
bottom20 = examples_per_word.sort_values(ascending=True).head(20)
log(f"\n[Top 20 target words by example count]\n{top20.to_string()}")
log(f"\n[Bottom 20 target words by example count]\n{bottom20.to_string()}")

# ---- Save machine-readable JSON -------------------------------------------------------
stats = {
    "source_file": "wsd_dataset.xlsx",
    "n_rows": int(n_rows),
    "n_cols": int(n_cols),
    "columns": list(df.columns),
    "missing_values_total": int(missing.sum()),
    "missing_values_by_column": {k: int(v) for k, v in missing.items()},
    "duplicate_full_rows_extra": int(dup_rows_exact_extra),
    "duplicate_text_extra": int(dup_text_extra),
    "unique_target_word_strings": int(n_unique_words),
    "unique_word_id": int(n_unique_word_ids),
    "target_words_with_multiple_word_ids": int(len(words_with_multiple_ids)),
    "unique_sense_gloss_strings": int(n_unique_gloss_strings),
    "unique_target_word_sense_gloss_pairs": int(n_word_sense_pairs),
    "unique_word_id_sense_id_pairs": int(n_wordid_sense_pairs),
    "senses_per_word_id": {
        "min": int(senses_per_word_id.min()),
        "max": int(senses_per_word_id.max()),
        "mean": float(senses_per_word_id.mean()),
        "median": float(senses_per_word_id.median()),
        "distribution": {int(k): int(v) for k, v in senses_per_word_id_counts.items()},
    },
    "examples_per_target_word": {
        "min": int(examples_per_word.min()),
        "max": int(examples_per_word.max()),
        "mean": float(examples_per_word.mean()),
        "median": float(examples_per_word.median()),
    },
    "examples_per_word_sense_class": {
        "min": int(examples_per_wordid_sense.min()),
        "max": int(examples_per_wordid_sense.max()),
        "mean": float(examples_per_wordid_sense.mean()),
        "median": float(examples_per_wordid_sense.median()),
        "distribution_of_class_sizes": {int(k): int(v) for k, v in class_size_counts.items()},
    },
    "rows_target_word_not_in_text": int(n_target_missing_from_text),
    "sentence_length_tokens": {
        "min": int(df["_word_len"].min()),
        "max": int(df["_word_len"].max()),
        "mean": float(df["_word_len"].mean()),
        "median": float(df["_word_len"].median()),
        "std": float(df["_word_len"].std()),
    },
    "sentence_length_chars": {
        "min": int(df["_char_len"].min()),
        "max": int(df["_char_len"].max()),
        "mean": float(df["_char_len"].mean()),
        "median": float(df["_char_len"].median()),
        "std": float(df["_char_len"].std()),
    },
}

with open(os.path.join(BASE_DIR, "stats.json"), "w", encoding="utf-8") as f:
    json.dump(stats, f, ensure_ascii=False, indent=2)

with open(os.path.join(BASE_DIR, "stats_report.txt"), "w", encoding="utf-8") as f:
    f.write("\n".join(report))

# also dump the raw distribution series as CSVs for figure generation
examples_per_word.sort_values(ascending=False).to_csv(
    os.path.join(BASE_DIR, "dist_examples_per_word.csv"), header=["count"], encoding="utf-8"
)
senses_per_word_id_counts.to_csv(
    os.path.join(BASE_DIR, "dist_senses_per_word_entry.csv"), header=["n_word_entries"], encoding="utf-8"
)
class_size_counts.to_csv(
    os.path.join(BASE_DIR, "dist_class_sizes.csv"), header=["n_classes"], encoding="utf-8"
)
word_len_hist.to_csv(
    os.path.join(BASE_DIR, "dist_sentence_length_tokens.csv"), header=["n_sentences"], encoding="utf-8"
)

print("OK -- wrote stats.json, stats_report.txt, and dist_*.csv")
print(f"rows={n_rows} cols={n_cols} unique_target_word={n_unique_words} unique_word_id={n_unique_word_ids}")
print(f"unique_sense_gloss_strings={n_unique_gloss_strings} unique_(word,gloss)_pairs={n_word_sense_pairs}")
print(f"unique_(word_id,sense_id)_pairs={n_wordid_sense_pairs}")
print(f"missing_total={int(missing.sum())} dup_full_extra={int(dup_rows_exact_extra)} dup_text_extra={int(dup_text_extra)}")
print(f"rows_target_not_in_text={n_target_missing_from_text}")
