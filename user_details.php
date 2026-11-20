<?php
/**
 * user_details.php — Admin-only user & annotator management page.
 *
 * Two views driven by the `?id=` query parameter:
 *
 *   1. List view  (no / invalid `id`)
 *      - Responsive table of every non-admin user with their annotation
 *        statistics (submitted count, completion %, last submission, status).
 *      - Four summary cards underneath: total / completed / in-progress
 *        annotators and the average completion percentage.
 *
 *   2. Detail view (`?id=<annotator_id>`)
 *      - Annotator profile (name / email / role / registration date).
 *      - Annotation statistics card with a progress bar.
 *      - First 20 submitted annotations, joined with `dataset` so the
 *        target word is shown alongside each chosen sense.
 *
 * Notes:
 *   - All queries are read-only; nothing here modifies any existing data.
 *   - The page reuses the existing `app-shell` + sidebar layout and the
 *     shared `assets/css/style.css` so it visually matches the rest of the
 *     admin panel.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Admin-only access; redirects unauthenticated visitors to login and
// logged-in annotators back to their workspace.
$user = require_role('admin');

$pdo = db();

/**
 * Fetch the total number of rows in `dataset`.
 * Cached in a static so repeated calls within the same request are free.
 */
function total_dataset_rows(PDO $pdo): int
{
    static $cached = null;
    if ($cached === null) {
        $cached = (int) $pdo->query('SELECT COUNT(*) FROM dataset')->fetchColumn();
    }
    return $cached;
}

/**
 * Fetch how many annotations a single annotator has submitted.
 */
