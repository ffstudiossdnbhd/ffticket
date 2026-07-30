<?php
$ticket = $detail['ticket'] ?? [];
$attachments = $detail['attachments'] ?? [];
$auditLogs = $detail['audit_logs'] ?? [];
$comments = $detail['comments'] ?? [];
$currentUrgencyTypeId = (int)($ticket['urgency_type_id'] ?? 0);
$currentAssigneeId = (int)($ticket['assigned_to'] ?? 0);
$selectedUrgencyTypeId = '';
$selectedAssigneeId = '';
foreach ($urgencyTypes as $urgency) {
    if ((int)($urgency['id'] ?? 0) === $currentUrgencyTypeId) {
        $selectedUrgencyTypeId = (string)$currentUrgencyTypeId;
        break;
    }
}
foreach ($assignableUsers as $assignee) {
    if ((int)($assignee['id'] ?? 0) === $currentAssigneeId) {
        $selectedAssigneeId = (string)$currentAssigneeId;
        break;
    }
}
$selectedAssigneeValue = $currentAssigneeId === 0 ? 'unassigned' : $selectedAssigneeId;
?>
<section class="card detail-head">
    <div class="detail-copy">
        <span class="eyebrow">Ticket Detail</span>
        <div class="ticket-id"><?= e($ticket['ticket_number'] ?? '') ?></div>
        <h1><?= e($ticket['subject'] ?? '') ?></h1>
        <p><span class="meta-label">Location:</span> <?= e($ticket['location_name'] ?? '') ?></p>
        <p><span class="meta-label">Description:</span> <?= nl2br(e($ticket['description'] ?? '')) ?></p>
        <?php if (($ticket['urgency'] ?? '') !== ''): ?>
            <p class="secondary-meta"><span class="meta-label">Urgency:</span> <?= e($ticket['urgency']) ?></p>
        <?php endif; ?>
    </div>
    <div class="detail-actions">
        <span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span>
        <?php if ($isTech ?? false): ?>
            <form method="post" action="<?= e($url('/tickets/' . (int)($ticket['id'] ?? 0) . '/close')) ?>">
                <?= $csrf() ?>
                <button class="btn" type="submit">Mark as Closed</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($canUpdateTicket ?? false): ?>
    <section class="card ticket-update-card">
        <div class="section-head">
            <div>
                <h2>Update Ticket</h2>
                <p>Update the ticket workflow, urgency, or assignee.</p>
            </div>
        </div>
        <form method="post" action="<?= e($url('/tickets/' . (int)($ticket['id'] ?? 0) . '/update')) ?>" class="ticket-update-form">
            <?= $csrf() ?>
            <label>
                <span>Status</span>
                <select name="status">
                    <?php foreach ($mutationStatuses as $status): ?>
                        <option value="<?= e($status) ?>"<?= selected($ticket['status'] ?? '', $status) ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Urgency</span>
                <select name="urgency_type_id">
                    <option value=""<?= selected($selectedUrgencyTypeId, '') ?>>No change</option>
                    <?php foreach ($urgencyTypes as $urgency): ?>
                        <option value="<?= e($urgency['id'] ?? '') ?>"<?= selected($selectedUrgencyTypeId, $urgency['id'] ?? '') ?>><?= e($urgency['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Assigned</span>
                <select name="assigned_to">
                    <option value=""<?= selected($selectedAssigneeValue, '') ?>>No change</option>
                    <option value="unassigned"<?= selected($selectedAssigneeValue, 'unassigned') ?>>Unassigned</option>
                    <?php foreach ($assignableUsers as $assignee): ?>
                        <option value="<?= e($assignee['id'] ?? '') ?>"<?= selected($selectedAssigneeValue, $assignee['id'] ?? '') ?>><?= e($assignee['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn" type="submit">Save Updates</button>
        </form>
    </section>
<?php endif; ?>

<div class="detail-grid">
    <section class="card">
        <h2>Audit History</h2>
        <div class="timeline">
            <?php foreach ($auditLogs as $log): ?>
                <div class="timeline-item">
                    <strong><?= e($log['action'] ?? '') ?></strong>
                    <p><?= e($log['notes'] ?? '') ?></p>
                    <span><?= e($log['created_at'] ?? '') ?> &middot; <?= e($log['performed_by_name'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($auditLogs === []): ?><p class="empty">No audit history.</p><?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h2>Comments</h2>
        <div class="timeline">
            <?php foreach ($comments as $comment): ?>
                <div class="timeline-item">
                    <p><?= nl2br(e($comment['body'] ?? '')) ?></p>
                    <span><?= e($comment['created_by_name'] ?? '') ?> &middot; <?= e($comment['created_at'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($comments === []): ?><p class="empty">No comments.</p><?php endif; ?>
        </div>
    </section>
</div>

<section class="card detail-bottom">
    <div>
        <h2>Attachments</h2>
        <?php foreach ($attachments as $attachment): ?>
            <a class="attachment" href="<?= e($url('/attachments/' . (int)$attachment['id'] . '/download')) ?>">
                <span class="attachment-icon" aria-hidden="true">&#xE723;</span>
                <span class="attachment-copy">
                    <strong><?= e($attachment['file_name'] ?? '') ?></strong>
                    <small><?= e($attachment['file_type'] ?? '') ?> &middot; <?= number_format(((int)($attachment['file_size'] ?? 0)) / 1024, 1) ?> KB</small>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if ($attachments === []): ?><p class="empty">No attachments.</p><?php endif; ?>
    </div>

    <?php if ($isTech ?? false): ?>
        <form method="post" action="<?= e($url('/tickets/' . (int)($ticket['id'] ?? 0) . '/comment')) ?>" class="comment-form">
            <?= $csrf() ?>
            <h2>Add Comment</h2>
            <textarea name="body" rows="4" maxlength="5000" placeholder="Add a comment" required></textarea>
            <button class="btn" type="submit">Add Comment</button>
        </form>
    <?php endif; ?>
</section>
