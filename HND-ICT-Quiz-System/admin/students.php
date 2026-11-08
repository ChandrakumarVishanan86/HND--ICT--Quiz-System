<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('admin', false, '../');

$error = '';
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
$rows = [];
$search = trim((string) ($_GET['q'] ?? ''));

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf'] ?? null) && ($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([(int) ($_POST['id'] ?? 0)]);
        $_SESSION['admin_flash'] = 'Student and all of their results were deleted.';
        header('Location: students.php');
        exit;
    }

    $par = [];
    $w   = "WHERE u.role = 'student'";
    if ($search !== '') {
        $w   .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)';
        $par  = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
    }
    $st = $pdo->prepare("SELECT u.id, u.name, u.username, u.email, u.created_at,
            (SELECT COUNT(DISTINCT a.module_id) FROM attempts a WHERE a.user_id = u.id AND a.passed = 1) AS passed_modules,
            (SELECT COUNT(*) FROM attempts a WHERE a.user_id = u.id AND a.submitted_at IS NOT NULL) AS tries,
            (SELECT COUNT(*) FROM certificates c WHERE c.user_id = u.id) AS certs,
            (SELECT MAX(a.submitted_at) FROM attempts a WHERE a.user_id = u.id) AS last_seen
          FROM users u $w ORDER BY u.created_at DESC LIMIT 200");
    $st->execute($par);
    $rows = $st->fetchAll();
    $totalModules = (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE active = 1')->fetchColumn();
} catch (Throwable $ex) {
    $error = db_hint($ex);
    $totalModules = 0;
}

$base   = '../';
$area   = 'admin';
$active = 'students';
$title  = 'Students';
include __DIR__ . '/../partials/header.php';
?>
<h1>Students</h1>
<?php if ($error !== ''): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash !== ''): ?><div class="alert ok" role="status"><?= e($flash) ?></div><?php endif; ?>

<form method="get" class="toolbar">
    <label class="field grow"><span>Search</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, username or email"></label>
    <button class="btn ghost" type="submit"><?= icon('search') ?>Search</button>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Student</th><th>Email</th><th>Joined</th><th class="num">Modules passed</th><th class="num">Attempts</th><th class="num">Certificates</th><th>Last quiz</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><br><span class="muted"><?= e($r['username']) ?></span></td>
                <td><?= e($r['email']) ?></td>
                <td><?= e(fmt_date($r['created_at'])) ?></td>
                <td class="num"><?= (int) $r['passed_modules'] ?>/<?= $totalModules ?></td>
                <td class="num"><?= (int) $r['tries'] ?></td>
                <td class="num"><?= (int) $r['certs'] ?></td>
                <td><?= $r['last_seen'] ? e(fmt_dt($r['last_seen'])) : '<span class="muted">Never</span>' ?></td>
                <td>
                    <form method="post" class="row-actions" onsubmit="return confirm('Delete this student and all of their results and certificates?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="btn ghost sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">No students found.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
