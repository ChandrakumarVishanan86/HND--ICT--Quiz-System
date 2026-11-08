<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('admin', false, '../');

$error   = '';
$rows    = $modules = [];
$fModule = (int) ($_GET['module'] ?? 0);

try {
    $pdo     = db();
    $modules = $pdo->query('SELECT id, title FROM modules ORDER BY sort_order, id')->fetchAll();
    $par = [];
    $w   = 'WHERE a.submitted_at IS NOT NULL';
    if ($fModule > 0) {
        $w   .= ' AND a.module_id = ?';
        $par[] = $fModule;
    }
    $st = $pdo->prepare("SELECT a.id, a.percent, a.score, a.total, a.passed, a.duration_sec, a.submitted_at, u.name, u.username, m.title
                           FROM attempts a JOIN users u ON u.id = a.user_id JOIN modules m ON m.id = a.module_id
                         $w ORDER BY a.id DESC LIMIT 100");
    $st->execute($par);
    $rows = $st->fetchAll();
} catch (Throwable $ex) {
    $error = db_hint($ex);
}

$base   = '../';
$area   = 'admin';
$active = 'attempts';
$title  = 'Attempts';
include __DIR__ . '/../partials/header.php';
?>
<h1>Attempts</h1>
<?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<form method="get" class="toolbar">
    <label class="field"><span>Module</span>
        <select name="module" onchange="this.form.submit()">
            <option value="0">All modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $fModule === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
            <?php endforeach; ?>
        </select></label>
    <noscript><button class="btn ghost" type="submit">Filter</button></noscript>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Student</th><th>Module</th><th>Submitted</th><th class="num">Score</th><th class="num">Time</th><th>Result</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $d = (int) $r['duration_sec']; ?>
            <tr>
                <td><?= e($r['name']) ?> <span class="muted">(<?= e($r['username']) ?>)</span></td>
                <td><?= e($r['title']) ?></td>
                <td><?= e(fmt_dt($r['submitted_at'])) ?></td>
                <td class="num"><?= (int) $r['score'] ?>/<?= (int) $r['total'] ?> (<?= e(pct($r['percent'])) ?>)</td>
                <td class="num"><?= floor($d / 60) ?>m <?= str_pad((string) ($d % 60), 2, '0', STR_PAD_LEFT) ?>s</td>
                <td><span class="chip <?= $r['passed'] ? 'ok' : 'no' ?>"><?= $r['passed'] ? 'Passed' : 'Not passed' ?></span></td>
                <td class="num"><a href="../result.php?attempt=<?= (int) $r['id'] ?>">Review</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted">No attempts yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<p class="muted">Showing the latest 100 attempts.</p>
<?php include __DIR__ . '/../partials/footer.php'; ?>