function annotator_submitted_count(PDO $pdo, int $annotatorId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM annotations WHERE annotator_id = ?');
    $stmt->execute([$annotatorId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Fetch the most recent annotation timestamp for a single annotator.
 * Returns null when the annotator has never submitted anything.
 */
function annotator_last_submission(PDO $pdo, int $annotatorId): ?string
{
    $stmt = $pdo->prepare('SELECT MAX(created_at) FROM annotations WHERE annotator_id = ?');
    $stmt->execute([$annotatorId]);
    $value = $stmt->fetchColumn();
    return $value !== null && $value !== false ? (string) $value : null;
}

/**
 * Build the row data shared by both views.
 * Combines users, submission counts, last submission time and computes the
 * derived completion percentage + status in PHP (kept out of SQL so MySQL
 * version differences don't affect the result).
 *
 * @return array<int,array<string,mixed>>
 */
function collect_user_rows(PDO $pdo): array
{
    $totalRows = total_dataset_rows($pdo);

    // Pull every non-admin user in registration order.
    $stmt = $pdo->query(
        "SELECT id, name, email, role, created_at
         FROM users
         WHERE role <> 'admin'
         ORDER BY created_at ASC, id ASC"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($users as $u) {
        $submitted  = annotator_submitted_count($pdo, (int) $u['id']);
        $lastSubmit = annotator_last_submission($pdo, (int) $u['id']);

        // Completion = submitted / total_dataset_rows * 100.
        // Avoid division by zero when the dataset is empty.
        $percent = $totalRows > 0
            ? round(($submitted / $totalRows) * 100, 1)
            : 0.0;

        // Status: Completed when the annotator has labelled every row.
        $status = ($totalRows > 0 && $submitted >= $totalRows)
            ? 'Completed'
            : 'In Progress';

        $rows[] = [
            'id'         => (int) $u['id'],
            'name'       => (string) $u['name'],
            'email'      => (string) $u['email'],
            'role'       => (string) $u['role'],
            'created_at' => (string) $u['created_at'],
            'submitted'  => $submitted,
            'percent'    => $percent,
            'last'       => $lastSubmit,
            'status'     => $status,
        ];
    }

    return $rows;
}

/**
 * Average completion percentage across the supplied rows.
 * Returns 0.0 when there are no rows so the UI never divides by zero.
 */
function average_completion(array $rows): float
{
    if (!$rows) {
        return 0.0;
    }
    $sum = 0.0;
    foreach ($rows as $r) {
        $sum += (float) $r['percent'];
    }
    return round($sum / count($rows), 1);
}

/**
 * Build the Bootstrap badge HTML for a status string.
 */
function status_badge(string $status): string
{
    if ($status === 'Completed') {
        return '<span class="badge text-bg-success">Completed</span>';
    }
    if ($status === 'In Progress') {
        return '<span class="badge text-bg-warning">In Progress</span>';
    }
    return '<span class="badge text-bg-secondary">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Build a Bootstrap progress bar HTML for a 0..100 percentage.
 */
function progress_bar(float $percent, string $extraClass = 'bg-success'): string
{
    $safe  = max(0.0, min(100.0, $percent));
    $label = number_format($safe, 1) . '%';
    return '<div class="progress" style="height: 10px;" role="progressbar" '
        . 'aria-valuenow="' . htmlspecialchars((string) $safe, ENT_QUOTES, 'UTF-8') . '" '
        . 'aria-valuemin="0" aria-valuemax="100">'
        . '<div class="progress-bar ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" '
        . 'style="width: ' . htmlspecialchars((string) $safe, ENT_QUOTES, 'UTF-8') . '%"></div>'
        . '</div>'
        . '<div class="small text-muted mt-1">' . $label . '</div>';
}

/**
 * Format a MySQL timestamp for display. Returns "—" for nulls.
 */
function fmt_timestamp(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '—';
    }
    return htmlspecialchars($ts, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Decide which view to render.
// ---------------------------------------------------------------------------
$detailId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detailRow = null;
$detailAnnotations = [];

if ($detailId > 0) {
    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, created_at
         FROM users
         WHERE id = ? AND role <> 'admin'
         LIMIT 1"
    );
    $stmt->execute([$detailId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($candidate) {
        $totalRows = total_dataset_rows($pdo);
        $submitted = annotator_submitted_count($pdo, $detailId);
        $last      = annotator_last_submission($pdo, $detailId);

        $percent = $totalRows > 0 ? round(($submitted / $totalRows) * 100, 1) : 0.0;
        $status  = ($totalRows > 0 && $submitted >= $totalRows) ? 'Completed' : 'In Progress';
        $remaining = max(0, $totalRows - $submitted);

        $detailRow = [
            'id'         => (int) $candidate['id'],
            'name'       => (string) $candidate['name'],
            'email'      => (string) $candidate['email'],
            'role'       => (string) $candidate['role'],
            'created_at' => (string) $candidate['created_at'],
            'submitted'  => $submitted,
            'remaining'  => $remaining,
            'percent'    => $percent,
            'last'       => $last,
            'status'     => $status,
        ];

        // First 20 annotations joined with the dataset to get the target word.
        $stmt = $pdo->prepare(
            'SELECT a.sentence_id, d.target_word, a.selected_sense, a.created_at
             FROM annotations a
             JOIN dataset d ON a.sentence_id = d.id
             WHERE a.annotator_id = ?
             ORDER BY a.sentence_id
             LIMIT 20'
        );
        $stmt->execute([$detailId]);
        $detailAnnotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* Page-scoped tweaks — kept inline to avoid touching the shared CSS. */
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
    .stat-label {
      color: var(--muted);
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .stat-value {
      font-size: 1.1rem;
      font-weight: 600;
    }
  </style>
</head>
<body>
<div class="app-shell">

  <!-- Sidebar: identical to the rest of the admin panel. -->
  <aside class="sidebar">
    <h1 class="h5">Bangla WSD</h1>
    <nav class="nav flex-column gap-1">
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="import_dataset.php">Import Dataset</a>
      <a class="nav-link" href="export_csv.php">Export CSV</a>
      <a class="nav-link" href="evaluation.php">Evaluation</a>
      <a class="nav-link active" href="user_details.php">User Details</a>
      <a class="nav-link" href="logout.php">Logout</a>
    </nav>
  </aside>

  <main class="content">

    <?php if ($detailRow !== null): ?>
      <!-- ============================================================ -->
      <!-- DETAIL VIEW: annotator profile + stats + first 20 annotations -->
      <!-- ============================================================ -->
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <h2 class="mb-1">User Details</h2>
          <p class="text-muted mb-0">
            Viewing annotator #<?= (int) $detailRow['id'] ?> ·
            <a href="user_details.php" class="link-secondary">← Back to all users</a>
          </p>
        </div>
        <a class="btn btn-outline-secondary" href="user_details.php">Back to list</a>
      </div>

      <div class="row g-4">

        <!-- Annotator information card -->
        <section class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="h5 mb-3">Annotator Information</h3>
              <dl class="row mb-0">
                <dt class="col-sm-4 stat-label">Name</dt>
                <dd class="col-sm-8 stat-value"><?= e($detailRow['name']) ?></dd>

                <dt class="col-sm-4 stat-label">Email</dt>
                <dd class="col-sm-8 stat-value"><?= e($detailRow['email']) ?></dd>

                <dt class="col-sm-4 stat-label">Role</dt>
                <dd class="col-sm-8 stat-value text-capitalize"><?= e($detailRow['role']) ?></dd>

                <dt class="col-sm-4 stat-label">Registration Date</dt>
                <dd class="col-sm-8 stat-value"><?= fmt_timestamp($detailRow['created_at']) ?></dd>
              </dl>
            </div>
          </div>
        </section>

        <!-- Annotation statistics card -->
        <section class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 mb-0">Annotation Statistics</h3>
                <?= status_badge($detailRow['status']) ?>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-6">
                  <div class="stat-label">Total Submitted</div>
                  <div class="stat-value"><?= number_format($detailRow['submitted']) ?></div>
                </div>
                <div class="col-6">
                  <div class="stat-label">Remaining</div>
                  <div class="stat-value"><?= number_format($detailRow['remaining']) ?></div>
                </div>
                <div class="col-6">
                  <div class="stat-label">Completion %</div>
                  <div class="stat-value"><?= number_format($detailRow['percent'], 1) ?>%</div>
                </div>
                <div class="col-6">
                  <div class="stat-label">Last Submission</div>
                  <div class="stat-value"><?= fmt_timestamp($detailRow['last']) ?></div>
                </div>
              </div>

              <?= progress_bar((float) $detailRow['percent']) ?>
            </div>
          </div>
        </section>
      </div>

      <!-- First 20 submitted annotations -->
      <section class="card mt-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h5 mb-0">First 20 Submitted Annotations</h3>
            <span class="text-muted small">
              Showing <?= count($detailAnnotations) ?> row(s)
            </span>
          </div>

          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th scope="col">Sentence ID</th>
                  <th scope="col">Target Word</th>
                  <th scope="col">Selected Sense</th>
                  <th scope="col">Submitted At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$detailAnnotations): ?>
                  <tr><td colspan="4" class="text-muted text-center py-4">No annotations submitted yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($detailAnnotations as $row): ?>
                    <tr>
                      <td>#<?= (int) $row['sentence_id'] ?></td>
                      <td class="fw-semibold"><?= e($row['target_word']) ?></td>
                      <td><?= e($row['selected_sense']) ?></td>
                      <td><?= fmt_timestamp((string) $row['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    <?php else: ?>
      <!-- ============================================================ -->
      <!-- LIST VIEW: every non-admin user + summary cards               -->
      <!-- ============================================================ -->

      <?php
        $rows = collect_user_rows($pdo);

        // Summary card aggregates — computed in PHP from the same $rows.
        $totalAnnotators    = count($rows);
        $completedCount     = 0;
        $inProgressCount    = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'Completed') {
                $completedCount++;
            } else {
                $inProgressCount++;
            }
        }
        $avgCompletion = average_completion($rows);
      ?>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <h2 class="mb-1">User Details</h2>
          <p class="text-muted mb-0">Signed in as <?= e($user['name']) ?></p>
        </div>
      </div>

      <!-- Summary cards -->
      <section class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
          <div class="metric-tile">
            <span>Total Annotators</span>
            <strong><?= number_format($totalAnnotators) ?></strong>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="metric-tile">
            <span>Completed Annotators</span>
            <strong class="text-success"><?= number_format($completedCount) ?></strong>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="metric-tile">
            <span>In Progress Annotators</span>
            <strong class="text-warning"><?= number_format($inProgressCount) ?></strong>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="metric-tile">
            <span>Average Completion %</span>
            <strong><?= number_format($avgCompletion, 1) ?>%</strong>
          </div>
        </div>
      </section>

      <!-- Users table -->
      <section class="card">
        <div class="card-body">
          <h3 class="h5 mb-3">All Annotators</h3>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th scope="col">ID</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Role</th>
                  <th scope="col">Registration Date</th>
                  <th scope="col" class="text-end">Total Submitted</th>
                  <th scope="col" style="min-width: 180px;">Completion %</th>
                  <th scope="col">Last Submission</th>
                  <th scope="col">Status</th>
                  <th scope="col" class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr><td colspan="10" class="text-muted text-center py-4">No annotators registered yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <tr>
                      <td>#<?= (int) $r['id'] ?></td>
                      <td class="fw-semibold"><?= e($r['name']) ?></td>
                      <td><?= e($r['email']) ?></td>
                      <td class="text-capitalize"><?= e($r['role']) ?></td>
                      <td><?= fmt_timestamp($r['created_at']) ?></td>
                      <td class="text-end"><?= number_format($r['submitted']) ?></td>
                      <td><?= progress_bar((float) $r['percent']) ?></td>
                      <td><?= fmt_timestamp($r['last']) ?></td>
                      <td><?= status_badge($r['status']) ?></td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="user_details.php?id=<?= (int) $r['id'] ?>">
                          View Details
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    <?php endif; ?>

  </main>
</div>
</body>
</html>
