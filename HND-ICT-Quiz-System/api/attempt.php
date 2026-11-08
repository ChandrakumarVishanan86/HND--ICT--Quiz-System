<?php
/**
 * GET ?id=ATTEMPT  ->  questions (without answers), saved choices and seconds left.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('', true);

try {
    $pdo     = db();
    $attempt = get_attempt($pdo, (int) ($_GET['id'] ?? 0), current_user()['id']);
    if (!$attempt) {
        json_out(['error' => 'Attempt not found.'], 404);
    }
    if ($attempt['submitted_at'] !== null) {
        json_out(['error' => 'This attempt is already submitted.', 'redirect' => 'result.php?attempt=' . $attempt['id']], 409);
    }

    $st = $pdo->prepare('SELECT aq.question_id, aq.position, aq.opt_order, aq.chosen,
                                q.question, q.option_a, q.option_b, q.option_c, q.option_d
                           FROM attempt_questions aq JOIN questions q ON q.id = aq.question_id
                          WHERE aq.attempt_id = ? ORDER BY aq.position');
    $st->execute([$attempt['id']]);

    $questions = [];
    $saved     = [];
    foreach ($st->fetchAll() as $r) {
        $opts  = [$r['option_a'], $r['option_b'], $r['option_c'], $r['option_d']];
        $shown = [];
        foreach (explode(',', $r['opt_order']) as $i) {
            $shown[] = $opts[(int) $i];
        }
        $questions[] = ['id' => (int) $r['question_id'], 'text' => $r['question'], 'options' => $shown];
        if ($r['chosen'] !== null) {
            $saved[(int) $r['question_id']] = (int) $r['chosen'];
        }
    }

    json_out([
        'attempt'   => ['id' => (int) $attempt['id'], 'module' => $attempt['module_title'], 'total' => count($questions)],
        'seconds'   => attempt_seconds_left($attempt),
        'questions' => $questions,
        'saved'     => (object) $saved,
    ]);
} catch (Throwable $ex) {
    json_out(['error' => db_hint($ex)], 500);
}
