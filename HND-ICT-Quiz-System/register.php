<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old    = ['name' => '', 'username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']     = trim((string) ($_POST['name'] ?? ''));
    $old['username'] = trim((string) ($_POST['username'] ?? ''));
    $old['email']    = trim((string) ($_POST['email'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');
    $confirm         = (string) ($_POST['confirm'] ?? '');

    if (!csrf_check($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Reload the page and try again.';
    }
    $nameLen = function_exists('mb_strlen') ? mb_strlen($old['name']) : strlen($old['name']);
    if ($nameLen < 2 || $nameLen > 80) {
        $errors[] = 'Enter your full name (2 to 80 characters). It is printed on your certificates.';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $old['username'])) {
        $errors[] = 'Username must be 3 to 30 characters: letters, numbers, dot or underscore.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->prepare('INSERT INTO users (username, name, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$old['username'], $old['name'], $old['email'], password_hash($password, PASSWORD_BCRYPT), 'student', date('Y-m-d H:i:s')]);
            login_user(['id' => $pdo->lastInsertId(), 'username' => $old['username'], 'name' => $old['name'], 'role' => 'student']);
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $ex) {
            if (db_error_code($ex) === 1062) {
                $errors[] = 'That username or email is already registered. Sign in instead, or pick another.';
            } else {
                $errors[] = db_hint($ex);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth">
<div class="auth-grid">
    <section class="auth-hero" aria-label="About">
        <a class="brand light" href="index.php"><span class="bubble" aria-hidden="true"></span><span><?= e(APP_NAME) ?></span></a>
        <div class="hero-copy">
            <h1>Your name goes on the certificate.</h1>
            <p>Enter it the way you want it printed. You can take each quiz as many times as you like, and your best score counts.</p>
        </div>
        <ul class="rules">
            <li><strong><?= QUIZ_QUESTIONS ?></strong> questions per attempt</li>
            <li><strong><?= QUIZ_MINUTES ?></strong> minutes on the clock</li>
            <li><strong><?= PASS_PERCENT ?>%</strong> to pass a module</li>
        </ul>
    </section>

    <section class="auth-panel">
        <div class="auth-box">
            <h2>Create account</h2>
            <p class="muted">It takes about a minute.</p>

            <?php if ($errors): ?>
                <div class="alert err" role="alert">
                    <?php foreach ($errors as $m): ?><div><?= e($m) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <label class="field"><span>Full name</span>
                    <input type="text" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required autofocus></label>
                <div class="two">
                    <label class="field"><span>Username</span>
                        <input type="text" name="username" value="<?= e($old['username']) ?>" autocomplete="username" required></label>
                    <label class="field"><span>Email</span>
                        <input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required></label>
                </div>
                <div class="two">
                    <label class="field"><span>Password</span>
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label class="field"><span>Confirm password</span>
                        <input type="password" name="confirm" autocomplete="new-password" minlength="8" required></label>
                </div>
                <button type="submit" class="btn primary block">Create account</button>
            </form>
            <p class="switch">Already registered? <a href="index.php">Sign in</a></p>
        </div>
    </section>
</div>
</body>
</html>
