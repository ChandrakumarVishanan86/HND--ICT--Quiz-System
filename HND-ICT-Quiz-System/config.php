<?php
/**
 * Global configuration, session start and helpers.
 * WAMP defaults: MySQL user "root" with an empty password.
 * Works on PHP 7.4+ (needs the pdo_mysql and mbstring extensions - both on in WAMP).
 */
declare(strict_types=1);

const DB_HOST  = '127.0.0.1';        // 127.0.0.1 is faster and safer than "localhost" on Windows
const DB_PORT  = 3306;               // WAMP: MySQL is usually 3306, MariaDB sometimes 3307. 3306-3308 are tried automatically.
const DB_NAME  = 'hnd_quiz_db';
const DB_USER  = 'root';
const DB_PASS  = '';

const APP_NAME       = 'HND ICT Quiz';
const CERT_ISSUER    = 'HND ICT Quiz Academy';   // name printed on certificates
const QUIZ_QUESTIONS = 10;                       // questions drawn per attempt
const QUIZ_MINUTES   = 15;                       // time limit per attempt
const PASS_PERCENT   = 50;                       // pass mark
const GRACE_SECONDS  = 20;                       // network delay allowed when submitting

date_default_timezone_set('Asia/Colombo');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** MySQL error number behind a PDOException (1045 access denied, 1049 unknown DB, 2002 refused ...). */
function db_error_code(Throwable $e): int
{
    if ($e instanceof PDOException && isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1])) {
        return (int) $e->errorInfo[1];
    }
    if (preg_match('/\[(\d{4})\]/', $e->getMessage(), $m) || preg_match('/:\s(\d{4})\s/', $e->getMessage(), $m)) {
        return (int) $m[1];
    }
    return is_numeric($e->getCode()) ? (int) $e->getCode() : 0;
}

/** Plain-language "what to do" text for a database exception. */
function db_hint(Throwable $e): string
{
    if (!extension_loaded('pdo_mysql')) {
        return 'The PHP extension pdo_mysql is not enabled. In WAMP: left-click the tray icon > PHP > PHP extensions > tick php_pdo_mysql.';
    }
    switch (db_error_code($e)) {
        case 2002:
        case 2003:
        case 2006:
            return 'MySQL is not reachable on ' . DB_HOST . ':' . DB_PORT . '. Start WAMP (tray icon must be green) or set the right DB_PORT in config.php.';
        case 1045:
            return 'MySQL refused the login. Check DB_USER and DB_PASS in config.php (WAMP default: root with an empty password).';
        case 1049:
            return 'The database "' . DB_NAME . '" does not exist yet. Run the one-click installer (setup.php).';
        case 1146:
        case 1054:
            return 'The database exists but its tables are missing or outdated. Run the one-click installer (setup.php).';
        default:
            return 'Database error. Open setup.php to run the connection check.';
    }
}

/**
 * Open a PDO connection. $dbname = null connects to the server only (used by the installer).
 * If the configured port refuses the connection, the usual WAMP ports 3306/3307/3308 are tried.
 */
function db_connect(?string $dbname = DB_NAME): PDO
{
    $ports = array_values(array_unique([DB_PORT, 3306, 3307, 3308]));
    $first = null;
    foreach ($ports as $port) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . $port
             . ($dbname !== null ? ';dbname=' . $dbname : '') . ';charset=utf8mb4';
        try {
            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        } catch (PDOException $ex) {
            $first = $first ?? $ex;
            if (db_error_code($ex) !== 2002) {   // only "connection refused" means "try the next port"
                throw $ex;
            }
        }
    }
    throw $first;
}

/** Shared PDO connection to the quiz database. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connect(DB_NAME);
    }
    return $pdo;
}

/** HTML-escape helper. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** "12 November 2026" */
function fmt_date(?string $dt): string
{
    return $dt ? date('j F Y', strtotime($dt)) : '';
}

/** "12 Nov 2026, 14:05" */
function fmt_dt(?string $dt): string
{
    return $dt ? date('j M Y, H:i', strtotime($dt)) : '';
}

/** 87.5 -> "87.5%", 80.0 -> "80%" */
function pct($v): string
{
    $v = (float) $v;
    return rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
}

/** Inline SVG icon (stroke based). */
function icon(string $name): string
{
    static $paths = [
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'award'    => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>',
        'print'    => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
        'arrow'    => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'back'     => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
