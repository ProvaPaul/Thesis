<?php
/**
 * calculate_metrics.php — Bridge between the PHP app and the Python
 * evaluation engine (final/kappa/kappa.py).
 *
 * Workflow:
 *   1. Verify the caller is an admin (re-uses existing session helpers).
 *   2. Detect whether we are on the hosted environment (InfinityFree) —
 *      Python is not available there, so we return a friendly JSON message
 *      instead of failing noisily.
 *   3. Export annotations to a temporary CSV (re-using the same query that
 *      export_csv.php uses, but without streaming to the browser).
 *   4. Invoke the Python script with `shell_exec` and return its JSON.
 *
 * The endpoint always responds with `Content-Type: application/json`.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Guard: admin only — anonymous callers get a 403-style payload.
require_role('admin');

/**
 * Detect hosted environments where Python is unavailable.
 * Mirrors the heuristic used in config.php for the DB credentials.
 */
function is_hosted_environment(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return !($host === 'localhost' || strpos($host, '127.0.0.1') !== false);
}

/**
 * Build the same CSV that export_csv.php streams, but as an in-memory string.
 * Centralising this avoids the two paths drifting apart later.
 */
function build_annotations_csv(): string
{
    $pdo = db();

    // Same ordering & same column set as export_csv.php.
    $annotators = $pdo->query(
        "SELECT id, name FROM users WHERE role = 'annotator' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $rows = $pdo->query(
        "SELECT id, target_word, text, sense_gloss FROM dataset ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    // UTF-8 BOM so Bangla renders correctly downstream.
    $csv = "\xEF\xBB\xBF";

    $header = ['sentence_id', 'target_word', 'sentence', 'actual_sense'];
    foreach ($annotators as $annotator) {
        $header[] = $annotator['name'];
    }
    $csv .= implode(',', array_map(static fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $header)) . "\n";

    $lookup = $pdo->prepare(
        "SELECT selected_sense FROM annotations
         WHERE sentence_id = ? AND annotator_id = ? LIMIT 1"
    );

    foreach ($rows as $row) {
        $line = [
            (string) (int) $row['id'],
            $row['target_word'],
            $row['text'],
            $row['sense_gloss'],
        ];
        foreach ($annotators as $annotator) {
            $lookup->execute([(int) $row['id'], (int) $annotator['id']]);
            $answer = $lookup->fetchColumn();
            $line[] = $answer !== false ? (string) $answer : '';
        }
        $csv .= implode(',', array_map(static fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $line)) . "\n";
    }

    return $csv;
}

/**
 * Resolve a usable Python interpreter.
 * Returns the command name (e.g. `python` or `python3`) or empty string.
 */
function detect_python(): string
{
    foreach (['python', 'python3', 'py'] as $candidate) {
        // `where` (Windows) / `which` (Unix) — silent probe.
        $probe = stripos(PHP_OS, 'WIN') === 0
            ? shell_exec('where ' . escapeshellarg($candidate) . ' 2>NUL')
            : shell_exec('command -v ' . escapeshellarg($candidate) . ' 2>/dev/null');
        if (!empty($probe)) {
            return $candidate;
        }
    }
    return '';
}

if (is_hosted_environment()) {
    echo json_encode([
        'ok' => false,
        'available' => false,
        'message' => 'Evaluation is only available on the local server because Python execution is not supported on the hosting environment.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$python = detect_python();
if ($python === '') {
    echo json_encode([
        'ok' => false,
        'available' => false,
        'message' => 'Python interpreter not found on this server. Install Python 3 and the pandas / scikit-learn packages to enable evaluation.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$scriptPath = __DIR__ . '/final/kappa/kappa.py';
if (!is_file($scriptPath)) {
    echo json_encode([
        'ok' => false,
        'available' => true,
        'message' => 'Evaluation script not found at ' . $scriptPath,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Materialise the CSV on disk so Python can read it with pandas.
$tempDir = sys_get_temp_dir();
$csvPath = tempnam($tempDir, 'wsd_eval_') . '.csv';
file_put_contents($csvPath, build_annotations_csv());

// Redirect stderr to a temp file so Python warnings (e.g. sklearn
// "single label" / "invalid value" warnings) don't pollute stdout and
// break json_decode on the PHP side.
$stderrPath = tempnam($tempDir, 'wsd_eval_err_') . '.log';

$command = escapeshellcmd($python)
    . ' ' . escapeshellarg($scriptPath)
    . ' ' . escapeshellarg($csvPath)
    . ' 2> ' . escapeshellarg($stderrPath);

$output = shell_exec($command);
$stderr  = is_file($stderrPath) ? (string) file_get_contents($stderrPath) : '';

// Clean up the temp files regardless of success / failure.
@unlink($csvPath);
@unlink($stderrPath);

if ($output === null || $output === '') {
    echo json_encode([
        'ok' => false,
        'available' => true,
        'message' => 'Python script produced no output.',
        'command' => $command,
        'stderr' => $stderr,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Script crashed or printed a stack trace — surface it as JSON.
    echo json_encode([
        'ok' => false,
        'available' => true,
        'message' => 'Could not parse evaluation output.',
        'raw' => $output,
        'stderr' => $stderr,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Pass through the Python result, but also include any stderr warnings so
// the admin can see sklearn warnings (e.g. "single label found").
if (is_array($decoded) && $stderr !== '') {
    $decoded['stderr'] = $stderr;
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);