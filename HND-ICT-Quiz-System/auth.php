<?php
/**
 * Authentication + CSRF helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** The signed-in user as stored in the session (no database query needed). */
function current_user(): array
{
    return [
        'id'       => (int) ($_SESSION['user_id'] ?? 0),
        'username' => (string) ($_SESSION['username'] ?? ''),
        'name'     => (string) ($_SESSION['name'] ?? ''),
        'role'     => (string) ($_SESSION['role'] ?? 'student'),
    ];
}

function login_user(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $row['id'];
    $_SESSION['username'] = (string) $row['username'];
    $_SESSION['name']     = (string) $row['name'];
    $_SESSION['role']     = (string) $row['role'];
    unset($_SESSION['fails'], $_SESSION['lock_until']);
}

/**
 * Guard a page.  $role = 'admin' also requires the admin role.
 * $json = true answers API calls with JSON errors instead of redirecting.
 * $base is the path from the calling page to the project root ('' or '../').
 */
function require_login(string $role = '', bool $json = false, string $base = ''): void
{
    if (!is_logged_in()) {
        if ($json) {
            json_out(['error' => 'Please sign in again.'], 401);
        }
        header('Location: ' . $base . 'index.php');
        exit;
    }
    if ($role === 'admin' && !is_admin()) {
        if ($json) {
            json_out(['error' => 'Admins only.'], 403);
        }
        header('Location: ' . $base . 'dashboard.php');
        exit;
    }
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}
