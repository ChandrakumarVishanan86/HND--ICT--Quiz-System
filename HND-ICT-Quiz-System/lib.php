<?php
/**
 * Quiz engine and certificate logic shared by pages and API endpoints.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Unix time at which an attempt stops accepting answers (without grace). */
function attempt_deadline(array $attempt): int
{
    return strtotime($attempt['started_at']) + QUIZ_MINUTES * 60;
}

function attempt_seconds_left(array $attempt): int
{
    return max(0, attempt_deadline($attempt) - time());
}

/** True once the time limit plus the grace period has run out. */
function attempt_expired(array $attempt): bool
{
    return time() > attempt_deadline($attempt) + GRACE_SECONDS;
}

/** Fetch an attempt that belongs to $userId (or any user when $userId is null). */
function get_attempt(PDO $pdo, int $id, ?int $userId): ?array
{
    $sql = 'SELECT a.*, m.title AS module_title, m.code AS module_code
              FROM attempts a JOIN modules m ON m.id = a.module_id WHERE a.id = ?';
    $par = [$id];
    if ($userId !== null) {
        $sql  .= ' AND a.user_id = ?';
        $par[] = $userId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($par);
    $row = $st->fetch();
    return $row ?: null;
}

/**
 * Start a new attempt: draw random questions and shuffle the answer options of each one.
 * Returns the attempt id, or 0 when the module has no active questions.
 */
function create_attempt(PDO $pdo, int $userId, int $moduleId): int
{
    $st = $pdo->prepare('SELECT id FROM questions WHERE module_id = ? AND active = 1');
    $st->execute([$moduleId]);
    $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
    if (!$ids) {
        return 0;
    }
    shuffle($ids);
    $ids = array_slice($ids, 0, QUIZ_QUESTIONS);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO attempts (user_id, module_id, started_at, total) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $moduleId, date('Y-m-d H:i:s'), count($ids)]);
        $attemptId = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO attempt_questions (attempt_id, question_id, position, opt_order) VALUES (?, ?, ?, ?)');
        foreach ($ids as $pos => $qid) {
            $order = [0, 1, 2, 3];
            shuffle($order);
            $ins->execute([$attemptId, $qid, $pos + 1, implode(',', $order)]);
        }
        $pdo->commit();
        return $attemptId;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

/** An unfinished, still-running attempt for this user and module (so a refresh resumes it). */
function find_running_attempt(PDO $pdo, int $userId, int $moduleId): ?array
{
    $st = $pdo->prepare('SELECT * FROM attempts WHERE user_id = ? AND module_id = ? AND submitted_at IS NULL ORDER BY id DESC LIMIT 1');
    $st->execute([$userId, $moduleId]);
    $row = $st->fetch();
    return ($row && !attempt_expired($row)) ? $row : null;
}

/**
 * Mark an attempt, store the result and issue certificates.
 * $payload = [question_id => chosen position]. After the deadline only answers
 * that were already saved on the server are counted.
 * Returns ['percent' => float, 'passed' => bool, 'score' => int, 'total' => int, 'certs' => string[]]
 * or null when the attempt was already submitted.
 */
