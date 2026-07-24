<section class="kanban-head">
    <h1>Kanban Board</h1>
    <a class="btn btn-secondary btn-compact" href="<?= e($url('/admin/kanban')) ?>">Refresh</a>
</section>

<div class="kanban-grid">
    <?php foreach ($columns as $status => $items): ?>
        <section class="card kanban-column">
            <h2><?= e($status) ?></h2>
            <div class="kanban-list">
                <?php foreach ($items as $ticket): ?>
                    <article class="ticket-card">
                        <a class="muted" href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a>
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
                                <form method="post" action="<?= e($url('/admin/kanban/move')) ?>">
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
