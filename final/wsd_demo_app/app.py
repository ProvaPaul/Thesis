"""
Bangla Word Sense Disambiguation (WSD) — Live Demo
====================================================
Loads the two fine-tuned transformer models (BanglaBERT-FineTuned and
mBERT-FineTuned) from ../bangla_wsd_benchmark_all_artifacts/saved_models/
and serves a Gradio UI for live, side-by-side comparison.

Model architecture, [TGT]-marker preprocessing, and inference logic are
copied exactly from the training notebook (bangla-wsd-final.ipynb) so
predictions match what was reported in results.csv.
"""

import os
import sys

os.environ["USE_TF"] = "0"
os.environ["USE_FLAX"] = "0"
os.environ["TRANSFORMERS_NO_ADVISORY_WARNINGS"] = "1"

if sys.stdout.encoding is None or sys.stdout.encoding.lower() != "utf-8":
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

import pickle
import time
import warnings

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
import numpy as np
import pandas as pd
import torch
import torch.nn as nn
import gradio as gr
from transformers import AutoModel, AutoTokenizer

warnings.filterwarnings("ignore")
os.environ["TOKENIZERS_PARALLELISM"] = "false"

# ── Paths ──────────────────────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ARTIFACTS_DIR = os.path.join(BASE_DIR, "..", "bangla_wsd_benchmark_all_artifacts")
MODELS_DIR = os.path.join(ARTIFACTS_DIR, "saved_models")
RESULTS_CSV = os.path.join(ARTIFACTS_DIR, "results.csv")
DATASET_XLSX = os.path.join(BASE_DIR, "..", "dataset_updated.xlsx")

DEVICE = torch.device("cuda" if torch.cuda.is_available() else "cpu")

MODEL_CONFIGS = {
    "BanglaBERT-FineTuned": {
        "backbone": "sagorsarker/bangla-bert-base",
        "color": "#1565C0",
        "accuracy": 87.45,
        "macro_f1": 85.57,
        "params": "165M",
        "size_mb": 631.4,
    },
    "mBERT-FineTuned": {
        "backbone": "bert-base-multilingual-cased",
        "color": "#E65100",
        "accuracy": 82.13,
        "macro_f1": 78.40,
        "params": "179M",
        "size_mb": 682.7,
    },
}


# ── Model architecture (exact copy from bangla-wsd-final.ipynb) ────────────
class TransformerWSD(nn.Module):
    """
    Input Sentence (with [TGT] markers)
        -> Transformer Encoder (12 layers)
        -> [CLS] token representation (768-dim)
        -> LayerNorm -> Dropout -> Linear(768, 768) -> GELU
        -> Dropout -> Linear(768, NUM_CLASSES)
    """

    def __init__(self, model_name: str, num_classes: int, dropout_rate: float = 0.3):
        super().__init__()
        self.bert = AutoModel.from_pretrained(model_name)
        hidden_size = self.bert.config.hidden_size
        self.classifier = nn.Sequential(
            nn.LayerNorm(hidden_size),
            nn.Dropout(p=dropout_rate),
            nn.Linear(hidden_size, hidden_size),
            nn.GELU(),
            nn.Dropout(p=dropout_rate),
            nn.Linear(hidden_size, num_classes),
        )

    def forward(self, input_ids, attention_mask):
        outputs = self.bert(input_ids=input_ids, attention_mask=attention_mask)
        cls_repr = outputs.last_hidden_state[:, 0, :]
        return self.classifier(cls_repr)


def insert_tgt_markers(sentence: str, target_word: str):
    """Insert [TGT] ... [/TGT] around the FIRST occurrence of target_word."""
    if target_word not in sentence:
        return None
    return sentence.replace(target_word, f"[TGT] {target_word} [/TGT]", 1)


# ── Dataset (needed to build word -> valid-sense-indices restriction) ───────
def build_word_specific_label(target_word: str, sense_gloss) -> str:
    gloss_clean = str(sense_gloss).strip().replace(" ", "_")
    return f"{target_word}_{gloss_clean}"


_dataset_df = pd.read_excel(DATASET_XLSX).dropna(subset=["target_word", "sense_gloss", "text"])
KNOWN_WORDS = sorted(_dataset_df["target_word"].unique().tolist())


