# =====================================================================
# STEP 20 (NEW): BanglaBERT SPLIT-STABILITY EXPERIMENT
# ---------------------------------------------------------------------
# Paste this as ONE new code cell at the END of the notebook and run it
# AFTER everything above has executed (it reuses df, CFG, set_seed,
# try_full_stratified_3way, fallback_stratified_train_then_random_valtest,
# finetune_and_evaluate_transformer, results_store, detailed_results_store).
#
# Two modes:
#   'seeds'  -> SAME 70/15/15 ratio, 5 different random seeds.
#               This is the standard STABILITY test: report mean +/- std.
#   'ratios' -> 5 different train/val/test ratios (70/15/15, 75/15/10, ...).
#               This is a SENSITIVITY-to-split-size analysis. Note that the
#               training-set size changes, so a mild upward trend with more
#               training data is EXPECTED and is not instability.
#
# Results are checkpointed to CSV after every run, so if the Kaggle
# session dies you can re-run this cell and it resumes where it stopped.
# =====================================================================

STABILITY_MODE = 'ratios'          # <-- change to 'seeds' for the pure stability test

RATIO_CONFIGS = [                  # (train, val, test) — must sum to 1.0
    (0.70, 0.15, 0.15),
    (0.75, 0.15, 0.10),
    (0.80, 0.10, 0.10),
    (0.65, 0.15, 0.20),
    (0.60, 0.20, 0.20),
]
SEED_CONFIGS = [42, 101, 202, 303, 404]

STAB_CSV  = os.path.join(CFG.WORKING_DIR, f'banglabert_stability_{STABILITY_MODE}.csv')
STAB_PLOT = os.path.join(CFG.PLOTS_DIR,  f'banglabert_stability_{STABILITY_MODE}.png')
BANGLABERT_BACKBONE = CFG.FINETUNE_MODELS['BanglaBERT-FineTuned']


def make_split_for_stability(dataframe, val_size, test_size, seed):
    """Same split logic as Step 8, parameterized by ratio and seed."""
    try:
        tr, va, te = try_full_stratified_3way(dataframe, val_size, test_size, seed)
        fallback = False
    except ValueError:
        tr, va, te = fallback_stratified_train_then_random_valtest(
            dataframe, val_size, test_size, seed)
        fallback = True
    return (tr.reset_index(drop=True), va.reset_index(drop=True),
            te.reset_index(drop=True), fallback)


# -- Build the list of configs for the chosen mode ----------------------
if STABILITY_MODE == 'ratios':
    run_configs = [
        (f'{t*100:.0f}/{v*100:.0f}/{s*100:.0f}', v, s, CFG.SEED)
        for (t, v, s) in RATIO_CONFIGS
    ]
elif STABILITY_MODE == 'seeds':
    run_configs = [
        (f'70/15/15 seed={sd}', CFG.VAL_SIZE, CFG.TEST_SIZE, sd)
        for sd in SEED_CONFIGS
    ]
else:
    raise ValueError("STABILITY_MODE must be 'ratios' or 'seeds'")

# -- Resume support: skip configs already completed ---------------------
if os.path.exists(STAB_CSV):
    stab_df = pd.read_csv(STAB_CSV)
    done_configs = set(stab_df['config'].tolist())
    print(f'Resuming: found {len(done_configs)} completed run(s) in {STAB_CSV}')
else:
    stab_df = pd.DataFrame()
    done_configs = set()

