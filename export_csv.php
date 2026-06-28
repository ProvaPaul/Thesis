<?php
require_once __DIR__ . '/config.php';
require_role('admin');

// Get all annotators
$annotators = db()->query("
    SELECT id, name
    FROM users
    WHERE role = 'annotator'
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

// Get all dataset rows (including actual sense)
$rows = db()->query("
    SELECT
        id,
        target_word,
        text,
        sense_gloss
    FROM dataset
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

// CSV headers
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="wsd_annotations.csv"');

$out = fopen('php://output', 'w');

// UTF-8 BOM (so Bangla displays correctly in Excel)
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Build CSV header
$header = [
    'sentence_id',
    'target_word',
    'sentence',
    'actual_sense'
];

// Add one column per annotator
foreach ($annotators as $annotator) {
    $header[] = $annotator['name'];
}

fputcsv($out, $header);

// Prepare lookup query
$lookupStmt = db()->prepare("
    SELECT selected_sense
    FROM annotations
    WHERE sentence_id = ?
      AND annotator_id = ?
    LIMIT 1
");

// Export every sentence
foreach ($rows as $row) {

    $csvRow = [
        (int)$row['id'],
        $row['target_word'],
        $row['text'],
        $row['sense_gloss']
    ];

    // Get annotation from every annotator
    foreach ($annotators as $annotator) {

        $lookupStmt->execute([
            (int)$row['id'],
            (int)$annotator['id']
        ]);

        $answer = $lookupStmt->fetchColumn();

        $csvRow[] = ($answer !== false) ? $answer : '';
    }

    fputcsv($out, $csvRow);
}

fclose($out);
exit;