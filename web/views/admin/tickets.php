<section class="card page-header-card">
    <div class="page-heading">
        <div>
            <h1>Ticket Overview</h1>
            <p>Review, assign, and resolve support requests.</p>
        </div>
        <a class="btn" href="<?= e($url('/reports/export?from=' . urlencode((string)($filters['from'] ?? '')) . '&to=' . urlencode((string)($filters['to'] ?? '')))) ?>">
            <span class="button-icon" aria-hidden="true">&#xE74E;</span>
            Export CSV
        </a>
    </div>

    <form method="get" action="<?= e($url('/admin/tickets')) ?>" class="filter-grid" data-date-filter-form>
        <label class="filter-search">
            <span>Search</span>
            <input type="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Ticket, subject, or creator">
        </label>
        <label>
            <span>Status</span>
            <select name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>"<?= selected($filters['status'] ?? 'All', $status) ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Urgency</span>
            <select name="urgency">
                <option value="All">All</option>
                <?php foreach ($urgencyTypes as $urgency): ?>
                    <option value="<?= e($urgency['name'] ?? '') ?>"<?= selected($filters['urgency'] ?? 'All', $urgency['name'] ?? '') ?>><?= e($urgency['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Created From</span>
            <input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>" data-auto-filter>
        </label>
        <label>
            <span>Created To</span>
            <input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>" data-auto-filter>
        </label>
        <div class="filter-actions">
            <button class="btn btn-secondary" type="submit">Apply Filters</button>
            <a class="icon-btn" href="<?= e($url('/admin/tickets')) ?>" aria-label="Reset and refresh filters" title="Reset and refresh filters">
                <span aria-hidden="true">&#xE72C;</span>
            </a>
        </div>
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
                    <tr class="ticket-row" data-href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>">
                        <td><a href="<?= e($url('/tickets/' . (int)$ticket['id'])) ?>"><?= e($ticket['ticket_number'] ?? '') ?></a></td>
                        <td><?= e($ticket['subject'] ?? '') ?></td>
                        <td><?= e($ticket['creator_name'] ?? '') ?></td>
                        <td><?= e($ticket['category_name'] ?? '') ?></td>
                        <td><?= e($ticket['location_name'] ?? '') ?></td>
                        <td class="badge-cell"><span class="badge <?= e(badge_class('status', $ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
                        <td class="badge-cell"><?php if (($ticket['urgency'] ?? '') !== ''): ?><span class="badge <?= e(badge_class('urgency', $ticket['urgency'] ?? '')) ?>"><?= e($ticket['urgency']) ?></span><?php endif; ?></td>
                        <td><?= e($ticket['assignee_name'] ?? '') ?></td>
                        <td><?= e($ticket['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tickets === []): ?>
                    <tr><td colspan="9" class="empty">No tickets match these filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card selected-ticket-card">
    <div class="section-head">
        <div>
            <h2>Update Ticket</h2>
            <p>Enter a ticket ID to assign it or change its workflow status.</p>
        </div>
    </div>
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