# -- Main loop: split -> fine-tune BanglaBERT -> record -----------------
for cfg_name, v_size, t_size, seed in run_configs:
    if cfg_name in done_configs:
        print(f'Skipping "{cfg_name}" (already completed).')
        continue

    print(f'\n{"#" * 70}\n# STABILITY RUN: {cfg_name}\n{"#" * 70}')
    tr_df, va_df, te_df, fallback = make_split_for_stability(df, v_size, t_size, seed)
    print(f'Split sizes: train={len(tr_df)} ({len(tr_df)/len(df)*100:.1f}%) | '
          f'val={len(va_df)} ({len(va_df)/len(df)*100:.1f}%) | '
          f'test={len(te_df)} ({len(te_df)/len(df)*100:.1f}%) | '
          f'stratified-fallback used: {fallback}')

    display_name = f'BanglaBERT-FT [{cfg_name}]'
    row = finetune_and_evaluate_transformer(
        display_name, BANGLABERT_BACKBONE, tr_df, va_df, te_df,
    )

    # Keep the main thesis results tables clean: remove this auxiliary row
    # from the shared results stores (it lives in the stability CSV instead).
    for _store in (results_store, detailed_results_store):
        try:
            _store.remove_result('Fine-Tuned-Transformer', display_name)
        except Exception:
            pass

    rec = {
        'config': cfg_name,
        'seed': seed,
        'train_n': len(tr_df), 'val_n': len(va_df), 'test_n': len(te_df),
        'stratified_fallback': fallback,
        'Accuracy': row['Accuracy'],
        'Macro F1': row['Macro F1'],
        'Weighted F1': row['Weighted F1'],
        'Macro Precision': row['Macro Precision'],
        'Macro Recall': row['Macro Recall'],
    }
    stab_df = pd.concat([stab_df, pd.DataFrame([rec])], ignore_index=True)
    stab_df.to_csv(STAB_CSV, index=False)          # checkpoint after EVERY run
    print(f'Checkpointed {len(stab_df)} run(s) -> {STAB_CSV}')

    gc.collect()
    if torch.cuda.is_available():
        torch.cuda.empty_cache()

# -- Summary statistics -------------------------------------------------
metric_cols = ['Accuracy', 'Macro F1', 'Weighted F1', 'Macro Precision', 'Macro Recall']
print(f'\n{"=" * 70}\nBanglaBERT SPLIT-STABILITY SUMMARY ({STABILITY_MODE} mode)\n{"=" * 70}')
print(stab_df[['config', 'train_n', 'test_n'] + metric_cols]
      .assign(**{c: (stab_df[c] * 100).round(2) for c in metric_cols})
      .to_string(index=False))

summary = stab_df[metric_cols].agg(['mean', 'std', 'min', 'max']).T * 100
summary.columns = ['Mean (%)', 'Std (%)', 'Min (%)', 'Max (%)']
summary['Range (%)'] = summary['Max (%)'] - summary['Min (%)']
print('\nAggregate over all splits:')
print(summary.round(2).to_string())

summary.round(2).to_csv(os.path.join(CFG.WORKING_DIR,
                        f'banglabert_stability_{STABILITY_MODE}_summary.csv'))

# -- Thesis-ready sentence ---------------------------------------------
acc_m, acc_s = stab_df['Accuracy'].mean() * 100, stab_df['Accuracy'].std() * 100
f1_m,  f1_s  = stab_df['Macro F1'].mean() * 100, stab_df['Macro F1'].std() * 100
print(f'\nThesis-ready summary line:')
print(f'  Across {len(stab_df)} different train/validation/test partitions, fine-tuned '
      f'BanglaBERT achieved {acc_m:.2f}% (+/- {acc_s:.2f}) accuracy and '
      f'{f1_m:.2f}% (+/- {f1_s:.2f}) macro-F1.')
if STABILITY_MODE == 'seeds' and f1_s <= 2.0:
    print('  Std <= 2 percentage points -> performance is STABLE across data partitions.')
elif STABILITY_MODE == 'ratios':
    print('  NOTE (ratios mode): training-set size differs between configs, so some '
          'variation reflects data quantity, not instability. Use seeds mode for the '
          'formal stability claim.')

# -- Plot ---------------------------------------------------------------
fig, ax = plt.subplots(figsize=(10, 5.5))
x = np.arange(len(stab_df))
width = 0.38
ax.bar(x - width / 2, stab_df['Accuracy'] * 100, width, label='Accuracy')
ax.bar(x + width / 2, stab_df['Macro F1'] * 100, width, label='Macro F1')
ax.axhline(acc_m, ls='--', lw=1, color='tab:blue',
           label=f'Mean Acc = {acc_m:.2f}%')
ax.axhline(f1_m, ls='--', lw=1, color='tab:orange',
           label=f'Mean Macro F1 = {f1_m:.2f}%')
ax.set_xticks(x)
ax.set_xticklabels(stab_df['config'], rotation=20, ha='right')
ax.set_ylabel('Score (%)')
ax.set_xlabel('Train / Val / Test configuration')
ax.set_title(f'BanglaBERT fine-tuning: stability across {len(stab_df)} splits '
             f'({STABILITY_MODE} mode)')
ax.legend(fontsize=9)
ax.grid(axis='y', alpha=0.3)
plt.tight_layout()
plt.savefig(STAB_PLOT, dpi=150, bbox_inches='tight')
plt.show()
print(f'\nPlot saved -> {STAB_PLOT}')
