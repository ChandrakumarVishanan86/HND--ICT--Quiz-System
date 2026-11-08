<?php
/**
 * POST (module=ID)   : start a new attempt, or resume the one that is still running
 * GET  ?attempt=ID   : the quiz player (questions are loaded by assets/js/quiz.js)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();

$user = current_user();

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? null)) {
            $_SESSION['flash'] = 'Your session expired. Try starting the quiz again.';
            header('Location: dashboard.php');
            exit;
        }
        $moduleId = (int) ($_POST['module'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM modules WHERE id = ? AND active = 1');
        $st->execute([$moduleId]);
        if (!$st->fetch()) {
            $_SESSION['flash'] = 'That module is not available.';
            header('Location: dashboard.php');
            exit;
        }

        $running = find_running_attempt($pdo, $user['id'], $moduleId);
        $id = $running ? (int) $running['id'] : create_attempt($pdo, $user['id'], $moduleId);
        if ($id === 0) {
            $_SESSION['flash'] = 'This module has no questions yet.';
            header('Location: dashboard.php');
            exit;
        }
        header('Location: quiz.php?attempt=' . $id);   // redirect so a refresh never starts a second attempt
        exit;
    }

    $attempt = get_attempt($pdo, (int) ($_GET['attempt'] ?? 0), $user['id']);
    if (!$attempt) {
        header('Location: dashboard.php');
        exit;
    }
    if ($attempt['submitted_at'] !== null) {
        header('Location: result.php?attempt=' . $attempt['id']);
        exit;
    }
    if (attempt_expired($attempt)) {                     // time ran out while the page was closed
        $res = grade_attempt($pdo, $attempt, []);
        if ($res && $res['certs']) {
            $_SESSION['new_certs'][(int) $attempt['id']] = $res['certs'];
        }
        header('Location: result.php?attempt=' . $attempt['id']);
        exit;
    }
} catch (Throwable $ex) {
    $_SESSION['flash'] = db_hint($ex);
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($attempt['module_title']) ?> quiz | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="quiz"
      data-attempt="<?= (int) $attempt['id'] ?>"
      data-csrf="<?= e(csrf_token()) ?>"
      data-pass="<?= PASS_PERCENT ?>">

<header class="q-top">
    <div class="q-top-in">
        <a class="q-exit" href="dashboard.php" title="Leave the quiz. Your answers are saved and the clock keeps running.">
            <?= icon('back') ?><span>Modules</span>
        </a>
        <div class="q-title">
            <strong><?= e($attempt['module_title']) ?></strong>
            <span id="qCount">Loading questions</span>
        </div>
        <div class="timer" id="timer" role="timer" aria-live="off"><?= icon('clock') ?><span id="timeText">--:--</span></div>
    </div>
    <div class="q-bar" aria-hidden="true"><span id="bar"></span></div>
</header>

<main class="q-main">
    <section class="q-sheet" id="sheet" aria-live="polite">
        <p class="muted" id="loading">Loading your questions...</p>
    </section>

    <aside class="q-side" aria-label="Answer sheet">
        <h2>Answer sheet</h2>
        <div class="pal" id="pal"></div>
        <ul class="legend">
            <li><span class="pb done"></span>Answered</li>
            <li><span class="pb flag"></span>Flagged</li>
            <li><span class="pb"></span>Blank</li>
        </ul>
        <button type="button" class="btn primary block" id="submitBtn">Submit answers</button>
    </aside>
</main>

<footer class="q-foot">
    <button type="button" class="btn ghost" id="prevBtn"><?= icon('back') ?>Previous</button>
    <button type="button" class="btn ghost" id="flagBtn" aria-pressed="false"><?= icon('flag') ?>Flag</button>
    <button type="button" class="btn primary" id="nextBtn">Next<?= icon('arrow') ?></button>
</footer>

<dialog id="confirm" class="dialog">
    <form method="dialog">
        <h2>Submit your answers?</h2>
        <p id="confirmText"></p>
        <div class="dialog-actions">
            <button value="cancel" class="btn ghost">Keep working</button>
            <button value="ok" class="btn primary" id="confirmOk">Submit now</button>
        </div>
    </form>
</dialog>

<script src="assets/js/quiz.js"></script>
</body>
</html>