function grade_attempt(PDO $pdo, array $attempt, array $payload): ?array
{
    $late = attempt_expired($attempt);
    $pdo->beginTransaction();
    try {
        // Claim the attempt so double submits cannot grade twice.
        $claim = $pdo->prepare('UPDATE attempts SET submitted_at = ? WHERE id = ? AND submitted_at IS NULL');
        $claim->execute([date('Y-m-d H:i:s'), $attempt['id']]);
        if ($claim->rowCount() === 0) {
            $pdo->rollBack();
            return null;
        }

        $st = $pdo->prepare('SELECT aq.id, aq.question_id, aq.opt_order, aq.chosen, q.correct
                               FROM attempt_questions aq JOIN questions q ON q.id = aq.question_id
                              WHERE aq.attempt_id = ?');
        $st->execute([$attempt['id']]);
        $rows = $st->fetchAll();

        $upd   = $pdo->prepare('UPDATE attempt_questions SET chosen = ?, is_correct = ? WHERE id = ?');
        $score = 0;
        foreach ($rows as $r) {
            $choice = $r['chosen'] !== null ? (int) $r['chosen'] : null;
            if (!$late && array_key_exists((int) $r['question_id'], $payload)) {
                $p      = $payload[(int) $r['question_id']];
                $choice = (is_int($p) && $p >= 0 && $p <= 3) ? $p : null;
            }
            $ok = false;
            if ($choice !== null) {
                $order = array_map('intval', explode(',', $r['opt_order']));
                $ok    = ($order[$choice] ?? -1) === (int) $r['correct'];
            }
            $upd->execute([$choice, $choice === null ? null : ($ok ? 1 : 0), $r['id']]);
            $score += $ok ? 1 : 0;
        }

        $total   = count($rows);
        $percent = $total > 0 ? round($score / $total * 100, 2) : 0.0;
        $passed  = $percent >= PASS_PERCENT;
        $took    = max(0, min(time(), attempt_deadline($attempt) + GRACE_SECONDS) - strtotime($attempt['started_at']));

        $pdo->prepare('UPDATE attempts SET duration_sec = ?, score = ?, total = ?, percent = ?, passed = ? WHERE id = ?')
            ->execute([$took, $score, $total, $percent, $passed ? 1 : 0, $attempt['id']]);

        $certs = [];
        if ($passed) {
            $c = issue_certificate($pdo, (int) $attempt['user_id'], (int) $attempt['module_id'], 'module', $percent);
            if ($c !== null) {
                $certs[] = $c;
            }
            $p = maybe_issue_programme_certificate($pdo, (int) $attempt['user_id']);
            if ($p !== null) {
                $certs[] = $p;
            }
        }
        $pdo->commit();

        return ['percent' => $percent, 'passed' => $passed, 'score' => $score, 'total' => $total, 'certs' => $certs];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Certificate code such as HNDQ-K7F3-9QXA-2M (no look-alike characters). */
function make_cert_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 10; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'HNDQ-' . substr($out, 0, 4) . '-' . substr($out, 4, 4) . '-' . substr($out, 8, 2);
}

/** Create a certificate unless the student already has one of that kind. Returns the new code or null. */
function issue_certificate(PDO $pdo, int $userId, int $moduleId, string $kind, float $percent): ?string
{
    $st = $pdo->prepare('SELECT id FROM certificates WHERE user_id = ? AND kind = ? AND module_id = ?');
    $st->execute([$userId, $kind, $moduleId]);
    if ($st->fetch()) {
        return null;
    }
    for ($try = 0; $try < 5; $try++) {
        $code = make_cert_code();
        try {
            $pdo->prepare('INSERT INTO certificates (code, user_id, module_id, kind, percent, issued_at) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$code, $userId, $moduleId, $kind, $percent, date('Y-m-d H:i:s')]);
            return $code;
        } catch (PDOException $ex) {
            if (db_error_code($ex) !== 1062) {
                throw $ex;
            }
        }
    }
    throw new RuntimeException('Could not create a unique certificate code.');
}

/** Best percent per active module for a student: [module_id => percent] (passed modules only). */
function best_passed_scores(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare('SELECT a.module_id, MAX(a.percent) AS best
                           FROM attempts a JOIN modules m ON m.id = a.module_id AND m.active = 1
                          WHERE a.user_id = ? AND a.passed = 1 GROUP BY a.module_id');
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(int) $r['module_id']] = (float) $r['best'];
    }
    return $out;
}

/** Issue the programme certificate when every active module has been passed. */
function maybe_issue_programme_certificate(PDO $pdo, int $userId): ?string
{
    $active = (int) $pdo->query('SELECT COUNT(*) FROM modules WHERE active = 1')->fetchColumn();
    $best   = best_passed_scores($pdo, $userId);
    if ($active === 0 || count($best) < $active) {
        return null;
    }
    $avg = round(array_sum($best) / count($best), 2);
    return issue_certificate($pdo, $userId, 0, 'programme', $avg);
}
