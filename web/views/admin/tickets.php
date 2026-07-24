<section class="card filters-card">
    <form method="get" action="<?= e($url('/admin/tickets')) ?>" class="filter-grid">
        <input type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search tickets">
        <select name="status">
            <?php foreach ($statuses as $status): ?>
                <option value="<?= e($status) ?>"<?= selected($filters['status'] ?? 'All', $status) ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="urgency">
            <option value="All">All</option>
            <?php foreach ($urgencyTypes as $urgency): ?>
                <option value="<?= e($urgency['name'] ?? '') ?>"<?= selected($filters['urgency'] ?? 'All', $urgency['name'] ?? '') ?>><?= e($urgency['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
        <a class="btn btn-secondary" href="<?= e($url('/admin/tickets')) ?>">Refresh</a>
        <input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
        <input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
        <a class="btn" href="<?= e($url('/reports/export?from=' . urlencode((string)($filters['from'] ?? '')) . '&to=' . urlencode((string)($filters['to'] ?? '')))) ?>">Export CSV</a>
    </form>
</section>

<section class="card table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Subject</th>
                    <th>Creator</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Urgency</th>
                    <th>Assigned</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><a href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a></td>
                        <td><?= e($ticket['subject'] ?? '') ?></td>
                        <td><?= e($ticket['creator_name'] ?? '') ?></td>
                        <td><?= e($ticket['category_name'] ?? '') ?></td>
                        <td><?= e($ticket['location_name'] ?? '') ?></td>
                        <td><span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
                        <td><?php if (($ticket['urgency'] ?? '') !== ''): ?><span class="badge <?= e(badge_class('urgency', $ticket['urgency'] ?? '')) ?>"><?= e($ticket['urgency']) ?></span><?php endif; ?></td>
                        <td><?= e($ticket['assignee_name'] ?? '') ?></td>
                        <td><?= e($ticket['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tickets === []): ?>
                    <tr><td colspan="9" class="empty">No tickets found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Selected Ticket</h2>
    <form method="post" action="<?= e($url('/admin/tickets/update')) ?>" class="inline-grid">
        <?= $csrf() ?>
        <label>
            <span>Ticket ID</span>
            <input type="number" name="id" min="1" placeholder="ID" required>
        </label>
        <label>
            <span>Status</span>
            <select name="status">
                <?php foreach ($mutationStatuses as $status): ?>
                    <option value="<?= e($status) ?>"><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Urgency</span>
            <select name="urgency_type_id">
                <option value="">No change</option>
                <?php foreach ($urgencyTypes as $urgency): ?>
                    <?php if ($urgency['is_active'] ?? false): ?>
                        <option value="<?= e($urgency['id'] ?? '') ?>"><?= e($urgency['name'] ?? '') ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Assigned</span>
            <select name="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($users as $assignee): ?>
                    <option value="<?= e($assignee['id'] ?? '') ?>"><?= e($assignee['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit">Apply Changes</button>
    </form>
</section>
