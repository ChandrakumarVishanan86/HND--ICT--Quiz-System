<?php
/**
 * Public certificate check: anyone with a code can confirm it is genuine.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$raw   = (string) ($_GET['code'] ?? '');
$code  = strtoupper(preg_replace('/\s+/', '', trim($raw)));
$cert  = null;
$error = '';

if ($code !== '') {
    try {
        $st = db()->prepare('SELECT c.*, u.name, m.title FROM certificates c
                               JOIN users u ON u.id = c.user_id LEFT JOIN modules m ON m.id = c.module_id
                              WHERE c.code = ?');
        $st->execute([$code]);
        $cert = $st->fetch() ?: null;
    } catch (Throwable $ex) {
        $error = db_hint($ex);
    }
}

$base   = '';
$active = 'verify';
$title  = 'Verify a certificate';
if (is_logged_in()) {
    include __DIR__ . '/partials/header.php';
} else {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify a certificate | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar"><div class="wrap topbar-in">
    <a class="brand" href="index.php"><span class="bubble" aria-hidden="true"></span><span><?= e(APP_NAME) ?></span></a>
    <span></span>
    <a class="link-btn" href="index.php"><span>Sign in</span></a>
</div></header>
<main class="wrap page">
<?php } ?>

<section class="verify">
    <h1>Verify a certificate</h1>
    <p class="lead">Type the certificate number printed at the bottom of the certificate.</p>

    <form method="get" class="verify-form">
        <label class="field grow">
            <span>Certificate number</span>
            <input type="text" name="code" value="<?= e($raw) ?>" placeholder="HNDQ-XXXX-XXXX-XX" autocomplete="off" required>
        </label>
        <button class="btn primary" type="submit"><?= icon('search') ?>Check</button>
    </form>

    <?php if ($error !== ''): ?>
        <div class="alert err"><?= e($error) ?></div>
    <?php elseif ($code !== '' && !$cert): ?>
        <div class="alert err" role="alert">
            <strong>No certificate matches that number.</strong>
            Check for typing mistakes. Numbers look like <code>HNDQ-K7F3-9QXA-2M</code>.
        </div>
    <?php elseif ($cert): ?>
        <div class="alert ok verified" role="status">
            <?= icon('shield') ?>
            <div>
                <strong>Genuine certificate</strong>
                <dl class="facts">
                    <div><dt>Awarded to</dt><dd><?= e($cert['name']) ?></dd></div>
                    <div><dt>For</dt><dd><?= $cert['kind'] === 'programme' ? 'The full HND ICT practice quiz programme' : e($cert['title'] ?? 'Module') ?></dd></div>
                    <div><dt><?= $cert['kind'] === 'programme' ? 'Average score' : 'Score' ?></dt><dd><?= e(pct($cert['percent'])) ?></dd></div>
                    <div><dt>Issued</dt><dd><?= e(fmt_date($cert['issued_at'])) ?></dd></div>
                </dl>
                <a class="btn primary sm" href="certificate.php?code=<?= e($cert['code']) ?>">Open certificate</a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
