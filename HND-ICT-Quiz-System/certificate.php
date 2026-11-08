<?php
/**
 * Printable A4-landscape certificate. Anyone holding the code can open it (it is meant to be shared).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$code = strtoupper(preg_replace('/\s+/', '', trim((string) ($_GET['code'] ?? ''))));
$cert = null;
$mods = [];
$error = '';

try {
    $pdo = db();
    $st  = $pdo->prepare('SELECT c.*, u.name, m.title FROM certificates c
                            JOIN users u ON u.id = c.user_id LEFT JOIN modules m ON m.id = c.module_id
                           WHERE c.code = ?');
    $st->execute([$code]);
    $cert = $st->fetch() ?: null;

    if ($cert && $cert['kind'] === 'programme') {
        $st = $pdo->prepare('SELECT m.title, MAX(a.percent) AS best
                               FROM modules m JOIN attempts a ON a.module_id = m.id AND a.user_id = ? AND a.passed = 1
                              WHERE m.active = 1 GROUP BY m.id, m.title, m.sort_order ORDER BY m.sort_order, m.id');
        $st->execute([$cert['user_id']]);
        $mods = $st->fetchAll();
    }
} catch (Throwable $ex) {
    $error = db_hint($ex);
}

if (!$cert) {
    http_response_code($error ? 500 : 404);
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate not found | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/fonts.css"><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="auth"><div class="auth-box" style="margin:12vh auto;padding:0 20px;max-width:460px">
    <h1>Certificate not found</h1>
    <p class="muted"><?= $error !== '' ? e($error) : 'There is no certificate with that number. Check the code and try again.' ?></p>
    <a class="btn primary" href="verify.php">Verify a certificate</a>
</div></body></html>
<?php
    exit;
}

$isProg = $cert['kind'] === 'programme';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$verify = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/verify.php';
$mine   = is_logged_in() && current_user()['id'] === (int) $cert['user_id'];
$nameLen = function_exists('mb_strlen') ? mb_strlen($cert['name']) : strlen($cert['name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate <?= e($cert['code']) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/certificate.css">
</head>
<body class="cert-page">

<div class="cert-bar">
    <a class="link-btn" href="<?= $mine ? 'certificates.php' : (is_logged_in() ? 'dashboard.php' : 'verify.php') ?>"><?= icon('back') ?><span><?= $mine ? 'My certificates' : 'Back' ?></span></a>
    <span class="chip ok"><?= icon('shield') ?>Genuine, number <?= e($cert['code']) ?></span>
    <button class="btn primary sm" type="button" onclick="window.print()"><?= icon('print') ?>Print or save as PDF</button>
</div>

<div class="stage" id="stage">
<div class="cert<?= $isProg ? ' prog' : '' ?>" id="cert">
    <svg class="frame" viewBox="0 0 1123 794" aria-hidden="true">
        <defs>
            <pattern id="diamonds" width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                <rect width="14" height="14" fill="#0E2A47"/>
                <rect x="3" y="3" width="8" height="8" fill="none" stroke="#C9A24A" stroke-width="1.2"/>
            </pattern>
            <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#E7C877"/><stop offset=".5" stop-color="#B98A2E"/><stop offset="1" stop-color="#E2BF6B"/>
            </linearGradient>
        </defs>
        <rect x="26" y="26" width="1071" height="742" fill="none" stroke="url(#diamonds)" stroke-width="22"/>
        <rect x="46" y="46" width="1031" height="702" fill="none" stroke="url(#gold)" stroke-width="3"/>
        <rect x="56" y="56" width="1011" height="682" fill="none" stroke="#0E2A47" stroke-width="1"/>
        <?php foreach ([[56, 56], [1067, 56], [56, 738], [1067, 738]] as [$cx, $cy]): ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="13" fill="#0E2A47" stroke="url(#gold)" stroke-width="3"/>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="4.5" fill="url(#gold)"/>
        <?php endforeach; ?>
        <!-- faint watermark: the answer bubble, filled in -->
        <g opacity=".05" transform="translate(561 400)">
            <circle r="230" fill="none" stroke="#0E2A47" stroke-width="22"/>
            <circle r="150" fill="#0E2A47"/>
            <polyline points="-70,5 -20,55 80,-55" fill="none" stroke="#fff" stroke-width="26" stroke-linecap="round" stroke-linejoin="round"/>
        </g>
    </svg>

    <div class="inner">
        <p class="issuer"><?= e(CERT_ISSUER) ?></p>
        <h1 class="title"><?= $isProg ? 'Certificate of Achievement' : 'Certificate of Completion' ?></h1>
        <p class="line">This is to certify that</p>
        <p class="name <?= $nameLen > 26 ? 'long' : '' ?>"><?= e($cert['name']) ?></p>

        <?php if ($isProg): ?>
            <p class="line">has passed every module of the</p>
            <h2 class="module">HND ICT Practice Quiz Programme</h2>
            <ul class="mods">
                <?php foreach ($mods as $m): ?>
                    <li><span><?= e($m['title']) ?></span><b><?= e(pct($m['best'])) ?></b></li>
                <?php endforeach; ?>
            </ul>
            <p class="line small">with an average score of <b><?= e(pct($cert['percent'])) ?></b></p>
        <?php else: ?>
            <p class="line">has successfully completed the module</p>
            <h2 class="module"><?= e($cert['title'] ?? 'Module') ?></h2>
            <p class="line small">in the HND ICT practice quiz programme, with a score of <b><?= e(pct($cert['percent'])) ?></b></p>
        <?php endif; ?>

        <div class="foot">
            <div class="sig">
                <svg viewBox="0 0 160 46" aria-hidden="true"><path d="M4 34c10-26 20-30 24-22s-8 24 2 24 14-18 22-18-2 16 6 16 12-12 20-12 4 10 12 10 20-8 36-12" fill="none" stroke="#0E2A47" stroke-width="2" stroke-linecap="round"/></svg>
                <i></i><small>Programme Director</small>
            </div>

            <div class="seal" aria-hidden="true">
                <svg viewBox="0 0 120 120">
                    <?php
                    $pts = [];
                    for ($i = 0; $i < 48; $i++) {
                        $r = ($i % 2 === 0) ? 58 : 51;
                        $a = M_PI * 2 * $i / 48;
                        $pts[] = round(60 + $r * cos($a), 2) . ',' . round(60 + $r * sin($a), 2);
                    }
                    ?>
                    <polygon points="<?= implode(' ', $pts) ?>" fill="url(#gold)"/>
                    <circle cx="60" cy="60" r="43" fill="#0E2A47"/>
                    <circle cx="60" cy="60" r="38" fill="none" stroke="#E2BF6B" stroke-width="1.5"/>
                    <circle cx="60" cy="60" r="21" fill="#FFD84D"/>
                    <polyline points="50,60 57,67 71,52" fill="none" stroke="#0E2A47" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <div class="sig">
                <span class="date"><?= e(fmt_date($cert['issued_at'])) ?></span>
                <i></i><small>Date of issue</small>
            </div>
        </div>

        <p class="code">Certificate number <b><?= e($cert['code']) ?></b>. Verify it at <?= e($verify) ?></p>
        <p class="note">Awarded for a practice quiz programme. Not an official qualification.</p>
    </div>
</div>
</div>

<script src="assets/js/certificate.js"></script>
</body>
</html>
