<?php
/**
 * Sign in. Demo admin: admin / admin123. Students register themselves.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_admin() ? 'admin/index.php' : 'dashboard.php'));
    exit;
}

$error     = '';
$login     = '';
$showSetup = false;

// Tell the user straight away (before they type anything) if the database is not ready.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $ex) {
        $error     = db_hint($ex);
        $showSetup = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login     = trim((string) ($_POST['login'] ?? ''));
    $password  = (string) ($_POST['password'] ?? '');
    $now       = time();
    $lockUntil = (int) ($_SESSION['lock_until'] ?? 0);

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif ($now < $lockUntil) {
        $error = 'Too many failed attempts. Try again in ' . ($lockUntil - $now) . ' seconds.';
    } else {
        try {
            $st = db()->prepare('SELECT id, username, name, role, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$login, $login]);
            $user = $st->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                header('Location: ' . ($user['role'] === 'admin' ? 'admin/index.php' : 'dashboard.php'));
                exit;
            }

            $_SESSION['fails'] = (int) ($_SESSION['fails'] ?? 0) + 1;
            if ($_SESSION['fails'] >= 5) {
                $_SESSION['lock_until'] = $now + 60;
                $_SESSION['fails']      = 0;
            }
            $error = 'Username or password is wrong. Check both and try again.';
        } catch (PDOException $ex) {
            $error     = db_hint($ex);
            $showSetup = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
<div class="auth-grid">
    <section class="auth-hero" aria-label="About">
        <a class="brand light" href="index.php"><span class="bubble" aria-hidden="true"></span><span><?= e(APP_NAME) ?></span></a>

        <div class="hero-copy">
            <h1>Pass the quiz.<br>Earn the certificate.</h1>
            <p>Eight HND ICT modules, ten questions each. Score <?= PASS_PERCENT ?>% or more in every module and your programme certificate is issued automatically.</p>
        </div>

        <!-- A mock answer sheet: the same bubbles you will fill in during a quiz -->
        <div class="sheet" aria-hidden="true">
            <div class="sheet-row"><span class="sheet-n">1</span><span class="b">A</span><span class="b fill">B</span><span class="b">C</span><span class="b">D</span></div>
            <div class="sheet-row"><span class="sheet-n">2</span><span class="b">A</span><span class="b">B</span><span class="b">C</span><span class="b fill">D</span></div>
            <div class="sheet-row"><span class="sheet-n">3</span><span class="b fill">A</span><span class="b">B</span><span class="b">C</span><span class="b">D</span></div>
            <div class="sheet-row"><span class="sheet-n">4</span><span class="b">A</span><span class="b">B</span><span class="b fill">C</span><span class="b">D</span></div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-box">
            <h2>Sign in</h2>
            <p class="muted">Use your username or email.</p>

            <?php if ($error !== ''): ?>
                <div class="alert err" role="alert">
                    <?= e($error) ?>
                    <?php if ($showSetup): ?>
                        <a class="alert-link" href="setup.php">Open setup and connection check</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <label class="field">
                    <span>Username or email</span>
                    <input type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus>
                </label>
                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button type="submit" class="btn primary block">Sign in</button>
            </form>

            <p class="switch">New student? <a href="register.php">Create an account</a></p>
            <p class="fine">Admin demo: <code>admin</code> / <code>admin123</code></p>
        </div>
    </section>
</div>
</body>
</html>
