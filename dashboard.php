<?php
require_once __DIR__ . '/config.php';

$user = require_role('admin');
$q = trim($_GET['q'] ?? '');

$totalRows = (int) db()->query('SELECT COUNT(*) FROM dataset')->fetchColumn();
$totalAnnotators = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'annotator'")->fetchColumn();
$totalAnnotations = (int) db()->query('SELECT COUNT(*) FROM annotations')->fetchColumn();
$expected = $totalRows * max($totalAnnotators, 1);
$completion = $expected > 0 ? round(($totalAnnotations / $expected) * 100, 1) : 0;

$senseStats = db()->query('SELECT selected_sense, COUNT(*) total FROM annotations GROUP BY selected_sense ORDER BY total DESC LIMIT 10')->fetchAll();
$recent = db()->query(
    'SELECT a.created_at, u.name, d.target_word, a.selected_sense
     FROM annotations a
     JOIN users u ON u.id = a.annotator_id
     JOIN dataset d ON d.id = a.sentence_id
     ORDER BY a.created_at DESC
     LIMIT 8'
)->fetchAll();

$results = [];
if ($q !== '') {
    $stmt = db()->prepare(
        'SELECT id, target_word, sense_gloss, text
         FROM dataset
         WHERE target_word LIKE ? OR sense_gloss LIKE ? OR text LIKE ?
         ORDER BY id
         LIMIT 50'
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <h1 class="h5">Bangla WSD</h1>
    <nav class="nav flex-column gap-1">
      <a class="nav-link active" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="import_dataset.php">Import Dataset</a>
      <a class="nav-link" href="export_csv.php">Export CSV</a>
      <a class="nav-link" href="logout.php">Logout</a>
    </nav>
  </aside>
  <main class="content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <h2 class="mb-1">Admin Dashboard</h2>
        <p class="text-muted mb-0">Signed in as <?= e($user['name']) ?></p>
      </div>
      <a class="btn btn-outline-primary" href="export_csv.php">Export CSV</a>
    </div>

    <?php foreach (consume_flash() as $message): ?>
      <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>

    <section class="row g-3 mb-4">
      <div class="col-md-3"><div class="metric"><span>Total Rows</span><strong><?= number_format($totalRows) ?></strong></div></div>
      <div class="col-md-3"><div class="metric"><span>Annotators</span><strong><?= number_format($totalAnnotators) ?></strong></div></div>
      <div class="col-md-3"><div class="metric"><span>Annotations</span><strong><?= number_format($totalAnnotations) ?></strong></div></div>
      <div class="col-md-3"><div class="metric"><span>Completion</span><strong><?= $completion ?>%</strong></div></div>
    </section>

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <h3 class="h6 mb-0">Overall Progress</h3>
          <span><?= $completion ?>%</span>
        </div>
        <div class="progress">
          <div class="progress-bar" style="width: <?= min($completion, 100) ?>%"></div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <section class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h5">Annotation Statistics</h3>
            <canvas id="senseChart" height="220" data-labels='<?= e(json_encode(array_column($senseStats, 'selected_sense'), JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode(array_map('intval', array_column($senseStats, 'total')))) ?>'></canvas>
          </div>
        </div>
      </section>
      <section class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h3 class="h5">Recent Activity</h3>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead><tr><th>Annotator</th><th>Word</th><th>Sense</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                  <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['target_word']) ?></td>
                    <td><?= e($row['selected_sense']) ?></td>
                    <td><?= e($row['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="4" class="text-muted">No annotations yet.</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="card mt-4">
      <div class="card-body">
        <h3 class="h5">Search Dataset</h3>
        <form class="row g-2 mb-3" method="get">
          <div class="col-md-10"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search target word, sentence, or sense"></div>
          <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
        </form>
        <?php if ($q !== ''): ?>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Target Word</th><th>Sense</th><th>Sentence</th></tr></thead>
              <tbody>
              <?php foreach ($results as $row): ?>
                <tr><td><?= (int) $row['id'] ?></td><td><?= e($row['target_word']) ?></td><td><?= e($row['sense_gloss']) ?></td><td><?= e($row['text']) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$results): ?><tr><td colspan="4" class="text-muted">No matching rows found.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/script.js"></script>
</body>
</html>
