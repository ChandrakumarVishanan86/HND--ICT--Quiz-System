<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('admin', false, '../');

$error = '';
$s = ['students' => 0, 'attempts' => 0, 'passed' => 0, 'certs' => 0, 'questions' => 0];
$perModule = $recent = [];
try {
    $pdo = db();
    $s['students']  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $row            = $pdo->query('SELECT COUNT(*) AS n, COALESCE(SUM(passed), 0) AS p FROM attempts WHERE submitted_at IS NOT NULL')->fetch();
    $s['attempts']  = (int) $row['n'];
    $s['passed']    = (int) $row['p'];
    $s['certs']     = (int) $pdo->query('SELECT COUNT(*) FROM certificates')->fetchColumn();
    $s['questions'] = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE active = 1')->fetchColumn();

    $perModule = $pdo->query('SELECT m.title, COUNT(a.id) AS n, COALESCE(SUM(a.passed), 0) AS p, COALESCE(AVG(a.percent), 0) AS avgp
                                FROM modules m LEFT JOIN attempts a ON a.module_id = m.id AND a.submitted_at IS NOT NULL
                               GROUP BY m.id, m.title, m.sort_order ORDER BY m.sort_order, m.id')->fetchAll();

    $recent = $pdo->query('SELECT a.id, a.percent, a.score, a.total, a.passed, a.submitted_at, u.name, m.title
                             FROM attempts a JOIN users u ON u.id = a.user_id JOIN modules m ON m.id = a.module_id
                            WHERE a.submitted_at IS NOT NULL ORDER BY a.id DESC LIMIT 8')->fetchAll();
} catch (Throwable $ex) {
    $error = db_hint($ex);
}
$rate = $s['attempts'] > 0 ? round($s['passed'] / $s['attempts'] * 100) : 0;

$base   = '../';
$area   = 'admin';
$active = 'overview';
$title  = 'Overview';
include __DIR__ . '/../partials/header.php';
?>
<h1>Overview</h1>
<?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<section class="stats" aria-label="Totals">
    <div class="stat"><strong><?= $s['students'] ?></strong><span>Students</span></div>
    <div class="stat"><strong><?= $s['attempts'] ?></strong><span>Quiz attempts</span></div>
    <div class="stat"><strong><?= $rate ?>%</strong><span>Attempts passed</span></div>
    <div class="stat"><strong><?= $s['certs'] ?></strong><span>Certificates issued</span></div>
    <div class="stat"><strong><?= $s['questions'] ?></strong><span>Active questions</span></div>
</section>

<div class="split">
    <section>
        <h2>Pass rate by module</h2>
        <div class="bars">
            <?php foreach ($perModule as $m):
                $n = (int) $m['n']; $r = $n > 0 ? round($m['p'] / $n * 100) : 0; ?>
                <div class="barrow">
                    <span><?= e($m['title']) ?></span>
                    <span class="muted"><?= $n > 0 ? $r . '% of ' . $n : 'No attempts' ?></span>
                    <span class="track"><i style="width: <?= $r ?>%"></i></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2>Latest attempts</h2>
        <div class="table-wrap" style="margin-top:12px">
            <table class="table">
                <thead><tr><th>Student</th><th>Module</th><th class="num">Score</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= e($r['name']) ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td class="num"><a href="../result.php?attempt=<?= (int) $r['id'] ?>"><?= e(pct($r['percent'])) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="3" class="muted">No attempts yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