def build_word_to_label_indices(label_encoder) -> dict:
    """Mirrors the notebook's word_to_label_indices: restricts inference for a
    target word to only the senses that word actually had during training."""
    class_to_idx = {cls: i for i, cls in enumerate(label_encoder.classes_)}
    word_to_indices: dict = {}
    for _, row in _dataset_df.iterrows():
        label = build_word_specific_label(row["target_word"], row["sense_gloss"])
        idx = class_to_idx.get(label)
        if idx is not None:
            word_to_indices.setdefault(row["target_word"], set()).add(idx)
    return {w: sorted(idxs) for w, idxs in word_to_indices.items()}


# ── Load both fine-tuned models at startup ──────────────────────────────────
print(f"\n{'=' * 60}\nLoading models on device: {DEVICE}\n{'=' * 60}")

LOADED_MODELS = {}
for name in MODEL_CONFIGS:
    load_dir = os.path.join(MODELS_DIR, name)
    print(f"\n[{name}] loading from {load_dir}")

    tokenizer = AutoTokenizer.from_pretrained(load_dir, use_fast=True)

    with open(os.path.join(load_dir, "label_encoder.pkl"), "rb") as f:
        label_encoder = pickle.load(f)
    num_classes = len(label_encoder.classes_)

    model = TransformerWSD(load_dir, num_classes, dropout_rate=0.3)
    head_state = torch.load(os.path.join(load_dir, "classifier_head.pt"), map_location=DEVICE)
    model.classifier.load_state_dict(head_state)
    model.to(DEVICE)
    model.eval()

    LOADED_MODELS[name] = {
        "model": model,
        "tokenizer": tokenizer,
        "label_encoder": label_encoder,
        "num_classes": num_classes,
        "word_to_label_indices": build_word_to_label_indices(label_encoder),
    }
    print(f"  -> ready ({num_classes} classes, {len(LOADED_MODELS[name]['word_to_label_indices'])} known words)")

print(f"\nAll models loaded on {DEVICE}.\n{'=' * 60}\n")


# ── Inference ────────────────────────────────────────────────────────────
# Mirrors predict_sense() from the training notebook exactly: logits are
# restricted to the target word's own sense indices before softmax, since
# the model was trained as a single 650-way classifier but each word only
# ever has a handful of valid senses.
def predict_one(text: str, target_word: str, model_name: str, top_k: int = 5):
    entry = LOADED_MODELS[model_name]
    target_word = target_word.strip()

    valid_indices = entry["word_to_label_indices"].get(target_word)
    if valid_indices is None:
        return {"oov": True}

    marked = insert_tgt_markers(text.strip(), target_word)
    if marked is None:
        return None

    enc = entry["tokenizer"](
        marked,
        max_length=128,
        padding="max_length",
        truncation=True,
        return_tensors="pt",
    )
    input_ids = enc["input_ids"].to(DEVICE)
    attention_mask = enc["attention_mask"].to(DEVICE)

    t0 = time.perf_counter()
    with torch.no_grad():
        logits = entry["model"](input_ids, attention_mask)[0]
        valid_idx_t = torch.tensor(valid_indices, device=logits.device)
        restricted_probs = torch.softmax(logits[valid_idx_t], dim=-1)
    elapsed_ms = (time.perf_counter() - t0) * 1000

    best_local = restricted_probs.argmax().item()
    pred_idx_global = valid_indices[best_local]
    pred_label = entry["label_encoder"].inverse_transform([pred_idx_global])[0]
    confidence = restricted_probs[best_local].item()

    k = min(top_k, len(valid_indices))
    topk_local = restricted_probs.topk(k).indices.tolist()
    topk_global = [valid_indices[i] for i in topk_local]
    topk_labels = entry["label_encoder"].inverse_transform(topk_global)
    topk_probs = [restricted_probs[i].item() for i in topk_local]

    return {
        "label": pred_label,
        "confidence": confidence,
        "elapsed_ms": elapsed_ms,
        "top_k": list(zip(topk_labels, topk_probs)),
    }


def parse_sense(label: str, target_word: str) -> str:
    """'target_word_sense_gloss' -> 'sense gloss'."""
    prefix = target_word + "_"
    if label.startswith(prefix):
        return label[len(prefix):].replace("_", " ")
    return label.replace("_", " ")


# ── Benchmark results table & chart ─────────────────────────────────────
results_df = pd.read_csv(RESULTS_CSV)

