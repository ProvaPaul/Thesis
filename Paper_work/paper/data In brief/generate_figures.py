"""
Generates all figures for the Data in Brief article from the outputs of
analyze_dataset.py (must be run first). No dataset numbers are hand-typed;
everything is read from stats.json / dist_*.csv.
"""
import json
import os

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch
import pandas as pd

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

with open(os.path.join(BASE_DIR, "stats.json"), encoding="utf-8") as f:
    stats = json.load(f)

plt.rcParams.update({
    "font.size": 10,
    "axes.spines.top": False,
    "axes.spines.right": False,
})

ACCENT = "#1565C0"
ACCENT2 = "#2E7D32"
ACCENT3 = "#E65100"

# ─────────────────────────────────────────────────────────────────────────
# Figure 1 — dataset construction workflow
# ─────────────────────────────────────────────────────────────────────────
fig, ax = plt.subplots(figsize=(11, 2.6))
ax.set_xlim(0, 11)
ax.set_ylim(0, 2)
ax.axis("off")

steps = [
    "Ambiguous\ntarget-word\nselection",
    "Sense definition\n(dictionary-\nvalidated)",
    "Example-sentence\ncollection\nper word-sense",
    "Independent dual\nannotation\n(web platform)",
    "Reliability check\n(Cohen's kappa)",
    "Final structured\ndataset\n(.xlsx)",
]
n = len(steps)
box_w, box_h = 1.55, 1.3
gap = (11 - n * box_w) / (n + 1)
x = gap
centers = []
for i, label in enumerate(steps):
    box = FancyBboxPatch((x, 0.35), box_w, box_h,
                          boxstyle="round,pad=0.02,rounding_size=0.08",
                          linewidth=1.3, edgecolor=ACCENT,
                          facecolor="#E3F2FD" if i < n - 1 else "#E8F5E9")
    ax.add_patch(box)
    ax.text(x + box_w / 2, 0.35 + box_h / 2, label, ha="center", va="center",
             fontsize=8.3, linespacing=1.35)
    centers.append(x + box_w / 2)
    x += box_w + gap

for i in range(n - 1):
    ax.annotate("", xy=(centers[i + 1] - box_w / 2 - 0.03, 1.0),
                xytext=(centers[i] + box_w / 2 + 0.03, 1.0),
                arrowprops=dict(arrowstyle="-|>", color="#455A64", lw=1.4))

plt.tight_layout()
plt.savefig(os.path.join(BASE_DIR, "Figure_1_workflow.pdf"))
plt.savefig(os.path.join(BASE_DIR, "Figure_1_workflow.png"), dpi=200)
plt.close()

# ─────────────────────────────────────────────────────────────────────────
# Figure 2 — target-word-level distributions: senses per word entry,
# examples per target word
# ─────────────────────────────────────────────────────────────────────────
senses_dist = pd.read_csv(os.path.join(BASE_DIR, "dist_senses_per_word_entry.csv"), index_col=0)
examples_dist = pd.read_csv(os.path.join(BASE_DIR, "dist_examples_per_word.csv"), index_col=0)
examples_counts = examples_dist["count"].value_counts().sort_index()

fig, axes = plt.subplots(1, 2, figsize=(10, 3.6))

ax = axes[0]
ax.bar(senses_dist.index.astype(str), senses_dist["n_word_entries"], color=ACCENT, width=0.6)
for i, v in enumerate(senses_dist["n_word_entries"]):
    ax.text(i, v + 3, str(v), ha="center", fontsize=9)
ax.set_xlabel("Number of candidate senses")
ax.set_ylabel("Number of word entries")
ax.set_title("(a) Senses per word entry", fontsize=10, fontweight="bold")

ax = axes[1]
ax.bar(examples_counts.index.astype(str), examples_counts.values, color=ACCENT2, width=0.6)
for i, v in enumerate(examples_counts.values):
    ax.text(i, v + 3, str(v), ha="center", fontsize=8)
ax.set_xlabel("Sentences per target word")
ax.set_ylabel("Number of target words")
ax.set_title("(b) Example sentences per target word", fontsize=10, fontweight="bold")

plt.tight_layout()
plt.savefig(os.path.join(BASE_DIR, "Figure_2_word_distributions.pdf"))
plt.savefig(os.path.join(BASE_DIR, "Figure_2_word_distributions.png"), dpi=200)
plt.close()

# ─────────────────────────────────────────────────────────────────────────
# Figure 3 — examples per word-sense class (class-size distribution)
# ─────────────────────────────────────────────────────────────────────────
class_dist = pd.read_csv(os.path.join(BASE_DIR, "dist_class_sizes.csv"), index_col=0)

fig, ax = plt.subplots(figsize=(6.5, 3.8))
ax.bar(class_dist.index.astype(str), class_dist["n_classes"], color=ACCENT3, width=0.5)
for i, v in enumerate(class_dist["n_classes"]):
    ax.text(i, v + 8, str(v), ha="center", fontsize=9)
ax.set_xlabel("Example sentences in the (word, sense) class")
ax.set_ylabel("Number of word–sense classes")
ax.set_title(f"Example sentences per word–sense class (n = {stats['unique_word_id_sense_id_pairs']} classes)",
             fontsize=10, fontweight="bold")
plt.tight_layout()
plt.savefig(os.path.join(BASE_DIR, "Figure_3_class_sizes.pdf"))
plt.savefig(os.path.join(BASE_DIR, "Figure_3_class_sizes.png"), dpi=200)
plt.close()

# ─────────────────────────────────────────────────────────────────────────
# Figure 4 — sentence length distribution (tokens)
# ─────────────────────────────────────────────────────────────────────────
len_dist = pd.read_csv(os.path.join(BASE_DIR, "dist_sentence_length_tokens.csv"), index_col=0)

fig, ax = plt.subplots(figsize=(7, 3.8))
ax.bar(len_dist.index.astype(int), len_dist["n_sentences"], color=ACCENT, width=0.8)
mean_len = stats["sentence_length_tokens"]["mean"]
ax.axvline(mean_len, color=ACCENT3, linestyle="--", linewidth=1.5,
           label=f"mean = {mean_len:.1f} tokens")
ax.set_xlabel("Sentence length (whitespace-separated tokens)")
ax.set_ylabel("Number of sentences")
ax.set_title(f"Sentence-length distribution (n = {stats['n_rows']} sentences)",
             fontsize=10, fontweight="bold")
ax.legend(frameon=False)
plt.tight_layout()
plt.savefig(os.path.join(BASE_DIR, "Figure_4_sentence_length.pdf"))
plt.savefig(os.path.join(BASE_DIR, "Figure_4_sentence_length.png"), dpi=200)
plt.close()

print("OK -- wrote Figure_1..4 (.pdf and .png) to", BASE_DIR)
