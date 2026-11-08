<?php
/**
 * Question bank: list, search, add, edit, delete.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('admin', false, '../');

$pdo   = null;
$error = '';
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
$errors = [];
$letters = ['A', 'B', 'C', 'D'];

function back_to_list(string $msg): void
{
    $_SESSION['admin_flash'] = $msg;
    header('Location: questions.php' . (!empty($_POST['return']) ? '?' . ltrim((string) $_POST['return'], '?') : ''));
    exit;
}

try {
    $pdo     = db();
    $modules = $pdo->query('SELECT id, code, title FROM modules ORDER BY sort_order, id')->fetchAll();
    $moduleIds = array_map('intval', array_column($modules, 'id'));

    $form = ['id' => 0, 'module_id' => (int) ($_GET['module'] ?? ($moduleIds[0] ?? 0)), 'question' => '',
             'opt' => ['', '', '', ''], 'correct' => 0, 'explanation' => '', 'active' => 1];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? null)) {
            $errors[] = 'Your session expired. Reload the page and try again.';
        } elseif (($_POST['action'] ?? '') === 'delete') {
            $qid  = (int) ($_POST['id'] ?? 0);
            $used = $pdo->prepare('SELECT COUNT(*) FROM attempt_questions WHERE question_id = ?');
            $used->execute([$qid]);
            if ((int) $used->fetchColumn() > 0) {   // used in past quizzes: hide it so old reviews stay complete
                $pdo->prepare('UPDATE questions SET active = 0 WHERE id = ?')->execute([$qid]);
                back_to_list('This question appears in past quizzes, so it was hidden instead of deleted.');
            }
            $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([$qid]);
            back_to_list('Question deleted.');
        } else {
            $form = [
                'id'          => (int) ($_POST['id'] ?? 0),
                'module_id'   => (int) ($_POST['module_id'] ?? 0),
                'question'    => trim((string) ($_POST['question'] ?? '')),
                'opt'         => array_map(function ($v) { return trim((string) $v); }, (array) ($_POST['opt'] ?? ['', '', '', ''])),
                'correct'     => (int) ($_POST['correct'] ?? 0),
                'explanation' => trim((string) ($_POST['explanation'] ?? '')),
                'active'      => !empty($_POST['active']) ? 1 : 0,
            ];
            $form['opt'] = array_pad(array_slice($form['opt'], 0, 4), 4, '');

            if (!in_array($form['module_id'], $moduleIds, true)) { $errors[] = 'Choose a module.'; }
            if ($form['question'] === '' || strlen($form['question']) > 500) { $errors[] = 'The question is required (up to 500 characters).'; }
            foreach ($form['opt'] as $o) {
                if ($o === '' || strlen($o) > 500) { $errors[] = 'All four answer options are required (up to 500 characters each).'; break; }
            }
            if (count(array_unique(array_map('strtolower', $form['opt']))) < 4) { $errors[] = 'The four answer options must all be different.'; }
            if ($form['correct'] < 0 || $form['correct'] > 3) { $errors[] = 'Mark which option is correct.'; }
            if (strlen($form['explanation']) > 600) { $errors[] = 'The explanation can be up to 600 characters.'; }

            if (!$errors) {
                $vals = [$form['module_id'], $form['question'], $form['opt'][0], $form['opt'][1], $form['opt'][2], $form['opt'][3],
                         $form['correct'], $form['explanation'], $form['active']];
                if ($form['id'] > 0) {
                    $pdo->prepare('UPDATE questions SET module_id=?, question=?, option_a=?, option_b=?, option_c=?, option_d=?, correct=?, explanation=?, active=? WHERE id=?')
                        ->execute(array_merge($vals, [$form['id']]));
                    back_to_list('Question updated.');
                }
                $pdo->prepare('INSERT INTO questions (module_id, question, option_a, option_b, option_c, option_d, correct, explanation, active) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute($vals);
                back_to_list('Question added.');
            }
        }
    } elseif (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
        $st->execute([(int) $_GET['edit']]);
        if ($r = $st->fetch()) {
            $form = ['id' => (int) $r['id'], 'module_id' => (int) $r['module_id'], 'question' => $r['question'],
                     'opt' => [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']],
                     'correct' => (int) $r['correct'], 'explanation' => $r['explanation'], 'active' => (int) $r['active']];
        }
    }

    // ---- list with filters + paging ----
    $fModule = (int) ($_GET['module'] ?? 0);
    $fSearch = trim((string) ($_GET['q'] ?? ''));
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $per     = 20;
    $where = []; $par = [];
    if ($fModule > 0)    { $where[] = 'q.module_id = ?'; $par[] = $fModule; }
    if ($fSearch !== '') { $where[] = 'q.question LIKE ?'; $par[] = '%' . $fSearch . '%'; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $st = $pdo->prepare("SELECT COUNT(*) FROM questions q $w");
    $st->execute($par);
    $total = (int) $st->fetchColumn();
    $pages = max(1, (int) ceil($total / $per));
    $page  = min($page, $pages);

    $st = $pdo->prepare("SELECT q.id, q.question, q.correct, q.active, m.code FROM questions q JOIN modules m ON m.id = q.module_id
                         $w ORDER BY m.sort_order, q.id LIMIT $per OFFSET " . (($page - 1) * $per));
    $st->execute($par);
    $rows = $st->fetchAll();
} catch (Throwable $ex) {
    $error = db_hint($ex);
    $modules = $rows = []; $total = 0; $pages = 1; $page = 1; $fModule = 0; $fSearch = '';
    $form = ['id' => 0, 'module_id' => 0, 'question' => '', 'opt' => ['', '', '', ''], 'correct' => 0, 'explanation' => '', 'active' => 1];
}

$showForm = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['edit']) || isset($_GET['new']);
$query = http_build_query(array_filter(['module' => $fModule ?: null, 'q' => $fSearch ?: null]));

$base   = '../';
$area   = 'admin';
$active = 'questions';
$title  = 'Questions';
include __DIR__ . '/../partials/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
    <h1>Questions</h1>
    <?php if (!$showForm): ?><a class="btn primary" href="questions.php?new=1<?= $fModule ? '&module=' . $fModule : '' ?>">Add question</a><?php endif; ?>
</div>

<?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash !== ''): ?><div class="alert ok" role="status"><?= e($flash) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<form method="post" class="q-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <input type="hidden" name="return" value="<?= e($query) ?>">
    <h2><?= $form['id'] ? 'Edit question' : 'Add question' ?></h2>

    <?php if ($errors): ?>
        <div class="alert err" role="alert" style="margin-bottom:16px"><div><?php foreach ($errors as $m): ?><div><?= e($m) ?></div><?php endforeach; ?></div></div>
    <?php endif; ?>

    <label class="field"><span>Module</span>
        <select name="module_id">
            <?php foreach ($modules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === (int) $form['module_id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
            <?php endforeach; ?>
        </select></label>

    <label class="field"><span>Question</span>
        <textarea name="question" maxlength="500" required><?= e($form['question']) ?></textarea></label>

    <div class="pick-correct" role="radiogroup" aria-label="Answer options. Select the correct one.">
        <?php foreach ($letters as $i => $L): ?>
            <div class="pick-row">
                <input type="radio" name="correct" value="<?= $i ?>" <?= (int) $form['correct'] === $i ? 'checked' : '' ?> aria-label="Option <?= $L ?> is correct">
                <label class="field"><span>Option <?= $L ?></span>
                    <input type="text" name="opt[]" maxlength="500" value="<?= e($form['opt'][$i]) ?>" required></label>
            </div>
        <?php endforeach; ?>
        <span class="muted" style="font-size:13px">Select the round button beside the correct option. Students see the options in a random order.</span>
    </div>

    <label class="field"><span>Explanation shown after the quiz</span>
        <textarea name="explanation" maxlength="600"><?= e($form['explanation']) ?></textarea></label>

    <label class="check"><input type="checkbox" name="active" value="1" <?= $form['active'] ? 'checked' : '' ?>> Active (can appear in quizzes)</label>

    <div style="display:flex;gap:10px">
        <button class="btn primary" type="submit"><?= $form['id'] ? 'Save changes' : 'Add question' ?></button>
        <a class="btn ghost" href="questions.php<?= $query ? '?' . e($query) : '' ?>">Cancel</a>
    </div>
</form>
<?php endif; ?>

<form method="get" class="toolbar">
    <label class="field"><span>Module</span>
        <select name="module">
            <option value="0">All modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $fModule === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
            <?php endforeach; ?>
        </select></label>
    <label class="field grow"><span>Search</span><input type="search" name="q" value="<?= e($fSearch) ?>" placeholder="Words in the question"></label>
    <button class="btn ghost" type="submit"><?= icon('search') ?>Filter</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Module</th><th>Question</th><th>Answer</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= e($r['code']) ?></code></td>
                <td class="qtext"><?= e(strlen($r['question']) > 110 ? substr($r['question'], 0, 107) . '...' : $r['question']) ?></td>
                <td><?= $letters[(int) $r['correct']] ?? '?' ?> <span class="muted">(before shuffle)</span></td>
                <td><span class="chip <?= $r['active'] ? 'ok' : 'idle' ?>"><?= $r['active'] ? 'Active' : 'Hidden' ?></span></td>
                <td>
                    <div class="row-actions">
                        <a class="btn ghost sm" href="questions.php?edit=<?= (int) $r['id'] ?><?= $query ? '&' . e($query) : '' ?>">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this question? If students have already seen it, it is hidden instead.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="return" value="<?= e($query) ?>">
                            <button class="btn ghost sm" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">No questions match.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pages">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
        <?php if ($p === $page): ?><span class="cur"><?= $p ?></span>
        <?php else: ?><a href="questions.php?<?= e(http_build_query(array_filter(['module' => $fModule ?: null, 'q' => $fSearch ?: null, 'page' => $p]))) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<p class="muted"><?= $total ?> question<?= $total === 1 ? '' : 's' ?>. Each attempt draws <?= QUIZ_QUESTIONS ?> random active questions from the module.</p>

<?php include __DIR__ . '/../partials/footer.php'; ?>
