<div class="staff-grid">
    <section class="card submit-card">
        <div class="page-heading compact-heading">
            <div>
                <h1>Submit Ticket</h1>
                <p>Tell IT what is happening and where.</p>
            </div>
        </div>
        <form method="post" action="<?= e($url('/tickets/create')) ?>" enctype="multipart/form-data" class="stack-form">
            <?= $csrf() ?>
            <label>
                <span>Category</span>
                <select name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e($category['id'] ?? '') ?>"><?= e($category['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Location</span>
                <select name="location_id" required>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e($location['id'] ?? '') ?>"><?= e($location['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Subject</span>
                <input type="text" name="subject" maxlength="180" placeholder="Brief issue summary" required>
            </label>
            <label>
                <span>Description</span>
                <textarea name="description" rows="7" maxlength="5000" placeholder="Describe the issue, error message, and affected device" required></textarea>
            </label>
            <label class="file-field">
                <span>Attachment</span>
                <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.pdf">
            </label>
            <button class="btn btn-block" type="submit">Submit Ticket</button>
        </form>
    </section>

    <section class="card table-card">
        <div class="section-head">
            <div>
                <h1>My Submitted Tickets</h1>
                <p>Track your active and completed requests.</p>
            </div>
            <a class="icon-btn" href="<?= e($url('/tickets')) ?>" aria-label="Refresh tickets" title="Refresh tickets">
                <span aria-hidden="true">&#xE72C;</span>
            </a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Subject</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Urgency</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr class="ticket-row" data-href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>">
                            <td><a href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a><?php if ($ticket['has_unread_tech_comments'] ?? false): ?><span class="ticket-unread-dot" aria-label="Unread IT comment" title="Unread IT comment"></span><?php endif; ?></td>
                            <td><?= e($ticket['subject'] ?? '') ?></td>
                            <td><?= e($ticket['location_name'] ?? '') ?></td>
                            <td class="badge-cell"><span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
                            <td><?php if (($ticket['urgency'] ?? '') !== ''): ?><span class="badge <?= e(badge_class('urgency', $ticket['urgency'] ?? '')) ?>"><?= e($ticket['urgency']) ?></span><?php endif; ?></td>
                            <td><?= e($ticket['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($tickets === []): ?>
                        <tr><td colspan="6" class="empty">No tickets found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