_disp = results_df[
    ["Embedding", "Model", "Accuracy", "Macro F1", "Weighted F1",
     "Inference Time per Sample (ms)", "Model Size (MB)"]
].copy()
_disp["Accuracy"] = (_disp["Accuracy"] * 100).round(2).astype(str) + "%"
_disp["Macro F1"] = (_disp["Macro F1"] * 100).round(2).astype(str) + "%"
_disp["Weighted F1"] = (_disp["Weighted F1"] * 100).round(2).astype(str) + "%"
_disp["Inference Time per Sample (ms)"] = _disp["Inference Time per Sample (ms)"].round(3)
_disp["Model Size (MB)"] = _disp["Model Size (MB)"].round(1)
_disp = _disp.rename(columns={"Inference Time per Sample (ms)": "Infer (ms/sample)"})
RESULTS_DISPLAY = _disp.sort_values(
    "Accuracy", ascending=False, key=lambda s: s.str.rstrip("%").astype(float)
).reset_index(drop=True)


def make_benchmark_chart():
    fig, ax = plt.subplots(figsize=(11, 9))
    sub = results_df.copy()
    sub["acc_pct"] = (sub["Accuracy"] * 100).round(2)
    sub["label"] = sub["Embedding"] + " / " + sub["Model"]
    sub = sub.sort_values("acc_pct", ascending=True)

    colors = []
    for _, row in sub.iterrows():
        if "FineTuned" in row["Model"]:
            colors.append("#1565C0")
        elif row["Embedding"] == "TF-IDF":
            colors.append("#388E3C")
        elif row["Embedding"] in ("Word2Vec", "fastText"):
            colors.append("#F57C00")
        else:
            colors.append("#7B1FA2")

    bars = ax.barh(sub["label"], sub["acc_pct"], color=colors, height=0.65)
    ax.set_xlabel("Test Accuracy (%)", fontsize=11)
    ax.set_title("Bangla WSD Benchmark — All 44 Experiments", fontsize=13, fontweight="bold")
    ax.tick_params(axis="y", labelsize=7)

    for bar, val in zip(bars, sub["acc_pct"]):
        ax.text(val + 0.5, bar.get_y() + bar.get_height() / 2, f"{val:.1f}%", va="center", fontsize=6.5)

    legend_elements = [
        mpatches.Patch(color="#1565C0", label="Fine-Tuned Transformers"),
        mpatches.Patch(color="#388E3C", label="TF-IDF based"),
        mpatches.Patch(color="#F57C00", label="Word2Vec / fastText based"),
        mpatches.Patch(color="#7B1FA2", label="BERT / mBERT / BanglaBERT (frozen CLS)"),
    ]
    ax.legend(handles=legend_elements, loc="lower right", fontsize=8)
    ax.spines["top"].set_visible(False)
    ax.spines["right"].set_visible(False)
    plt.tight_layout()
    return fig


BENCHMARK_FIG = make_benchmark_chart()


# ── Example inputs pulled from the real dataset ─────────────────────────
sample = _dataset_df.groupby("target_word").first().reset_index()
sample = sample.sample(min(20, len(sample)), random_state=42)
EXAMPLES = [[row["text"], row["target_word"]] for _, row in sample.iterrows()]
print(f"Loaded {len(EXAMPLES)} example inputs from dataset_updated.xlsx")
print(f"Known target words ({len(KNOWN_WORDS)}): {', '.join(KNOWN_WORDS[:15])}, ...")


