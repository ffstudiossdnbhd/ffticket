<?php
$ticket = $detail['ticket'] ?? [];
$attachments = $detail['attachments'] ?? [];
$auditLogs = $detail['audit_logs'] ?? [];
$comments = $detail['comments'] ?? [];
?>
<section class="card detail-head">
    <div>
        <div class="muted"><?= e($ticket['ticket_number'] ?? '') ?></div>
        <h1><?= e($ticket['subject'] ?? '') ?></h1>
        <p class="muted">Location: <?= e($ticket['location_name'] ?? '') ?></p>
        <p><?= nl2br(e($ticket['description'] ?? '')) ?></p>
    </div>
    <div class="detail-actions">
        <span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span>
        <?php if (($ticket['urgency'] ?? '') !== ''): ?>
            <span class="badge <?= e(badge_class('urgency', $ticket['urgency'] ?? '')) ?>"><?= e($ticket['urgency']) ?></span>
        <?php endif; ?>
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
                    <span><?= e($log['created_at'] ?? '') ?> · <?= e($log['performed_by_name'] ?? '') ?></span>
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
                    <span><?= e($comment['created_by_name'] ?? '') ?> · <?= e($comment['created_at'] ?? '') ?></span>
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
                <?= e($attachment['file_name'] ?? '') ?>
                <span><?= e($attachment['file_type'] ?? '') ?> · <?= number_format(((int)($attachment['file_size'] ?? 0)) / 1024, 1) ?> KB</span>
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
