<?php
/**
 * evaluation.php — Admin-only Evaluation Dashboard.
 *
 * Visualises per-annotator Accuracy + Cohen's Kappa + Agreement level
 * produced by calculate_metrics.php (which shells out to final/kappa/kappa.py).
 *
 * The page itself does no heavy computation — it just renders a Bootstrap
 * skeleton, then asks the browser to fetch metrics via the Fetch API when
 * the user clicks "Calculate Evaluation Metrics".
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Guard: admin only.
$user = require_role('admin');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Evaluation Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* Page-scoped styles — kept in <style> to avoid touching global CSS. */
    .metric-tile {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 18px;
    }
    .metric-tile span {
      display: block;
      color: var(--muted);
      font-size: 0.9rem;
    }
    .metric-tile strong {
      display: block;
      font-size: 1.6rem;
      margin-top: 6px;
    }
    .agreement-pill {
      display: inline-block;
      padding: 0.25rem 0.6rem;
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    .agreement-Poor { background: #fde2e1; color: #b42318; }
    .agreement-Slight { background: #fef0c7; color: #b54708; }
    .agreement-Fair { background: #fff6e0; color: #b54708; }
    .agreement-Moderate { background: #e0f2fe; color: #175cd3; }
    .agreement-Substantial { background: #d1fadf; color: #027a48; }
    .agreement-AlmostPerfect, .agreement-Almost { background: #c6f6d5; color: #027a48; }
    .agreement-NA { background: #f1f5f9; color: #475569; }

    .kappa-bar {
      height: 8px;
      background: #e2e8f0;
      border-radius: 4px;
      overflow: hidden;
    }
    .kappa-bar > div {
      height: 100%;
      background: linear-gradient(90deg, #1f6feb, #4cc2ff);
      transition: width 0.6s ease;
    }
  </style>
</head>
<body>
<div class="app-shell">

  <!-- Sidebar: re-uses the existing admin navigation pattern. -->
  <aside class="sidebar">
    <h1 class="h5">Bangla WSD</h1>
    <nav class="nav flex-column gap-1">
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="import_dataset.php">Import Dataset</a>
      <a class="nav-link" href="export_csv.php">Export CSV</a>
      <a class="nav-link active" href="evaluation.php">Evaluation</a>
      <a class="nav-link" href="logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">

    <!-- Header + action button. -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h2 class="mb-1">Evaluation Dashboard</h2>
        <p class="text-muted mb-0">Signed in as <?= e($user['name']) ?></p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="export_csv.php">Export CSV</a>
        <button id="calculateBtn" class="btn btn-primary">
          Calculate Evaluation Metrics
        </button>
      </div>
    </div>

    <!-- Loading indicator — hidden until the AJAX call starts. -->
    <div id="loadingPanel" class="card mb-4 d-none">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
        <div>
          <div class="fw-semibold">Calculating metrics…</div>
          <div class="text-muted small">Running Cohen's Kappa over all annotators. This may take a few seconds.</div>
        </div>
      </div>
    </div>

    <!-- Inline messages (errors / informational). -->
    <div id="messageArea"></div>

    <!-- Summary tiles — populated after metrics arrive. -->
    <section id="summaryRow" class="row g-3 mb-4 d-none">
      <div class="col-md-3">
        <div class="metric-tile">
          <span>Annotators</span>
          <strong id="sumAnnotators">0</strong>
        </div>
      </div>
      <div class="col-md-3">
        <div class="metric-tile">
          <span>Avg Accuracy</span>
          <strong id="sumAccuracy">0%</strong>
        </div>
      </div>
      <div class="col-md-3">
        <div class="metric-tile">
          <span>Avg Kappa</span>
          <strong id="sumKappa">0.000</strong>
        </div>
      </div>
      <div class="col-md-3">
        <div class="metric-tile">
          <span>Rows</span>
          <strong id="sumRows">0</strong>
        </div>
      </div>
    </section>

    <!-- Per-annotator results card. -->
    <section id="resultsCard" class="card mb-4 d-none">
      <div class="card-body">
        <h3 class="h5 mb-3">Per-Annotator Results</h3>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th scope="col">Annotator</th>
                <th scope="col" style="min-width: 220px;">Accuracy</th>
                <th scope="col">Accuracy %</th>
                <th scope="col">Cohen's Kappa</th>
                <th scope="col">Agreement Level</th>
                <th scope="col">Samples</th>
              </tr>
            </thead>
            <tbody id="resultsBody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Empty-state placeholder shown before the first calculation. -->
    <section id="emptyState" class="card">
      <div class="card-body text-center py-5">
        <h1 class="h5 mb-2">No metrics yet</h1>
        <p class="text-muted mb-3">Click <strong>Calculate Evaluation Metrics</strong> to compute accuracy and Cohen's Kappa for every annotator.</p>
      </div>
    </section>

  </main>
</div>

<script>
/**
 * Fetch-based client for calculate_metrics.php.
 * Renders results into Bootstrap cards/table without reloading the page.
 */
(function () {
  'use strict';

  const calculateBtn = document.getElementById('calculateBtn');
  const loadingPanel = document.getElementById('loadingPanel');
  const messageArea = document.getElementById('messageArea');
  const summaryRow = document.getElementById('summaryRow');
  const resultsCard = document.getElementById('resultsCard');
  const resultsBody = document.getElementById('resultsBody');
  const emptyState = document.getElementById('emptyState');

  const sumAnnotators = document.getElementById('sumAnnotators');
  const sumAccuracy = document.getElementById('sumAccuracy');
  const sumKappa = document.getElementById('sumKappa');
  const sumRows = document.getElementById('sumRows');

  /**
   * Build a CSS class for the agreement pill from the textual level.
   * Handles both "Almost Perfect" and the short "Almost" form.
   */
  function agreementClass(level) {
    if (!level || level === 'N/A') return 'agreement-NA';
    if (level.startsWith('Almost')) return 'agreement-AlmostPerfect';
    return 'agreement-' + level.replace(/\s+/g, '');
  }

  /** Render a flash-style Bootstrap alert into the message area. */
  function showMessage(type, text) {
    messageArea.innerHTML =
      '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        '<div>' + text + '</div>' +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
      '</div>';
  }

  function clearMessage() {
    messageArea.innerHTML = '';
  }

  /** Format a 0..1 ratio as a percentage string with two decimals. */
  function fmtPct(ratio) {
    const n = Number(ratio) || 0;
    return (n * 100).toFixed(2) + '%';
  }

  /** Format a 0..1 ratio with four decimals. `null`/`undefined` → N/A. */
  function fmtKappa(ratio) {
    if (ratio === null || ratio === undefined || Number.isNaN(Number(ratio))) {
      return 'N/A';
    }
    return Number(ratio).toFixed(4);
  }

  /**
   * Render the metrics returned by calculate_metrics.php.
   * Assumes `data.ok === true` and `data.annotators` is an array.
   */
  function renderResults(data) {
    const annotators = Array.isArray(data.annotators) ? data.annotators : [];

    if (annotators.length === 0) {
      showMessage('warning', 'No annotators found. Import a dataset and add annotator accounts first.');
      return;
    }

    // Average accuracy / kappa across all annotators for the summary tiles.
    // Null kappas (single-class cases) are skipped so they don't poison the mean.
    const validKappas = annotators
      .map((a) => Number(a.kappa))
      .filter((k) => Number.isFinite(k));
    const avgAccuracy = annotators.reduce((s, a) => s + (Number(a.accuracy) || 0), 0) / annotators.length;
    const avgKappa = validKappas.length
      ? validKappas.reduce((s, k) => s + k, 0) / validKappas.length
      : null;

    sumAnnotators.textContent = String(annotators.length);
    sumAccuracy.textContent = fmtPct(avgAccuracy);
    sumKappa.textContent = avgKappa === null ? 'N/A' : fmtKappa(avgKappa);
    sumRows.textContent = (data.totals && data.totals.rows) ? data.totals.rows : '—';

    // Build per-annotator rows.
    resultsBody.innerHTML = annotators.map(function (a) {
      const accuracyPct = (Number(a.accuracy) || 0) * 100;
      const width = Math.max(0, Math.min(100, accuracyPct)).toFixed(1) + '%';
      const agreementLevel = a.agreement_level || 'N/A';
      const pillClass = agreementClass(agreementLevel);
      const samples = a.samples != null ? a.samples : '—';

      return '' +
        '<tr>' +
          '<td class="fw-semibold">' + escapeHtml(a.name) + '</td>' +
          '<td>' +
            '<div class="progress" style="height: 10px;">' +
              '<div class="progress-bar bg-success" role="progressbar" style="width: ' + width + ';" aria-valuenow="' + accuracyPct.toFixed(1) + '" aria-valuemin="0" aria-valuemax="100"></div>' +
            '</div>' +
            '<div class="kappa-bar mt-2"><div style="width: ' + width + ';"></div></div>' +
          '</td>' +
          '<td>' + fmtPct(a.accuracy) + '</td>' +
          '<td>' + fmtKappa(a.kappa) + '</td>' +
          '<td><span class="agreement-pill ' + pillClass + '">' + escapeHtml(agreementLevel) + '</span></td>' +
          '<td>' + escapeHtml(String(samples)) + '</td>' +
        '</tr>';
    }).join('');

    emptyState.classList.add('d-none');
    summaryRow.classList.remove('d-none');
    resultsCard.classList.remove('d-none');
  }

  /** Minimal HTML escape for user-facing strings. */
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /** Main click handler — fires the AJAX request. */
  function onCalculateClick() {
    clearMessage();
    loadingPanel.classList.remove('d-none');
    calculateBtn.disabled = true;
    calculateBtn.textContent = 'Calculating…';

    fetch('calculate_metrics.php', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, data: data };
        });
      })
      .then(function (result) {
        const data = result.data || {};

        if (data.ok === true) {
          renderResults(data);
          if (data.errors && data.errors.length) {
            showMessage('warning', 'Metrics computed, but with warnings: ' + data.errors.join('; '));
          } else {
            showMessage('success', 'Evaluation metrics calculated successfully.');
          }
        } else if (data.available === false) {
          // Hosted environment — show a friendly info message, not an error.
          showMessage('info', data.message || 'Evaluation is not available in this environment.');
        } else {
          showMessage('danger', data.message || 'Evaluation failed for an unknown reason.');
        }
      })
      .catch(function (err) {
        showMessage('danger', 'Network error while calculating metrics: ' + (err && err.message ? err.message : err));
      })
      .finally(function () {
        loadingPanel.classList.add('d-none');
        calculateBtn.disabled = false;
        calculateBtn.textContent = 'Calculate Evaluation Metrics';
      });
  }

  calculateBtn.addEventListener('click', onCalculateClick);
})();
</script>
</body>
</html>
