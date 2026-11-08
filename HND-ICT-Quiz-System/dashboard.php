<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();

$user  = current_user();
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$error = '';
$modules = $stats = $running = $certs = $recent = [];
$programme = null;
try {
    $pdo = db();

    $modules = $pdo->query('SELECT m.*, (SELECT COUNT(*) FROM questions q WHERE q.module_id = m.id AND q.active = 1) AS qcount
                              FROM modules m WHERE m.active = 1 ORDER BY m.sort_order, m.id')->fetchAll();

    $st = $pdo->prepare('SELECT module_id, COUNT(*) AS tries, MAX(percent) AS best, MAX(passed) AS passed
                           FROM attempts WHERE user_id = ? AND submitted_at IS NOT NULL GROUP BY module_id');
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $r) {
        $stats[(int) $r['module_id']] = $r;
    }

    $st = $pdo->prepare('SELECT module_id, started_at FROM attempts WHERE user_id = ? AND submitted_at IS NULL');
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $r) {
        if (!attempt_expired($r)) {
            $running[(int) $r['module_id']] = true;
        }
    }

    $st = $pdo->prepare('SELECT * FROM certificates WHERE user_id = ?');
    $st->execute([$user['id']]);
    foreach ($st->fetchAll() as $r) {
        if ($r['kind'] === 'programme') {
            $programme = $r;
        } else {
            $certs[(int) $r['module_id']] = $r;
        }
    }

    $st = $pdo->prepare('SELECT a.id, a.percent, a.score, a.total, a.passed, a.submitted_at, m.title
                           FROM attempts a JOIN modules m ON m.id = a.module_id
                          WHERE a.user_id = ? AND a.submitted_at IS NOT NULL ORDER BY a.id DESC LIMIT 6');
    $st->execute([$user['id']]);
    $recent = $st->fetchAll();
} catch (Throwable $ex) {
    $error = db_hint($ex);
}

$total  = count($modules);
$passed = 0;
foreach ($modules as $m) {
    if (!empty($stats[(int) $m['id']]['passed'])) {
        $passed++;
    }
}
$first = explode(' ', trim($user['name']))[0] ?: 'there';

$base   = '';
$active = 'modules';
$title  = 'Modules';
include __DIR__ . '/partials/header.php';
?>

<?php if ($error !== ''): ?>
    <div class="alert err"><?= e($error) ?> <a class="alert-link" href="setup.php">Open setup and connection check</a></div>
<?php endif; ?>
<?php if ($flash !== ''): ?>
    <div class="alert err" role="alert"><?= e($flash) ?></div>
<?php endif; ?>

<section class="progress-head">
    <div>
        <h1>Hello, <?= e($first) ?></h1>
        <p class="lead">
            <?php if ($total === 0): ?>
                No modules are available yet.
            <?php elseif ($passed === $total): ?>
                You have passed every module. Well done.
            <?php else: ?>
                You have passed <strong><?= $passed ?> of <?= $total ?></strong> modules.
                Pass <?= $total - $passed ?> more to earn your programme certificate.
            <?php endif; ?>
        </p>
        <div class="pips" role="img" aria-label="<?= $passed ?> of <?= $total ?> modules passed">
            <?php foreach ($modules as $m): ?>
                <span class="pip <?= !empty($stats[(int) $m['id']]['passed']) ? 'on' : '' ?>" title="<?= e($m['title']) ?>"></span>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="prog-cert <?= $programme ? 'earned' : '' ?>">
        <?= icon($programme ? 'award' : 'lock') ?>
        <div>
            <strong><?= $programme ? 'Programme certificate earned' : 'Programme certificate locked' ?></strong>
            <span><?= $programme ? 'Average score ' . e(pct($programme['percent'])) : 'Unlocks when all modules are passed.' ?></span>
        </div>
        <?php if ($programme): ?>
            <a class="btn primary sm" href="certificate.php?code=<?= e($programme['code']) ?>">View</a>
        <?php endif; ?>
    </aside>
</section>

<section class="modules" aria-label="Modules">
    <?php foreach ($modules as $m):
        $id    = (int) $m['id'];
        $s     = $stats[$id] ?? null;
        $best  = $s ? (float) $s['best'] : null;
        $ok    = $s && (int) $s['passed'] === 1;
        $isRun = !empty($running[$id]);
        $circ  = 2 * M_PI * 22;
        $dash  = $best !== null ? $circ * min(100, $best) / 100 : 0;
        $cls   = $ok ? 'pass' : ($best !== null ? 'miss' : 'none');
    ?>
    <article class="mod <?= $cls ?>">
        <svg class="ring" viewBox="0 0 52 52" aria-hidden="true">
            <circle cx="26" cy="26" r="22" class="ring-bg"/>
            <circle cx="26" cy="26" r="22" class="ring-fg" stroke-dasharray="<?= round($dash, 2) ?> <?= round($circ, 2) ?>" transform="rotate(-90 26 26)"/>
            <text x="26" y="30" text-anchor="middle" class="ring-t"><?= $best !== null ? (int) round($best) : '-' ?></text>
        </svg>

        <div class="mod-body">
            <h2><?= e($m['title']) ?></h2>
            <p><?= e($m['summary']) ?></p>
            <ul class="chips">
                <?php foreach (array_filter(explode(',', (string) $m['topics'])) as $t): ?><li><?= e(trim($t)) ?></li><?php endforeach; ?>
            </ul>
        </div>

        <div class="mod-side">
            <?php if ($ok): ?>
                <span class="stamp">Passed <?= e(pct($best)) ?></span>
            <?php elseif ($best !== null): ?>
                <span class="status">Best <?= e(pct($best)) ?>. Needs <?= PASS_PERCENT ?>%</span>
            <?php elseif ($isRun): ?>
                <span class="status">Quiz in progress</span>
            <?php else: ?>
                <span class="status"><?= min(QUIZ_QUESTIONS, (int) $m['qcount']) ?> questions, <?= QUIZ_MINUTES ?> min</span>
            <?php endif; ?>

            <div class="mod-actions">
                <?php if ((int) $m['qcount'] > 0): ?>
                <form method="post" action="quiz.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="module" value="<?= $id ?>">
                    <button type="submit" class="btn <?= $ok ? 'ghost' : 'primary' ?> sm">
                        <?= $isRun ? 'Resume quiz' : ($s ? 'Retake quiz' : 'Start quiz') ?>
                    </button>
                </form>
                <?php else: ?>
                    <span class="status">No questions yet</span>
                <?php endif; ?>
                <?php if (isset($certs[$id])): ?>
                    <a class="btn ghost sm" href="certificate.php?code=<?= e($certs[$id]['code']) ?>"><?= icon('award') ?>Certificate</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</section>

<?php if ($recent): ?>
<section class="recent">
    <h2>Recent attempts</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Module</th><th>Date</th><th class="num">Score</th><th>Result</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e(fmt_dt($r['submitted_at'])) ?></td>
                    <td class="num"><?= (int) $r['score'] ?>/<?= (int) $r['total'] ?> (<?= e(pct($r['percent'])) ?>)</td>
                    <td><span class="chip <?= $r['passed'] ? 'ok' : 'no' ?>"><?= $r['passed'] ? 'Passed' : 'Not passed' ?></span></td>
                    <td class="num"><a href="result.php?attempt=<?= (int) $r['id'] ?>">Review</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/partials/footer.php'; ?>
