<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FFTicket') ?></title>
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body class="auth-shell">
    <main class="login-card">
        <?php if (($flash['error'] ?? '') !== ''): ?>
            <div class="alert alert-error"><?= e($flash['error']) ?></div>
        <?php endif; ?>
        <?php if (($flash['success'] ?? '') !== ''): ?>
            <div class="alert alert-success"><?= e($flash['success']) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
