<?php
/**
 * POST JSON {attempt_id, question_id, choice}  ->  saves one answer (choice 0-3, or null to clear).
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
$choice = $in['choice'] ?? null;
if ($choice !== null && !(is_int($choice) && $choice >= 0 && $choice <= 3)) {
    json_out(['error' => 'Bad choice.'], 400);
}

try {
    $pdo     = db();
    $attempt = get_attempt($pdo, (int) ($in['attempt_id'] ?? 0), current_user()['id']);
    if (!$attempt || $attempt['submitted_at'] !== null) {
        json_out(['error' => 'This attempt is closed.'], 409);
    }
    if (attempt_expired($attempt)) {
        json_out(['error' => 'Time is up.', 'expired' => true], 409);
    }
    $pdo->prepare('UPDATE attempt_questions SET chosen = ? WHERE attempt_id = ? AND question_id = ?')
        ->execute([$choice, $attempt['id'], (int) ($in['question_id'] ?? 0)]);
    json_out(['ok' => true]);
} catch (Throwable $ex) {
    json_out(['error' => db_hint($ex)], 500);
}
