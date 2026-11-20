# Bangla Word Sense Disambiguation: A Large-Scale Benchmark of Embedding and Classifier Combinations

This repository contains the corpus, code, and experimental results for a thesis project introducing a large, reliability-checked Bangla (Bengali) Word Sense Disambiguation (WSD) corpus, along with a controlled 29-experiment benchmark spanning lexical baselines, static embeddings, recurrent architectures, and frozen/fine-tuned transformers.

**Authors:** Prova Rani Paul, Dr. K. M. Azharul Hasan
**Affiliation:** Dept. of Computer Science and Engineering, Khulna University of Engineering & Technology (KUET), Khulna, Bangladesh

---

## Overview

Word Sense Disambiguation is the task of selecting the correct sense of an ambiguous word given its context. Bangla, spoken by roughly 300 million people, is rich in polysemy, but prior Bangla WSD research relied on small, private datasets and hand-engineered features — no study had fine-tuned a language model for this task before this work.

This project closes that gap with two contributions:

1. **A new large-scale, human-annotated Bangla WSD corpus** — 12,059 sentences covering 402 ambiguous target words with 1,085 distinct sense glosses, the largest Bangla WSD corpus reported to date and the only one with quantified inter-annotator reliability.
2. **A controlled 29-experiment benchmark** — from lexical baselines to fully fine-tuned transformers, all sharing one fixed data split, one preprocessing pipeline, and one evaluation suite, so that performance differences reflect modeling choices alone.

## Key Results

| Highlight | Value |
|---|---|
| Best model | Fine-tuned BanglaBERT |
| Best accuracy | **89.72%** |
| Best weighted F1 | 88.97% |
| Best MCC | 0.897 |
| Frozen BanglaBERT + Logistic Regression | 89.30% accuracy at ~70× lower training cost |
| Best purely lexical baseline | TF-IDF + Linear SVM, 66.35% |
| Prior best reported Bangla WSD accuracy | 80.82% (100 words, 3,860 sentences) |

Fine-tuned BanglaBERT beats fine-tuned XLM-RoBERTa by 3.7 accuracy points and fine-tuned mBERT by 5.6 points, and outperforms the best purely lexical baseline by more than 23 points — while operating over a much harder task (373 target words, 1,048 sense classes) than any prior Bangla WSD study.

## The Corpus

