</main>
<footer class="wrap foot">
    <span><?= e(APP_NAME) ?> is a practice tool. Its certificates are for your own records and are not an official qualification.</span>
</footer>
<?php foreach (($extraScripts ?? []) as $src): ?>
<script src="<?= $base ?? '' ?><?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
