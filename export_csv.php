<?php
require_once __DIR__ . '/config.php';
require_role('admin');

$annotators = db()->query("SELECT id, name FROM users WHERE role = 'annotator' ORDER BY id LIMIT 2")->fetchAll();
$rows = db()->query('SELECT id, target_word, text FROM dataset ORDER BY id')->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="wsd_annotations.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, ['sentence_id', 'target_word', 'sentence', 'annotator_1', 'annotator_2']);

$lookupStmt = db()->prepare(
    'SELECT selected_sense
     FROM annotations
     WHERE sentence_id = ? AND annotator_id = ?
     LIMIT 1'
);

foreach ($rows as $row) {
    $answers = [];
    foreach ([0, 1] as $position) {
        if (!isset($annotators[$position])) {
            $answers[] = '';
            continue;
        }
        $lookupStmt->execute([(int) $row['id'], (int) $annotators[$position]['id']]);
        $answers[] = (string) ($lookupStmt->fetchColumn() ?: '');
    }

    fputcsv($out, [(int) $row['id'], $row['target_word'], $row['text'], $answers[0], $answers[1]]);
}

fclose($out);
exit;