# ── Gradio callback ──────────────────────────────────────────────────────
def predict(text: str, target_word: str):
    text = (text or "").strip()
    target_word = (target_word or "").strip()

    if not text:
        return "⚠️ Please enter some Bangla text.", None, None, None, None
    if not target_word:
        return "⚠️ Please enter the target word to disambiguate.", None, None, None, None
    if target_word not in text:
        return (
            f'⚠️ Target word **"{target_word}"** was not found in the text. '
            "Check spelling — it must appear exactly as typed.",
            None, None, None, None,
        )
    if target_word not in KNOWN_WORDS:
        sample_words = ", ".join(KNOWN_WORDS[:25])
        return (
            f'⚠️ **"{target_word}"** was not one of the {len(KNOWN_WORDS)} words seen during '
            f"training, so the model has no senses to choose between for it. "
            f"Try one of: {sample_words}, ... (see the Known Words list below).",
            None, None, None, None,
        )

    results = {}
    for mname in LOADED_MODELS:
        r = predict_one(text, target_word, mname)
        if r and not r.get("oov"):
            results[mname] = r

    if not results:
        return "❌ Prediction failed for all models.", None, None, None, None

    rows = []
    for mname, r in results.items():
        cfg = MODEL_CONFIGS[mname]
        rows.append({
            "Model": mname,
            "Predicted Sense": parse_sense(r["label"], target_word),
            "Full Label": r["label"],
            "Confidence": f"{r['confidence'] * 100:.1f}%",
            "Benchmark Test Accuracy": f"{cfg['accuracy']:.2f}%",
            "Benchmark Macro F1": f"{cfg['macro_f1']:.2f}%",
            "Latency": f"{r['elapsed_ms']:.0f} ms",
        })
    comparison_df = pd.DataFrame(rows)

    fig, ax = plt.subplots(figsize=(7, 2.3))
    names = [r["Model"] for r in rows]
    confs = [float(r["Confidence"].rstrip("%")) for r in rows]
    colors = [MODEL_CONFIGS[n]["color"] for n in names]
    bars = ax.barh(names, confs, color=colors, height=0.45)
    ax.set_xlim(0, 105)
    ax.set_xlabel("Confidence (%)")
    ax.set_title(f'Prediction confidence — target word: "{target_word}"', fontweight="bold")
    for bar, c in zip(bars, confs):
        ax.text(c + 1, bar.get_y() + bar.get_height() / 2, f"{c:.1f}%", va="center", fontweight="bold")
    ax.spines["top"].set_visible(False)
    ax.spines["right"].set_visible(False)
    plt.tight_layout()

    top5 = {}
    for mname, r in results.items():
        top5[mname] = pd.DataFrame([
            {"Rank": i + 1, "Sense": parse_sense(lbl, target_word), "Probability": f"{p * 100:.2f}%"}
            for i, (lbl, p) in enumerate(r["top_k"])
        ])

    banglabert_top5 = top5.get("BanglaBERT-FineTuned", pd.DataFrame())
    mbert_top5 = top5.get("mBERT-FineTuned", pd.DataFrame())

    return "", comparison_df, fig, banglabert_top5, mbert_top5


# ── Build the Gradio UI ──────────────────────────────────────────────────
CSS = """
.header { text-align: center; padding: 22px; background: linear-gradient(135deg, #1565C0, #283593);
          border-radius: 12px; color: white; margin-bottom: 18px; }
.header h1 { font-size: 1.9rem; margin: 0; }
.header p  { margin: 4px 0 0; opacity: 0.9; }
"""

