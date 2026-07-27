<section class="card page-header-card">
    <div class="page-heading">
        <div>
            <h1>Kanban Board</h1>
            <p>Move tickets through the support workflow.</p>
        </div>
        <a class="icon-btn" href="<?= e($url('/admin/kanban')) ?>" aria-label="Refresh board" title="Refresh board">
            <span aria-hidden="true">&#xE72C;</span>
        </a>
    </div>
</section>

<div class="kanban-scroll">
    <div class="kanban-grid">
        <?php foreach ($columns as $status => $items): ?>
            <section class="card kanban-column" data-kanban-status="<?= e($status) ?>">
                <div class="kanban-column-head">
                    <h2><?= e($status) ?></h2>
                    <span><?= count($items) ?></span>
                </div>
                <div class="kanban-list">
                    <?php foreach ($items as $ticket): ?>
                        <article class="ticket-card" draggable="true" data-ticket-id="<?= e($ticket['id'] ?? '') ?>" data-ticket-status="<?= e($status) ?>">
                            <a class="ticket-number" href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a>
                            <strong><?= e($ticket['subject'] ?? '') ?></strong>
                            <?php if (($ticket['urgency'] ?? '') !== ''): ?>
                                <span class="badge <?= e(badge_class('urgency', $ticket['urgency'] ?? '')) ?>"><?= e($ticket['urgency']) ?></span>
                            <?php endif; ?>
                            <div class="card-actions">
                                <?php
                                $moves = match ($status) {
                                    'Open' => ['In Progress' => 'Start'],
                                    'In Progress' => ['Pending User Input' => 'Pending', 'Closed' => 'Close'],
                                    'Pending User Input' => ['In Progress' => 'Resume'],
                                    'Closed' => ['Open' => 'Reopen'],
                                    default => [],
                                };
                                ?>
                                <?php foreach ($moves as $target => $label): ?>
                                    <form method="post" action="<?= e($url('/admin/kanban/move')) ?>" data-kanban-move-form>
                                        <?= $csrf() ?>
                                        <input type="hidden" name="id" value="<?= e($ticket['id'] ?? '') ?>">
                                        <input type="hidden" name="status" value="<?= e($target) ?>">
                                        <button class="btn <?= $target === 'Open' ? 'btn-secondary' : '' ?> btn-compact" type="submit"><?= e($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?><p class="empty">No tickets.</p><?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
