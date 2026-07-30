<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'FFTicket') ?></title>
    <link rel="stylesheet" href="<?= e($asset('css/app.css')) ?>">
    <script src="<?= e($asset('js/app.js')) ?>" defer></script>
</head>
<body class="app-body" data-activity-url="<?= e($url('/activity/heartbeat')) ?>" data-faq-url="<?= e($url('/faqs')) ?>" data-login-url="<?= e($url('/login')) ?>" data-csrf-token="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
    <header class="topbar">
        <a class="brand" href="<?= e($url('/dashboard')) ?>" aria-label="FFTicket dashboard">
            <img src="<?= e($asset('app-icon.png')) ?>" alt="" class="brand-icon">
            <span>
                <span class="brand-title">FFTicket</span>
                <span class="brand-subtitle">Support Console</span>
            </span>
        </a>

        <form class="global-search" method="get" action="<?= e($url(($isTech ?? false) ? '/admin/tickets' : '/tickets')) ?>" role="search">
            <span class="field-icon" aria-hidden="true">&#xE721;</span>
            <label class="sr-only" for="global-ticket-search">Search tickets</label>
            <input id="global-ticket-search" type="search" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Search tickets">
        </form>

        <div class="top-actions">
            <div class="user-pill">
                <span class="avatar" role="img" aria-label="<?= e($user['name'] ?? 'User') ?>">
                    <span aria-hidden="true">&#xE77B;</span>
                </span>
                <span class="user-copy">
                    <strong><?= e($user['name'] ?? 'User') ?></strong>
                    <small><?= e($user['role'] ?? 'staff') ?></small>
                </span>
            </div>
            <button class="icon-btn" type="button" data-faq-open aria-label="Open FAQs" title="FAQs"><span aria-hidden="true">?</span></button>
            <a class="icon-btn" href="<?= e($url('/change-password')) ?>" aria-label="Change password" title="Change password">
                <span aria-hidden="true">&#xE192;</span>
            </a>
            <form method="post" action="<?= e($url('/logout')) ?>">
                <?= $csrf() ?>
                <button class="icon-btn" type="submit" aria-label="Logout" title="Logout">
                    <span aria-hidden="true">&#xF3B1;</span>
                </button>
            </form>
            <?php if ($isTech ?? false): ?>
                <button class="icon-btn nav-toggle" type="button" aria-label="Toggle navigation" aria-controls="primary-navigation" aria-expanded="false">
                    <span aria-hidden="true">&#xE700;</span>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-shell<?= ($isTech ?? false) ? ' has-sidebar' : '' ?>">
        <?php if ($isTech ?? false): ?>
            <aside class="sidebar" id="primary-navigation">
                <nav class="side-nav" aria-label="Primary navigation">
                    <a href="<?= e($url('/admin/tickets')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/tickets') ? 'active' : '' ?>">
                        <span aria-hidden="true">&#xE8A5;</span>
                        Ticket Overview
                    </a>
                    <a href="<?= e($url('/admin/kanban')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/kanban') ? 'active' : '' ?>">
                        <span aria-hidden="true">&#xE7C4;</span>
                        Kanban Board
                    </a>
                    <?php if ($isAdmin ?? false): ?>
                        <a href="<?= e($url('/admin/users')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/users') ? 'active' : '' ?>">
                            <span aria-hidden="true">&#xE716;</span>
                            User Management
                        </a>
                        <a href="<?= e($url('/admin/faqs')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/faqs') ? 'active' : '' ?>">
                            <span aria-hidden="true">&#xE90F;</span>
                            FAQ Management
                        </a>
                        <a href="<?= e($url('/admin/timeouts')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/timeouts') ? 'active' : '' ?>">
                            <span aria-hidden="true">&#xE823;</span>
                            Timeouts
                        </a>
                    <?php endif; ?>
                    <a href="<?= e($url('/admin/customize')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/customize') ? 'active' : '' ?>">
                        <span aria-hidden="true">&#xE713;</span>
                        Customize Ticket
                    </a>
                </nav>
            </aside>
        <?php endif; ?>

        <main class="workspace">
            <?php if (($flash['error'] ?? '') !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($flash['error']) ?></div>
            <?php endif; ?>
            <?php if (($flash['success'] ?? '') !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($flash['success']) ?></div>
            <?php endif; ?>
            <?php if (($loadError ?? '') !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($loadError) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
    <section class="faq-modal" data-faq-dialog hidden role="dialog" aria-modal="true" aria-labelledby="faq-dialog-title">
        <div class="faq-modal-backdrop" data-faq-close></div>
        <div class="faq-modal-card">
            <div class="section-head"><h2 id="faq-dialog-title">Frequently Asked Questions</h2><button class="icon-btn" type="button" data-faq-close aria-label="Close FAQs">×</button></div>
            <div class="faq-list" data-faq-list><p class="empty">Loading FAQs…</p></div>
        </div>
    </section>
</body>
</html>
