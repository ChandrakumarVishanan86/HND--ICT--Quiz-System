<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();

$user = current_user();
$id   = (int) ($_GET['attempt'] ?? 0);

try {
    $pdo     = db();
    $attempt = get_attempt($pdo, $id, is_admin() ? null : $user['id']);
    if (!$attempt) {
        header('Location: dashboard.php');
        exit;
    }
    if ($attempt['submitted_at'] === null) {
        header('Location: quiz.php?attempt=' . $attempt['id']);
        exit;
    }

    $st = $pdo->prepare('SELECT aq.position, aq.opt_order, aq.chosen, aq.is_correct,
                                q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct, q.explanation
                           FROM attempt_questions aq JOIN questions q ON q.id = aq.question_id
                          WHERE aq.attempt_id = ? ORDER BY aq.position');
    $st->execute([$attempt['id']]);
    $rows = $st->fetchAll();

    $st = $pdo->prepare('SELECT code, kind FROM certificates WHERE user_id = ? AND ((kind = ? AND module_id = ?) OR kind = ?)');
    $st->execute([$attempt['user_id'], 'module', $attempt['module_id'], 'programme']);
    $certs = [];
    foreach ($st->fetchAll() as $c) {
        $certs[$c['kind']] = $c['code'];
    }
} catch (Throwable $ex) {
    $_SESSION['flash'] = db_hint($ex);
    header('Location: dashboard.php');
    exit;
}

$newCerts = $_SESSION['new_certs'][$id] ?? [];
unset($_SESSION['new_certs'][$id]);

$passed = (int) $attempt['passed'] === 1;
$circ   = 2 * M_PI * 52;
$dash   = $circ * min(100, (float) $attempt['percent']) / 100;
$secs   = (int) $attempt['duration_sec'];

$base   = '';
$active = 'modules';
$title  = 'Result';
include __DIR__ . '/partials/header.php';
$letters = ['A', 'B', 'C', 'D'];
?>

<section class="result-head <?= $passed ? 'pass' : 'miss' ?>">
    <svg class="big-ring" viewBox="0 0 120 120" aria-hidden="true">
        <circle cx="60" cy="60" r="52" class="ring-bg"/>
        <circle cx="60" cy="60" r="52" class="ring-fg" stroke-dasharray="<?= round($dash, 2) ?> <?= round($circ, 2) ?>" transform="rotate(-90 60 60)"/>
        <text x="60" y="68" text-anchor="middle" class="big-t"><?= e(pct($attempt['percent'])) ?></text>
    </svg>
    <div>
        <h1><?= $passed ? 'Passed' : 'Not passed this time' ?></h1>
        <p class="lead">
            <?= e($attempt['module_title']) ?>: <strong><?= (int) $attempt['score'] ?> of <?= (int) $attempt['total'] ?></strong> correct
            in <?= floor($secs / 60) ?>m <?= str_pad((string) ($secs % 60), 2, '0', STR_PAD_LEFT) ?>s.
            The pass mark is <?= PASS_PERCENT ?>%.
        </p>
        <div class="actions">
            <?php if ($passed && isset($certs['module'])): ?>
                <a class="btn primary" href="certificate.php?code=<?= e($certs['module']) ?>"><?= icon('award') ?>View module certificate</a>
            <?php endif; ?>
            <?php if ($attempt['user_id'] == $user['id']): ?>
            <form method="post" action="quiz.php">
                <?= csrf_field() ?>
                <input type="hidden" name="module" value="<?= (int) $attempt['module_id'] ?>">
                <button type="submit" class="btn <?= $passed ? 'ghost' : 'primary' ?>"><?= $passed ? 'Try for a higher score' : 'Retake quiz' ?></button>
            </form>
            <?php endif; ?>
            <a class="btn ghost" href="dashboard.php">All modules</a>
        </div>
    </div>
</section>

<?php if ($newCerts): ?>
<div class="alert ok" role="status">
    <?= icon('award') ?>
    <div>
        <strong><?= count($newCerts) > 1 ? 'You earned two new certificates.' : 'You earned a new certificate.' ?></strong>
        <?php foreach ($newCerts as $code): ?>
            <a class="alert-link" href="certificate.php?code=<?= e($code) ?>"><?= e($code) ?></a>
        <?php endforeach; ?>
        <?php if (isset($certs['programme']) && in_array($certs['programme'], $newCerts, true)): ?>
            <div>That includes your programme certificate: every module is now passed.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<section class="review">
    <h2>Review</h2>
    <?php foreach ($rows as $r):
        $opts  = [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']];
        $order = array_map('intval', explode(',', $r['opt_order']));
        $correctPos = array_search((int) $r['correct'], $order, true);
        $chosen = $r['chosen'] !== null ? (int) $r['chosen'] : null;
    ?>
    <article class="rq <?= $chosen === null ? 'blank' : ($r['is_correct'] ? 'right' : 'wrong') ?>">
        <header>
            <span class="qn"><?= (int) $r['position'] ?></span>
            <h3><?= e($r['question']) ?></h3>
            <span class="chip <?= $chosen === null ? 'idle' : ($r['is_correct'] ? 'ok' : 'no') ?>">
                <?= $chosen === null ? 'Not answered' : ($r['is_correct'] ? 'Correct' : 'Wrong') ?>
            </span>
        </header>
        <ul class="opts">
            <?php foreach ($order as $pos => $orig):
                $cls = '';
                if ($pos === $correctPos) { $cls = 'is-correct'; }
                elseif ($chosen === $pos) { $cls = 'is-wrong'; }
            ?>
            <li class="opt <?= $cls ?> <?= $chosen === $pos ? 'picked' : '' ?>">
                <span class="b"><?= $letters[$pos] ?></span>
                <span class="opt-text"><?= e($opts[$orig]) ?></span>
                <?php if ($pos === $correctPos): ?><span class="mark"><?= icon('check') ?><span class="sr">Correct answer</span></span>
                <?php elseif ($chosen === $pos): ?><span class="mark"><?= icon('x') ?><span class="sr">Your answer</span></span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($r['explanation'] !== ''): ?>
            <p class="why"><strong>Why:</strong> <?= e($r['explanation']) ?></p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
