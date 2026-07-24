<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FFTicket') ?></title>
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <img src="<?= e($asset('app-icon.png')) ?>" alt="" class="brand-icon">
            <div>
                <div class="brand-title">FFTicket</div>
                <div class="brand-subtitle">Support Console</div>
            </div>
        </div>
        <div class="top-actions">
            <div class="user-pill">
                <span class="avatar"><?= e(initials((string)($user['name'] ?? 'User'))) ?></span>
                <span><?= e($user['name'] ?? 'User') ?> · <?= e($user['role'] ?? 'staff') ?></span>
            </div>
            <a class="btn btn-secondary btn-compact" href="<?= e($url('/change-password')) ?>">Change Password</a>
            <form method="post" action="<?= e($url('/logout')) ?>">
                <?= $csrf() ?>
                <button class="btn btn-secondary btn-compact" type="submit">Logout</button>
            </form>
        </div>
    </header>

    <?php if ($isTech ?? false): ?>
        <nav class="tabs">
            <a href="<?= e($url('/admin/tickets')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/tickets') ? 'active' : '' ?>">Ticket Overview</a>
            <a href="<?= e($url('/admin/kanban')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/kanban') ? 'active' : '' ?>">Kanban Board</a>
            <?php if ($isAdmin ?? false): ?>
                <a href="<?= e($url('/admin/users')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/users') ? 'active' : '' ?>">User Management</a>
            <?php endif; ?>
            <a href="<?= e($url('/admin/customize')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/customize') ? 'active' : '' ?>">Customize Ticket</a>
        </nav>
    <?php endif; ?>

    <main class="workspace">
        <?php if (($flash['error'] ?? '') !== ''): ?>
            <div class="alert alert-error"><?= e($flash['error']) ?></div>
        <?php endif; ?>
        <?php if (($flash['success'] ?? '') !== ''): ?>
            <div class="alert alert-success"><?= e($flash['success']) ?></div>
        <?php endif; ?>
        <?php if (($loadError ?? '') !== ''): ?>
            <div class="alert alert-error"><?= e($loadError) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
