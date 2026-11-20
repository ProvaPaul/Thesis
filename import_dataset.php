<?php
require_once __DIR__ . '/config.php';
require_role('admin');

$autoload = __DIR__ . '/vendor/autoload.php';
$hasSpreadsheet = file_exists($autoload);
if ($hasSpreadsheet) {
    require_once $autoload;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$hasSpreadsheet) {
        flash('danger', 'PhpSpreadsheet is missing. Run: composer install');
        header('Location: import_dataset.php');
        exit;
    }

    if (empty($_FILES['dataset']['tmp_name']) || $_FILES['dataset']['error'] !== UPLOAD_ERR_OK) {
        flash('danger', 'Please upload a valid .xlsx file.');
        header('Location: import_dataset.php');
        exit;
    }

    $extension = strtolower(pathinfo($_FILES['dataset']['name'], PATHINFO_EXTENSION));
    if ($extension !== 'xlsx') {
        flash('danger', 'Only .xlsx files are supported.');
        header('Location: import_dataset.php');
        exit;
    }

    $spreadsheet = IOFactory::load($_FILES['dataset']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);
    $headerRow = array_shift($rows);
    $headers = array_map(static fn($value) => trim((string) $value), array_values($headerRow));

    $required = array_values(DATASET_COLUMNS);
    $missing = array_values(array_diff($required, $headers));
    if ($missing) {
        flash('danger', 'Missing required column(s): ' . implode(', ', $missing));
        header('Location: import_dataset.php');
        exit;
    }

    $indexes = array_flip($headers);
    $inserted = 0;
    $skipped = 0;

    $stmt = db()->prepare(
        'INSERT IGNORE INTO dataset (source_row_hash, word_id, target_word, sense_id, sense_gloss, text)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    db()->beginTransaction();
    foreach ($rows as $row) {
        $values = array_values($row);
        $wordId = trim((string) ($values[$indexes['word_id']] ?? ''));
        $targetWord = trim((string) ($values[$indexes['target_word']] ?? ''));
        $senseId = trim((string) ($values[$indexes['sense_id']] ?? ''));
        $senseGloss = trim((string) ($values[$indexes['sense_gloss']] ?? ''));
        $text = trim((string) ($values[$indexes['text']] ?? ''));

        if ($targetWord === '' || $senseGloss === '' || $text === '') {
            $skipped++;
            continue;
        }

        $hash = hash('sha256', implode('|', [$wordId, $targetWord, $senseId, $senseGloss, $text]));
        $stmt->execute([$hash, $wordId, $targetWord, $senseId, $senseGloss, $text]);

        if ($stmt->rowCount() > 0) {
            $inserted++;
        } else {
            $skipped++;
        }
    }
    db()->commit();

    flash('success', "Import complete. Inserted {$inserted} row(s), skipped {$skipped} duplicate/invalid row(s).");
    header('Location: import_dataset.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Import Dataset</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <h1 class="h5">Bangla WSD</h1>
    <nav class="nav flex-column gap-1">
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link active" href="import_dataset.php">Import Dataset</a>
      <a class="nav-link" href="export_csv.php">Export CSV</a>
      <a class="nav-link" href="logout.php">Logout</a>
    </nav>
  </aside>
  <main class="content">
    <h2>Import Dataset</h2>
    <p class="text-muted">Required Excel columns: <?= e(implode(', ', array_values(DATASET_COLUMNS))) ?></p>
    <?php foreach (consume_flash() as $message): ?>
      <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <?php if (!$hasSpreadsheet): ?>
      <div class="alert alert-warning">PhpSpreadsheet is not installed. Run <code>composer install</code> in this folder.</div>
    <?php endif; ?>
    <div class="card">
      <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="vstack gap-3">
          <div>
            <label class="form-label" for="dataset">Excel file (.xlsx)</label>
            <input class="form-control" id="dataset" name="dataset" type="file" accept=".xlsx" required>
          </div>
          <button class="btn btn-primary" type="submit">Upload and Import</button>
        </form>
      </div>
    </div>
  </main>
</div>
</body>
</html>