- **12,059 sentences**, 402 unique ambiguous target words, 1,085 distinct sense glosses
- First 100 target words adopted from the public [Das Dawn et al. Bengali WSD dataset](https://www.kaggle.com/datasets/debapratimdasdawn/bengali-wsd-dataset); the remaining 302 words and all sentences/annotations are newly contributed
- Senses validated against the *Sansad Bangla Abhidhan* dictionary
- Two independent annotators labeled every sentence on a custom PHP/MySQL annotation platform (sentence deduplication by SHA-256, randomized sense ordering to reduce positional bias, one-label-per-sentence-per-annotator database constraint)
- **Inter-annotator reliability:** Cohen's κ = 0.836 and 0.874 (both "almost perfect" on the Landis–Koch scale) — the first reliability statistic ever reported for a Bangla WSD dataset
- After preprocessing, word-specific label construction (to eliminate cross-word sense-label collisions), and rare-class filtering: **11,151 sentences, 373 target words, 1,048 word–sense classes**
- Fixed **70/15/15** stratified train/validation/test split (seed 42), reused identically across every experiment: 7,805 / 1,673 / 1,673 sentences

### Corpus schema

Each row contains: `word_id`, `target_word`, `sense_id`, `sense_gloss`, `text`

| word_id | target_word | sense_id | sense_gloss | text |
|---|---|---|---|---|
| 114 | রূপ | 1 | আকৃতি (shape) | মেঘের রূপ ক্রমশ বদলে গিয়ে ঝড়ের আভাস দিচ্ছিল। |
| 114 | রূপ | 2 | সৌন্দর্য (beauty) | তার রূপ দেখে কবিরা অনেক কবিতা লিখেছেন। |
| 114 | রূপ | 3 | অবস্থা (state) | দেশের বর্তমান রূপ দেখে অনেকেই হতাশ। |

## Benchmark Methodology

### Text representations (6 total)
1. **TF-IDF** — word-level 1–2 grams, 30,000 max features, sublinear TF, fit on train split only
2. **Pretrained static embeddings** — Bangla Word2Vec (100-D) and Bengali fastText (300-D, `wiki.bn`), combined into target-aware features: `[left-context mean; target-word vector; right-context mean]` with a 5-word window
3. **Frozen transformer embeddings** — mBERT, BanglaBERT, XLM-R; `[CLS]` vector concatenated with mean hidden state over the `[TGT]`-marked target span (1,536-D)

### Classifiers and sequence models
- **Classical classifiers** (18 experiments): Naive Bayes, Logistic Regression, Linear SVM over all 6 representations
- **Recurrent architectures** (8 experiments): LSTM, GRU, BiLSTM, BiGRU applied only to Word2Vec and fastText (the two representations with real per-token sequences), hidden size 128, sequences padded/truncated to 20 tokens

### Transformer fine-tuning (3 experiments)
End-to-end fine-tuning of mBERT, BanglaBERT, and XLM-R on marked text (`[TGT] word [/TGT]`), with a shared classification head (LayerNorm → Dropout → Linear → GELU → Dropout → Linear), AdamW with discriminative learning rates, class-balanced cross-entropy, gradient clipping, bfloat16 mixed precision, and early stopping on validation accuracy. Trained on a Tesla T4 GPU (15.6 GB).

**Total: 18 + 8 + 3 = 29 experiments**, all sharing the same split, preprocessing, and evaluation suite.

### Evaluation protocol
Every model is scored with accuracy, macro/weighted precision-recall-F1, balanced accuracy, MCC, and Cohen's kappa, under two regimes:
- **Global evaluation** — over the full 1,048-class label space
- **Target-word-constrained evaluation** — inference-time restriction of predictions to the known target word's own candidate senses (the realistic deployment setting), which isolates cross-word errors from true within-word sense confusions

## Full Results

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

*(All values in %, except MCC. Test split: 1,673 sentences, global evaluation over all 1,048 classes.)*

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

## Key Findings

- **Representation quality dominates classifier choice.** Holding the classifier fixed at logistic regression, accuracy climbs from 63.72% (TF-IDF) to 89.30% (frozen BanglaBERT) — a 23-point spread that dwarfs any within-representation classifier difference.
- **Language-specific pretraining beats multilingual scale.** BanglaBERT outperforms both multilingual backbones (mBERT, XLM-R) in every configuration, frozen and fine-tuned.
- **Recurrent models underperform classical classifiers** on the same embeddings, likely due to the small number of training sentences per class (~7.4 on average) relative to sequence-model data requirements.
- **Fine-tuning is expensive relative to its gain.** Fine-tuned BanglaBERT (89.72%) beats frozen BanglaBERT + logistic regression (89.30%) by only 0.42 points, despite ~70× more training time.
- **Optimization sensitivity across backbones.** XLM-R fine-tuning collapsed under the learning-rate recipe that worked for mBERT/BanglaBERT and required a gentler, separately-tuned schedule — a caution for benchmarks that fine-tune all backbones under one blind recipe.

## Comparison with Prior Work

| Source | Words | Sentences/Docs | Reliability |
|---|---|---|---|
| Pal and Saha (2019) | 7 | 2,478 sent. | — |
| Nazah et al. (2017) | 50 | 160 sent. | — |
| Saha et al. (2019) | 100 | 3,860 sent. | — |
| Kaysar and Khan (2018) | unspec. | 100–1,600 patt. | — |
| Das Dawn et al. (2023) | 100 | 345 docs | — |
| **This work** | **402** | **12,059 sent.** | **κ = 0.836 / 0.874** |

## Limitations

- The sense inventory follows a single dictionary and averages 10.6 sentences per sense, so rare senses remain hard (macro F1 trails weighted F1 by ~12 points for all strong models).
- Validation and test splits do not contain all 1,048 classes (918 and 914 present, respectively); metrics are computed over the full label set so averages are not artificially inflated.
- Results are single-run on one fixed split by design; conclusions rest on pattern consistency across all 29 experiments rather than repeated runs.
- The corpus follows the lexical-sample (target-word) formulation rather than all-words WSD, since no adequately covered all-words Bangla sense inventory yet exists.

## Future Work

- Adding examples for rare senses to improve macro-level performance
- Parameter-efficient fine-tuning approaches (e.g., LoRA/adapters)
- Extending toward all-words Bangla WSD as sense-inventory coverage improves

## Repository Structure

```
.
├── data/                  # Corpus files (raw and processed splits)
├── src/
│   ├── preprocessing/     # Label construction, filtering, split generation
│   ├── representations/   # TF-IDF, Word2Vec/fastText, transformer embedding extraction
│   ├── classifiers/       # Naive Bayes, Logistic Regression, Linear SVM pipelines
│   ├── sequence_models/   # LSTM/GRU/BiLSTM/BiGRU training
│   └── transformers/      # Fine-tuning scripts for mBERT, BanglaBERT, XLM-R
├── results/               # Experiment logs, metrics, figures
├── notebooks/             # Exploratory analysis and result visualization
└── README.md
```

> Adjust this structure to match your actual repository layout.

## Requirements

- Python 3.9+
- PyTorch
- Hugging Face `transformers`
- `scikit-learn`
- `gensim` (for Word2Vec/fastText loading)
- Pretrained embeddings: [Bangla Word2Vec](https://github.com/sagorbrur/bangla_word2vec), [Bengali fastText (`wiki.bn`)](https://fasttext.cc/docs/en/pretrained-vectors.html)
- Pretrained transformers: `bert-base-multilingual-cased`, `sagorsarker/bangla-bert-base`, `xlm-roberta-base`

## Citation

If you use this corpus or benchmark in your work, please cite:

```bibtex
@article{paul_bangla_wsd,
  title   = {Bangla Word Sense Disambiguation: A Large-Scale Benchmark of Embedding and Classifier Combinations},
  author  = {Paul, Prova Rani and Hasan, K. M. Azharul},
  school  = {Khulna University of Engineering \& Technology},
  note    = {Thesis}
}
```

## Acknowledgments

The first 100 target words in this corpus are adopted from the public [Bengali WSD dataset by Das Dawn et al.](https://www.kaggle.com/datasets/debapratimdasdawn/bengali-wsd-dataset), gratefully acknowledged as the starting point of this work.

## License

Specify your preferred license here (e.g., MIT, CC BY 4.0 for the dataset).

## Contact

Prova Rani Paul — provapaul123@gmail.com
Dept. of Computer Science and Engineering, KUET, Khulna, Bangladesh
