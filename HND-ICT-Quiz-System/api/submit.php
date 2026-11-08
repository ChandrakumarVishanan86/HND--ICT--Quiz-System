<?php
/**
 * POST JSON {attempt_id, answers: {question_id: choice|null}}  ->  grades the attempt.
 * Header X-CSRF-Token is required.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';
require_login('', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'POST only.'], 405);
}
if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_out(['error' => 'Session expired. Reload the page.'], 419);
}

$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_out(['error' => 'Bad request.'], 400);
}
$answers = (isset($in['answers']) && is_array($in['answers'])) ? $in['answers'] : [];

try {
    $pdo     = db();
    $attempt = get_attempt($pdo, (int) ($in['attempt_id'] ?? 0), current_user()['id']);
    if (!$attempt) {
        json_out(['error' => 'Attempt not found.'], 404);
    }
    $url = 'result.php?attempt=' . (int) $attempt['id'];
    if ($attempt['submitted_at'] !== null) {
        json_out(['ok' => true, 'redirect' => $url]);       // double submit: just go to the result
    }

    $payload = [];
    foreach ($answers as $qid => $choice) {
        $payload[(int) $qid] = $choice;
    }
    $res = grade_attempt($pdo, $attempt, $payload);
    if ($res && $res['certs']) {
        $_SESSION['new_certs'][(int) $attempt['id']] = $res['certs'];
    }
    json_out(['ok' => true, 'redirect' => $url]);
} catch (Throwable $ex) {
    json_out(['error' => db_hint($ex)], 500);
}
