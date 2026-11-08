<?php
/**
 * Shared page top. Expects: $base ('' or '../'), $title, $active (nav key), optional $area ('student'|'admin').
 */
$base   = $base   ?? '';
$title  = $title  ?? APP_NAME;
$active = $active ?? '';
$area   = $area   ?? 'student';
$user   = current_user();

$nav = ($area === 'admin')
    ? [
        'overview'  => ['Overview',  $base . 'admin/index.php'],
        'questions' => ['Questions', $base . 'admin/questions.php'],
        'students'  => ['Students',  $base . 'admin/students.php'],
        'attempts'  => ['Attempts',  $base . 'admin/attempts.php'],
        'student'   => ['Student view', $base . 'dashboard.php'],
    ]
    : [
        'modules'      => ['Modules',      $base . 'dashboard.php'],
        'certificates' => ['Certificates', $base . 'certificates.php'],
        'verify'       => ['Verify',       $base . 'verify.php'],
    ];
if ($area === 'student' && is_admin()) {
    $nav['admin'] = ['Admin', $base . 'admin/index.php'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/fonts.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-in">
        <a class="brand" href="<?= $base ?><?= $area === 'admin' ? 'admin/index.php' : 'dashboard.php' ?>">
            <span class="bubble" aria-hidden="true"></span>
            <span><?= e(APP_NAME) ?><?= $area === 'admin' ? ' <em>admin</em>' : '' ?></span>
        </a>
        <nav class="nav" aria-label="Main">
            <?php foreach ($nav as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form class="who" method="post" action="<?= $base ?>logout.php">
            <?= csrf_field() ?>
            <span class="who-name"><?= e($user['name']) ?></span>
            <button type="submit" class="link-btn" title="Sign out"><?= icon('logout') ?><span>Sign out</span></button>
        </form>
    </div>
</header>
<main class="wrap page">
