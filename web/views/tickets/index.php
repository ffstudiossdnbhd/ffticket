<div class="staff-grid">
    <section class="card">
        <h1>Submit Ticket</h1>
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
            <label>
                <span>Attachment</span>
                <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.pdf">
            </label>
            <button class="btn" type="submit">Submit Ticket</button>
        </form>
    </section>

    <section class="card table-card">
        <div class="section-head">
            <h1>My Submitted Tickets</h1>
            <a class="btn btn-secondary btn-compact" href="<?= e($url('/tickets')) ?>">Refresh</a>
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
                        <tr>
                            <td><a href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a></td>
                            <td><?= e($ticket['subject'] ?? '') ?></td>
                            <td><?= e($ticket['location_name'] ?? '') ?></td>
                            <td><span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
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
