<?php
$ticket = $detail['ticket'] ?? [];
$attachments = $detail['attachments'] ?? [];
$auditLogs = $detail['audit_logs'] ?? [];
$comments = $detail['comments'] ?? [];
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
