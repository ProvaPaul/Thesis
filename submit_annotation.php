<?php
require_once __DIR__ . '/config.php';

$user = require_role('annotator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: annotation.php');
    exit;
}

$sentenceId = (int) ($_POST['sentence_id'] ?? 0);
$selectedSense = trim($_POST['selected_sense'] ?? '');

if ($sentenceId <= 0 || $selectedSense === '') {
    flash('danger', 'Please select a valid answer.');
    header('Location: annotation.php');
    exit;
}

$stmt = db()->prepare(
    'SELECT COUNT(*)
     FROM dataset d
     WHERE d.id = ?
       AND EXISTS (
         SELECT 1 FROM dataset s
         WHERE s.target_word = d.target_word AND s.sense_gloss = ?
       )'
);
$stmt->execute([$sentenceId, $selectedSense]);

if ((int) $stmt->fetchColumn() === 0) {
    flash('danger', 'The selected sense is not valid for this target word.');
    header('Location: annotation.php');
    exit;
}

try {
    $stmt = db()->prepare('INSERT INTO annotations (sentence_id, annotator_id, selected_sense) VALUES (?, ?, ?)');
    $stmt->execute([$sentenceId, (int) $user['id'], $selectedSense]);
    flash('success', 'Annotation saved.');
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        flash('warning', 'This sentence was already annotated and has been skipped.');
    } else {
        throw $exception;
    }
}

header('Location: annotation.php');
exit;