with gr.Blocks(title="Bangla WSD Demo", theme=gr.themes.Soft(), css=CSS) as demo:

    gr.HTML(
        """
        <div class="header">
            <h1>Bangla Word Sense Disambiguation</h1>
            <p>BanglaBERT-FineTuned (87.45% accuracy) vs mBERT-FineTuned (82.13% accuracy)</p>
            <p style="font-size:0.85rem; margin-top:6px;">Research benchmark · 44 experiments · 650+ word-sense classes</p>
        </div>
        """
    )

    with gr.Tabs():
        # ── Predict tab ──────────────────────────────────────────────
        with gr.Tab("Predict"):
            with gr.Row():
                with gr.Column(scale=2):
                    text_input = gr.Textbox(
                        label="Bangla Text",
                        placeholder="Enter a Bangla sentence containing the target word...",
                        lines=4,
                    )
                    target_input = gr.Textbox(
                        label="Target Word (the ambiguous word to disambiguate)",
                        placeholder="e.g. জল",
                    )
                    with gr.Row():
                        predict_btn = gr.Button("Predict Sense", variant="primary", scale=2)
                        clear_btn = gr.Button("Clear", scale=1)

                with gr.Column(scale=1):
                    gr.Markdown(
                        "### Model Info\n\n"
                        "| Model | Accuracy | Macro F1 | Params |\n"
                        "|---|---|---|---|\n"
                        "| **BanglaBERT-FineTuned** | **87.45%** | **85.57%** | 165M |\n"
                        "| mBERT-FineTuned | 82.13% | 78.40% | 179M |\n\n"
                        "**Task:** Word Sense Disambiguation (WSD)  \n"
                        "**Classes:** ~650 unique word-sense pairs  \n"
                        "**Architecture:** BERT backbone + LayerNorm → Linear(768) → GELU → Linear(num_classes)  \n"
                        "**Training:** 15 epochs, lr=2e-5 (backbone), lr=2e-4 (head)"
                    )

            error_box = gr.Markdown()
            comp_table = gr.Dataframe(label="Comparison Table", interactive=False)
            conf_chart = gr.Plot(label="Confidence Chart")

            with gr.Row():
                with gr.Column():
                    gr.Markdown("#### BanglaBERT-FineTuned — Top 5 Predictions")
                    banglabert_top5 = gr.Dataframe(interactive=False)
                with gr.Column():
                    gr.Markdown("#### mBERT-FineTuned — Top 5 Predictions")
                    mbert_top5 = gr.Dataframe(interactive=False)

            predict_btn.click(
                fn=predict,
                inputs=[text_input, target_input],
                outputs=[error_box, comp_table, conf_chart, banglabert_top5, mbert_top5],
            )
            target_input.submit(
                fn=predict,
                inputs=[text_input, target_input],
                outputs=[error_box, comp_table, conf_chart, banglabert_top5, mbert_top5],
            )
            clear_btn.click(
                fn=lambda: ("", None, None, None, None, "", ""),
                inputs=[],
                outputs=[error_box, comp_table, conf_chart, banglabert_top5, mbert_top5, text_input, target_input],
            )

            if EXAMPLES:
                gr.Markdown("### Example Inputs (from the training dataset)")
                gr.Examples(
                    examples=EXAMPLES,
                    inputs=[text_input, target_input],
                    label="Click a row to load it into the input fields, then press Predict Sense",
                )

            with gr.Accordion(f"Known Words ({len(KNOWN_WORDS)}) — only these words have learned senses", open=False):
                gr.Markdown(
                    "The model was trained on a fixed set of word-sense pairs. "
                    "Typing a target word outside this list will not produce a meaningful prediction.\n\n"
                    + ", ".join(KNOWN_WORDS)
                )

        # ── Benchmark tab ────────────────────────────────────────────
        with gr.Tab("Benchmark Results"):
            gr.Markdown("### All 44 Experiments — Sorted by Accuracy")
            gr.Plot(value=BENCHMARK_FIG, label="Accuracy Comparison (all experiments)")
            gr.Dataframe(value=RESULTS_DISPLAY, label="Full Results Table", interactive=False)

        # ── About tab ────────────────────────────────────────────────
        with gr.Tab("About"):
            gr.Markdown(
                """
## Bangla WSD Benchmark

This demo is part of a full research benchmark comparing **44 NLP model configurations**
for the task of **Bangla Word Sense Disambiguation (WSD)**.

### Task
Given a Bangla sentence and a target ambiguous word, predict which **sense** (meaning)
the word is used in. For example, "জল" can mean water, tears, or "hard work" (idiom),
depending on context.

### Models in this demo
| Model | Backbone | Fine-tuning | Test Accuracy |
|---|---|---|---|
| **BanglaBERT-FineTuned** | sagorsarker/bangla-bert-base | 15 epochs, end-to-end | **87.45%** |
| mBERT-FineTuned | bert-base-multilingual-cased | 15 epochs, end-to-end | 82.13% |

### Full Benchmark (44 experiments, see Benchmark Results tab)
- **6 embedding types:** TF-IDF, Word2Vec, fastText, BERT (CLS), mBERT (CLS), BanglaBERT (CLS)
- **7 classifiers per embedding:** Naive Bayes, Logistic Regression, Linear SVM, LSTM, GRU, BiLSTM, BiGRU
- **2 fine-tuned transformers:** mBERT-FineTuned, BanglaBERT-FineTuned

Only the two fine-tuned transformers are runnable in this live demo — the
TF-IDF vectorizer and Word2Vec/fastText embedding models used for the other
42 experiments were not exported alongside the saved classifier weights, so
they cannot process new text.

### Key Findings
- Fine-tuned transformers massively outperform classical embedding+classifier combinations
- BanglaBERT (Bangla-specific pre-training) beats multilingual BERT by ~5 points
- Best classical model: TF-IDF + LinearSVM (57.13% — 30 points below BanglaBERT)
- BanglaBERT-FineTuned ROC-AUC: 99.98% — near-perfect ranking of the correct sense

### Dataset
7,409 annotated Bangla sentences · 650+ unique word-sense classes · stratified 70/15/15 split
"""
            )

if __name__ == "__main__":
    demo.launch(server_name="127.0.0.1", server_port=7860, share=False, show_error=True)
