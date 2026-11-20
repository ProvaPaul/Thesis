"""
kappa.py — Evaluation engine for Bangla WSD annotations.

Reads `wsd_annotations.csv` (produced by export_csv.php) and emits a single
JSON object on stdout describing accuracy + Cohen's Kappa for every
annotator column found in the file, so future annotators appear automatically.

Usage:
    python kappa.py path/to/wsd_annotations.csv

JSON shape:
    {
        "ok": true,
        "csv_path": "...",
        "annotators": [
            {
                "name": "Amit",
                "samples": 50,
                "accuracy": 0.874,
                "kappa": 0.874,
                "agreement_level": "Almost Perfect"
            },
            ...
        ],
        "totals": { "rows": 50, "annotators": 2 },
        "errors": []
    }
"""

import sys
import json
import traceback

import pandas as pd
from sklearn.metrics import cohen_kappa_score, accuracy_score


def agreement_level(kappa) -> str:
    """Landis & Koch (1977) interpretation of Cohen's Kappa.

    `kappa` may be `None` if sklearn returned NaN (e.g. only one class was
    observed). In that case we report "N/A" instead of crashing.
    """
    if kappa is None:
        return "N/A"
    try:
        k = float(kappa)
    except (TypeError, ValueError):
        return "N/A"
    if k != k:  # NaN safety net
        return "N/A"
    if k < 0.00:
        return "Poor"
    if k < 0.21:
        return "Slight"
    if k < 0.41:
        return "Fair"
    if k < 0.61:
        return "Moderate"
    if k < 0.81:
        return "Substantial"
    return "Almost Perfect"


def safe_float(value, default=0.0):
    """Convert a value to JSON-safe float. NaN / inf become `None`."""
    try:
        if value is None:
            return default
        f = float(value)
        if f != f or f in (float("inf"), float("-inf")):  # NaN or +/-inf
            return None
        return f
    except (TypeError, ValueError):
        return default


def evaluate(csv_path: str) -> dict:
    """Load CSV and compute metrics for every annotator column."""
    df = pd.read_csv(csv_path)

    # Identify annotator columns: everything after the fixed metadata columns.
    metadata = {"sentence_id", "target_word", "sentence", "actual_sense"}
    annotator_cols = [c for c in df.columns if c not in metadata]

    results = []
    errors = []

    for name in annotator_cols:
        # Need both a ground truth label AND a human label to compute anything.
        subset = df[["actual_sense", name]].dropna()

        if subset.empty or len(subset) < 2:
            # Skip annotators with no usable labels instead of crashing.
            results.append({
                "name": name,
                "samples": int(len(subset)),
                "accuracy": 0.0,
                "kappa": 0.0,
                "agreement_level": "N/A",
            })
            continue

        try:
            accuracy = accuracy_score(subset["actual_sense"], subset[name])
            kappa = cohen_kappa_score(subset["actual_sense"], subset[name])
        except Exception as exc:  # noqa: BLE001
            errors.append(f"{name}: {exc}")
            continue

        results.append({
            "name": name,
            "samples": int(len(subset)),
            "accuracy": safe_float(accuracy),
            "kappa": safe_float(kappa),
            "agreement_level": agreement_level(safe_float(kappa)),
        })

    return {
        "ok": True,
        "csv_path": csv_path,
        "annotators": results,
        "totals": {
            "rows": int(len(df)),
            "annotators": len(results),
        },
        "errors": errors,
    }


def main():
    csv_path = sys.argv[1] if len(sys.argv) > 1 else "wsd_annotations.csv"
    try:
        payload = evaluate(csv_path)
    except FileNotFoundError:
        payload = {"ok": False, "error": f"CSV not found: {csv_path}"}
    except Exception as exc:  # noqa: BLE001
        payload = {
            "ok": False,
            "error": str(exc),
            "trace": traceback.format_exc(),
        }

    # One JSON object, one line — easy for PHP json_decode to consume.
    # allow_nan=False guarantees we never emit raw `NaN` / `Infinity` tokens,
    # which would break strict JSON parsers.
    print(json.dumps(payload, ensure_ascii=False, allow_nan=False))


if __name__ == "__main__":
    main()