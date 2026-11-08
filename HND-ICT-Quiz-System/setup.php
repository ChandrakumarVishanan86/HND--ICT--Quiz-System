<?php
/**
 * One-click installer and connection check.
 *   - Not installed yet : anyone can run the install (there is nothing to lose).
 *   - Already installed : only a signed-in admin can see details or reinstall.
 * Imports database/hnd_quiz_db.sql, so phpMyAdmin is optional.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const SQL_FILE = __DIR__ . '/database/hnd_quiz_db.sql';
const TABLES   = ['users', 'modules', 'questions', 'attempts', 'attempt_questions', 'certificates'];

/** Split a SQL dump into single statements (quote and comment aware). */
function split_sql(string $sql): array
{
    $stmts = [];
    $buf   = '';
    $len   = strlen($sql);
    $q     = null;

    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($q !== null) {
            $buf .= $c;
            if ($c === '\\' && $q !== '`' && $i + 1 < $len) {
                $buf .= $sql[++$i];
            } elseif ($c === $q) {
                if ($i + 1 < $len && $sql[$i + 1] === $q) {
                    $buf .= $sql[++$i];
                } else {
                    $q = null;
                }
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $q = $c;
            $buf .= $c;
            continue;
        }
        if ($c === '-' && substr($sql, $i, 3) === '-- ') {
            $nl = strpos($sql, "\n", $i);
            $i  = ($nl === false) ? $len : $nl;
            $buf .= "\n";
            continue;
        }
        if ($c === ';') {
            $s = trim($buf);
            if ($s !== '') {
                $stmts[] = $s;
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    $s = trim($buf);
    if ($s !== '') {
        $stmts[] = $s;
    }
    return $stmts;
}

/** Create the database and import the dump. Returns the number of statements run. */
function run_install(): int
{
    $sql = @file_get_contents(SQL_FILE);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('database/hnd_quiz_db.sql was not found next to setup.php (or it is empty).');
    }
    $pdo = db_connect(null);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $n = 0;
    foreach (split_sql($sql) as $st) {
        if (preg_match('/^(CREATE\s+DATABASE|USE\s|SET\s+NAMES)/i', $st)) {
            continue;                       // handled above with the configured DB_NAME
        }
        $pdo->exec($st);
        $n++;
    }
    return $n;
}

/** Run every check. Returns ['checks' => [[label, ok, detail]], 'state' => string, 'counts' => [table => rows]]. */
function inspect_system(): array
{
    $checks = [];
    $counts = [];

    $phpOk    = version_compare(PHP_VERSION, '7.4.0', '>=');
    $checks[] = ['PHP version', $phpOk, PHP_VERSION . ($phpOk ? '' : '. PHP 7.4 or newer is required.')];
    $pdoOk    = extension_loaded('pdo_mysql');
    $checks[] = ['PHP extension pdo_mysql', $pdoOk, $pdoOk ? 'enabled' : 'Disabled. WAMP tray icon > PHP > PHP extensions > php_pdo_mysql.'];
    $mbOk     = extension_loaded('mbstring');
    $checks[] = ['PHP extension mbstring', $mbOk, $mbOk ? 'enabled' : 'Disabled. WAMP tray icon > PHP > PHP extensions > php_mbstring.'];

    if (!$phpOk || !$pdoOk) {
        return ['checks' => $checks, 'state' => 'noext', 'counts' => $counts];
    }

    try {
        $srv      = db_connect(null);
        $checks[] = ['MySQL server (' . DB_USER . '@' . DB_HOST . ')', true, 'connected, version ' . (string) $srv->query('SELECT VERSION()')->fetchColumn()];
    } catch (Throwable $ex) {
        $checks[] = ['MySQL server (' . DB_USER . '@' . DB_HOST . ':' . DB_PORT . ')', false, db_hint($ex)];
        return ['checks' => $checks, 'state' => 'noserver', 'counts' => $counts];
    }

    try {
        $pdo      = db_connect(DB_NAME);
        $checks[] = ['Database ' . DB_NAME, true, 'exists'];
    } catch (Throwable $ex) {
        $checks[] = ['Database ' . DB_NAME, false, 'not created yet. The installer will create it.'];
        return ['checks' => $checks, 'state' => 'nodb', 'counts' => $counts];
    }

    $missing = 0;
    foreach (TABLES as $t) {
        try {
            $counts[$t] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
            $checks[]   = ['Table ' . $t, true, number_format($counts[$t]) . ' rows'];
        } catch (Throwable $ex) {
            $missing++;
            $checks[] = ['Table ' . $t, false, 'missing'];
        }
    }
    if ($missing > 0) {
        return ['checks' => $checks, 'state' => 'notables', 'counts' => $counts];
    }

    $hasAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
    $checks[] = ['Admin account', $hasAdmin, $hasAdmin ? 'present (admin / admin123 unless you changed it)' : 'missing. Reinstall to create it.'];
    $modules  = (int) ($counts['modules'] ?? 0);
    $qs       = (int) ($counts['questions'] ?? 0);
    $contentOk = $modules > 0 && $qs > 0;
    $checks[] = ['Quiz content', $contentOk, $modules . ' modules, ' . $qs . ' questions'];

    return ['checks' => $checks, 'state' => ($hasAdmin && $contentOk) ? 'ready' : 'notables', 'counts' => $counts];
}

$error = '';
$done  = null;
$info  = inspect_system();
$installed = $info['state'] === 'ready';
$hasData   = array_sum($info['counts']) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif ($info['state'] === 'noext' || $info['state'] === 'noserver') {
        $error = 'Fix the failed checks first, then run the installer.';
    } elseif ($installed && (!is_admin() || empty($_POST['confirm']))) {
        $error = 'Sign in as admin and tick the confirmation box to reinstall.';
    } elseif (!$installed && $hasData && empty($_POST['confirm'])) {
        $error = 'Some tables already contain data. Tick the confirmation box to replace them.';
    } else {
        try {
            $done      = run_install();
            $info      = inspect_system();
            $installed = $info['state'] === 'ready';
            $hasData   = array_sum($info['counts']) > 0;
        } catch (Throwable $ex) {
            $error = $ex instanceof PDOException ? db_hint($ex) . ' (' . $ex->getMessage() . ')' : $ex->getMessage();
        }
    }
}

$canSee = !$installed || is_admin() || $done !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="setup">
    <h1>Setup and connection check</h1>

    <?php if ($error !== ''): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($done !== null): ?>
        <div class="alert ok" role="status">
            <div><strong>Database installed.</strong> <?= $done ?> statements ran. Sign in as <code>admin</code> / <code>admin123</code>, or create a student account.</div>
        </div>
    <?php endif; ?>

    <?php if (!$canSee): ?>
        <div class="alert ok"><div><strong>Everything is installed.</strong> Sign in as admin to see the details.</div></div>
        <p><a class="btn primary" href="index.php">Go to sign in</a></p>
    <?php else: ?>
        <ul class="checks">
            <?php foreach ($info['checks'] as [$label, $ok, $detail]): ?>
                <li>
                    <span class="pill <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? 'OK' : 'Fix' ?></span>
                    <div><b><?= e($label) ?></b><span><?= e($detail) ?></span></div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($installed): ?>
            <p><a class="btn primary" href="index.php">Go to sign in</a></p>
            <?php if (is_admin()): ?>
            <form method="post" class="q-form">
                <?= csrf_field() ?>
                <h2>Reinstall</h2>
                <p class="muted" style="margin-bottom:12px">This deletes every student, attempt and certificate and restores the sample questions.</p>
                <label class="check"><input type="checkbox" name="confirm" value="1" required> I understand all data will be replaced</label>
                <button class="btn ghost" type="submit">Reinstall database</button>
            </form>
            <?php endif; ?>
        <?php elseif (in_array($info['state'], ['nodb', 'notables'], true)): ?>
            <form method="post">
                <?= csrf_field() ?>
                <?php if ($hasData): ?>
                    <label class="check"><input type="checkbox" name="confirm" value="1" required> Some tables already contain data. Replace them with the sample data.</label>
                <?php endif; ?>
                <button class="btn primary" type="submit">Install database now</button>
            </form>
        <?php else: ?>
            <p class="muted">Fix the items marked "Fix", then reload this page.</p>
        <?php endif; ?>
    <?php endif; ?>

    <p class="muted" style="font-size:13px">Database settings live at the top of <code>config.php</code> (host, port, user, password).</p>
</main>
</body>
</html>
