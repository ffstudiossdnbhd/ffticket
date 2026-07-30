<?php
$defaultRelease = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->modify('+1 hour')->format('Y-m-d\TH:i');
?>
<section class="card page-header-card">
    <div class="page-heading">
        <div><h1>Timeouts</h1><p>Temporarily block staff and IT staff access. Release times use Malaysia time (MYT).</p></div>
        <a class="icon-btn" href="<?= e($url('/admin/timeouts')) ?>" aria-label="Refresh timeout users" title="Refresh users"><span aria-hidden="true">&#xE72C;</span></a>
    </div>
</section>

<section class="card table-card">
    <?php foreach ($timeoutUsers as $account): ?>
        <?php $id = (int)($account['id'] ?? 0); ?>
        <form id="timeout-save-<?= e($id) ?>" method="post" action="<?= e($url('/admin/timeouts/save')) ?>"></form>
        <form id="timeout-release-<?= e($id) ?>" method="post" action="<?= e($url('/admin/timeouts/release')) ?>" data-confirm-release></form>
    <?php endforeach; ?>
    <div class="section-head table-section-head"><div><h2>User access</h2><p>New sign-ins are blocked immediately. Active sessions receive a one-minute warning.</p></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Release time (MYT)</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($timeoutUsers as $account): ?>
            <?php
            $id = (int)($account['id'] ?? 0);
            $timedOut = (bool)($account['timed_out'] ?? false);
            $releaseValue = $timedOut ? str_replace(' ', 'T', (string)($account['release_at_myt'] ?? '')) : $defaultRelease;
            ?>
            <tr>
                <td>
                    <strong><?= e($account['name'] ?? '') ?></strong><br>
                    <small><?= e($account['email'] ?? '') ?></small>
                    <input form="timeout-save-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                    <input form="timeout-save-<?= e($id) ?>" type="hidden" name="user_id" value="<?= e($id) ?>">
                    <input form="timeout-save-<?= e($id) ?>" type="hidden" name="action" value="<?= $timedOut ? 'update' : 'start' ?>">
                    <input form="timeout-release-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                    <input form="timeout-release-<?= e($id) ?>" type="hidden" name="user_id" value="<?= e($id) ?>">
                </td>
                <td><?= e($account['role'] ?? '') ?></td>
                <td>
                    <?php if ($timedOut): ?><span class="status-text status-timeout">Timed out<?= ($account['timeout_warning'] ?? false) ? ' (warning)' : '' ?></span>
                    <?php elseif ($account['online'] ?? false): ?><span class="status-text status-online">Online</span>
                    <?php else: ?><span class="status-text">Offline</span><?php endif; ?>
                </td>
                <td><?php if ($account['can_timeout'] ?? false): ?><input form="timeout-save-<?= e($id) ?>" type="datetime-local" name="release_at" value="<?= e($releaseValue) ?>" required><?php else: ?>—<?php endif; ?></td>
                <td class="table-actions">
                    <?php if ($account['can_timeout'] ?? false): ?>
                        <button form="timeout-save-<?= e($id) ?>" class="btn btn-compact" type="submit"><?= $timedOut ? 'Change Time' : 'Start Timeout' ?></button>
                        <?php if ($timedOut): ?><button form="timeout-release-<?= e($id) ?>" class="btn btn-secondary btn-compact" type="submit">Release Now</button><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($timeoutUsers === []): ?><tr><td colspan="5" class="empty">No users found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
