<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_PORT = '3307';
const DB_NAME = 'bangla_wsd';
const DB_USER = 'root';
const DB_PASS = '';

const DATASET_COLUMNS = [
    'word_id' => 'word_id',
    'target_word' => 'target_word',
    'sense_id' => 'sense_id',
    'sense_gloss' => 'sense_gloss',
    'text' => 'text',
];

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        http_response_code(500);
        exit(
            '<!doctype html><html><head><meta charset="utf-8"><title>Database Connection Error</title>' .
            '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">' .
            '</head><body class="bg-light"><main class="container py-5">' .
            '<div class="alert alert-danger"><h1 class="h4">Database connection failed</h1>' .
            '<p class="mb-2">MySQL is not running or the database is not imported yet.</p>' .
            '<ol class="mb-0"><li>Open XAMPP Control Panel.</li><li>Start MySQL.</li>' .
            '<li>Open phpMyAdmin and import <code>database.sql</code>.</li>' .
            '<li>Check <code>config.php</code> if your MySQL password is not empty.</li></ol></div>' .
            '<p class="text-muted small">Original error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>' .
            '</main></body></html>'
        );
    }

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        header('Location: ' . ($user['role'] === 'admin' ? 'dashboard.php' : 'annotation.php'));
        exit;
    }

    return $user;
}

function redirect_for_role(array $user): void
{
    header('Location: ' . ($user['role'] === 'admin' ? 'dashboard.php' : 'annotation.php'));
    exit;
}

function highlight_target(string $text, string $targetWord): string
{
    $safeText = e($text);
    $safeTarget = e($targetWord);

    if ($safeTarget === '') {
        return $safeText;
    }

    return preg_replace('/' . preg_quote($safeTarget, '/') . '/u', '<mark>$0</mark>', $safeText, 1) ?? $safeText;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}
