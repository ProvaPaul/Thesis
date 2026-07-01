# Bangla WSD Demo App

A Gradio web UI for live-testing the **Bangla Word Sense Disambiguation (WSD)**
models trained in `../bangla_wsd_benchmark_all_artifacts/`. Built for a class
demo — type a Bangla sentence + the ambiguous word, and compare predictions
from both fine-tuned transformer models side by side.

## What's in this folder

| File | Purpose |
|---|---|
| `app.py` | The full Gradio app (model loading, inference, UI) |
| `requirements.txt` | Pinned dependency versions known to work together on this machine |
| `README.md` | This file |

It does **not** touch anything in `bangla_wsd_benchmark_all_artifacts/` or
the rest of your project — it only reads from it.

## How to launch

```bash
cd wsd_demo_app
pip install -r requirements.txt
python app.py
```

Then open **http://127.0.0.1:7860** in a browser. First load takes ~30-60s
(loading two ~650MB transformer checkpoints from disk into CPU memory).

> The app was already verified end-to-end on this machine — both models
> load and produce correct predictions on real dataset examples.

## What the app does

- **Predict tab**: enter a Bangla sentence and the target word to disambiguate,
  get a side-by-side comparison table (predicted sense, confidence, benchmark
  accuracy, latency) plus a confidence bar chart and top-5 sense breakdown
  for each model. Click any row in "Example Inputs" to auto-fill from the
  real training dataset.
- **Benchmark Results tab**: the full 44-experiment comparison table and
  accuracy chart from `results.csv`, for context on how the two live models
  stack up against every other embedding/classifier combination that was tried.
- **About tab**: task description, model details, dataset stats.

## Models used

Only the two **fine-tuned transformers** are wired up for live inference:

| Model | Test Accuracy | Macro F1 |
|---|---|---|
| BanglaBERT-FineTuned | 87.45% | 85.57% |
| mBERT-FineTuned | 82.13% | 78.40% |

**Why only these two:** the other 42 experiments (TF-IDF, Word2Vec, fastText,
and frozen BERT/mBERT/BanglaBERT CLS embeddings + classical/sequence
classifiers) need their fitted vectorizer / embedding model to process new
text. Those were saved to `/kaggle/working/artifacts/` during training but
that folder wasn't included in the exported artifacts — only the classifier
weights in `saved_models/` were. If you export `tfidf_vectorizer.pkl`,
`word2vec.model`, and `fasttext.model` from the Kaggle session, those rows
could be added.

## Important: the model only knows 181 words

The dataset only contains **181 target words** with **657 total word-sense
classes** (after dropping rare senses with <3 samples). The model was trained
as a single 657-way classifier, but each word only maps to a handful of valid
senses. The app **restricts predictions to the target word's own valid
senses** before running softmax — this exactly mirrors the `predict_sense()`
function in `bangla-wsd-final.ipynb` (cell 79), which does the same
restriction for the same reason.

If you type a target word outside this list, the app shows a warning instead
of a meaningless prediction. The full list of known words is in a collapsible
section ("Known Words") at the bottom of the Predict tab.

## Expected behavior, not a bug

The models are **87.45%** and **82.13%** accurate — not 100%. Occasionally a
prediction will be wrong, even confidently wrong, especially on senses that
are semantically close (e.g. "জল" used for rain vs. tears). This is normal
and is a faithful demonstration of the reported benchmark numbers, not an
app malfunction.

## Troubleshooting

- **Slow predictions (1-2s)**: the models run on CPU here (no CUDA detected).
  This is normal and fine for a live demo of single inputs.
- **`ImportError` about TensorFlow DLLs**: `app.py` already sets `USE_TF=0`
  / `USE_FLAX=0` so `transformers` never tries to load TensorFlow. If you
  still see this, make sure you're running the `app.py` in this folder
  (not a copy with that fix removed).
- **Gradio/FastAPI/Starlette version conflicts on `pip install`**: this
  environment had a newer `gradio` pull in incompatible `fastapi`/`starlette`/
  `huggingface-hub` versions. `requirements.txt` pins ranges that are known
  to resolve correctly together; if `pip` still installs a broken combo, force:
  `pip install "gradio>=4.40,<5.0" "huggingface-hub>=0.23.2,<1.0" "fastapi==0.115.5" "starlette==0.41.3"`
