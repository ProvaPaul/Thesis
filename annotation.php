<?php
require_once __DIR__ . '/config.php';

$user = require_role('annotator');
$annotatorId = (int) $user['id'];

$_SESSION['skipped_sentence_ids'] ??= [];
if (isset($_GET['skip'])) {
    $skipId = (int) $_GET['skip'];
    if ($skipId > 0 && !in_array($skipId, $_SESSION['skipped_sentence_ids'], true)) {
        $_SESSION['skipped_sentence_ids'][] = $skipId;
    }
    header('Location: annotation.php');
    exit;
}

$total = (int) db()->query('SELECT COUNT(*) FROM dataset')->fetchColumn();
$stmt = db()->prepare('SELECT COUNT(*) FROM annotations WHERE annotator_id = ?');
$stmt->execute([$annotatorId]);
$completed = (int) $stmt->fetchColumn();

$skippedIds = array_values(array_filter(array_map('intval', $_SESSION['skipped_sentence_ids'])));
$skipSql = $skippedIds ? 'AND d.id NOT IN (' . implode(',', array_fill(0, count($skippedIds), '?')) . ')' : '';
$params = array_merge([$annotatorId], $skippedIds);

$sql = 'SELECT d.*
     FROM dataset d
     WHERE NOT EXISTS (
       SELECT 1 FROM annotations a
       WHERE a.sentence_id = d.id AND a.annotator_id = ?
     )
     ' . $skipSql . '
     ORDER BY d.id
     LIMIT 1';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$sentence = $stmt->fetch();

if (!$sentence && $skippedIds && $completed < $total) {
    $_SESSION['skipped_sentence_ids'] = [];
    header('Location: annotation.php');
    exit;
}

$options = [];
if ($sentence) {
    $stmt = db()->prepare('SELECT DISTINCT sense_gloss FROM dataset WHERE target_word = ? ORDER BY sense_gloss');
    $stmt->execute([$sentence['target_word']]);
    $options = array_column($stmt->fetchAll(), 'sense_gloss');
    shuffle($options);
}

$percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Annotation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="annotation-page">
<nav class="navbar navbar-expand bg-white border-bottom">
  <div class="container">
    <span class="navbar-brand">Bangla WSD</span>
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="text-muted small"><?= e($user['name']) ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php foreach (consume_flash() as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
  <?php endforeach; ?>

  <div class="d-flex justify-content-between align-items-center mb-2">
    <span class="fw-semibold">Completed <?= number_format($completed) ?> / <?= number_format($total) ?></span>
    <span><?= $percent ?>%</span>
  </div>
  <div class="progress mb-4"><div class="progress-bar" style="width: <?= min($percent, 100) ?>%"></div></div>

  <?php if (!$sentence): ?>
    <div class="card annotation-card">
      <div class="card-body text-center py-5">
        <h1 class="h4">All sentences completed</h1>
        <p class="text-muted mb-0">There are no unfinished annotations for your account.</p>
      </div>
    </div>
  <?php else: ?>
    <article class="card annotation-card shadow-sm">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
          <span class="badge text-bg-light">Current Sentence #<?= (int) $sentence['id'] ?></span>
          <span class="badge text-bg-primary">Target Word: <?= e($sentence['target_word']) ?></span>
        </div>

        <h1 class="h5 mb-3">Sentence</h1>
        <p class="sentence-text"><?= highlight_target($sentence['text'], $sentence['target_word']) ?></p>

        <h2 class="h5 mt-4 mb-3">এখানে "<?= e($sentence['target_word']) ?>" শব্দটি কোন অর্থে ব্যবহৃত হয়েছে?</h2>

        <form method="post" action="submit_annotation.php" class="vstack gap-3">
          <input type="hidden" name="sentence_id" value="<?= (int) $sentence['id'] ?>">
          <?php foreach ($options as $index => $option): ?>
            <label class="option-row">
              <input class="form-check-input" type="radio" name="selected_sense" value="<?= e($option) ?>" required>
              <span><?= e($option) ?></span>
            </label>
          <?php endforeach; ?>
          <div class="d-flex flex-wrap gap-2 pt-2">
            <button class="btn btn-primary" type="submit">Submit</button>
            <a class="btn btn-outline-secondary" href="annotation.php?skip=<?= (int) $sentence['id'] ?>">Skip</a>
          </div>
        </form>
      </div>
    </article>
  <?php endif; ?>
</main>
<script src="assets/js/script.js"></script>
</body>
</html>
