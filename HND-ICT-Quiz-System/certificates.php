<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();

$user  = current_user();
$error = '';
$list  = [];
$programme = null;
$active_modules = 0;
$best = [];
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT c.*, m.title FROM certificates c LEFT JOIN modules m ON m.id = c.module_id
                          WHERE c.user_id = ? ORDER BY c.issued_at DESC');
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $c) {
        if ($c['kind'] === 'programme') {
            $programme = $c;
        } else {
            $list[] = $c;
        }
    }
    $active_modules = (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE active = 1')->fetchColumn();
    $best = best_passed_scores($pdo, $user['id']);
} catch (Throwable $ex) {
    $error = db_hint($ex);
}

$base   = '';
$active = 'certificates';
$title  = 'Certificates';
include __DIR__ . '/partials/header.php';
?>

<h1>Your certificates</h1>
<p class="lead">Each passed module earns a certificate. Pass all <?= $active_modules ?> modules for the programme certificate.</p>

<?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<section class="cert-list">
    <?php if ($programme): ?>
        <a class="cert-row gold" href="certificate.php?code=<?= e($programme['code']) ?>">
            <?= icon('award') ?>
            <div>
                <strong>HND ICT programme certificate</strong>
                <span>All modules passed. Average <?= e(pct($programme['percent'])) ?>. Issued <?= e(fmt_date($programme['issued_at'])) ?>.</span>
            </div>
            <code><?= e($programme['code']) ?></code>
        </a>
    <?php else: ?>
        <div class="cert-row locked">
            <?= icon('lock') ?>
            <div>
                <strong>HND ICT programme certificate</strong>
                <span><?= count($best) ?> of <?= $active_modules ?> modules passed. <?= max(0, $active_modules - count($best)) ?> to go.</span>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($list as $c): ?>
        <a class="cert-row" href="certificate.php?code=<?= e($c['code']) ?>">
            <?= icon('award') ?>
            <div>
                <strong><?= e($c['title'] ?? 'Module') ?></strong>
                <span>Score <?= e(pct($c['percent'])) ?>. Issued <?= e(fmt_date($c['issued_at'])) ?>.</span>
            </div>
            <code><?= e($c['code']) ?></code>
        </a>
    <?php endforeach; ?>

    <?php if (!$list && !$programme): ?>
        <div class="empty">
            <strong>No certificates yet</strong>
            <span>Pass any module quiz and its certificate appears here.</span>
            <a class="btn primary sm" href="dashboard.php">Choose a module</a>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
