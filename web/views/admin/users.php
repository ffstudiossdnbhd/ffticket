<section class="card">
    <form method="post" action="<?= e($url('/admin/users/create')) ?>" class="user-create-grid">
        <?= $csrf() ?>
        <input type="text" name="name" maxlength="120" placeholder="Name" required>
        <input type="text" name="nickname" maxlength="120" placeholder="Nickname">
        <input type="email" name="email" maxlength="190" placeholder="Email" required>
        <input type="text" name="password" placeholder="Password optional">
        <select name="role">
            <?php foreach ($roles as $role): ?>
                <option value="<?= e($role) ?>"<?= selected($role, 'staff') ?>><?= e($role) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Add User</button>
    </form>
</section>

<section class="card table-card">
    <?php foreach ($users as $account): ?>
        <form id="update-user-<?= e($account['id'] ?? '') ?>" method="post" action="<?= e($url('/admin/users/update')) ?>"></form>
        <form id="delete-user-<?= e($account['id'] ?? '') ?>" method="post" action="<?= e($url('/admin/users/delete')) ?>"></form>
    <?php endforeach; ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Nickname</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Reset Password</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $account): ?>
                    <?php $id = (int)($account['id'] ?? 0); ?>
                    <tr>
                        <td>
                            <?= e($id) ?>
                            <input form="update-user-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                            <input form="delete-user-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                            <input form="update-user-<?= e($id) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                            <input form="delete-user-<?= e($id) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                        </td>
                        <td><input form="update-user-<?= e($id) ?>" name="name" value="<?= e($account['name'] ?? '') ?>" maxlength="120"></td>
                        <td><input form="update-user-<?= e($id) ?>" name="nickname" value="<?= e($account['nickname'] ?? '') ?>" maxlength="120"></td>
                        <td><input form="update-user-<?= e($id) ?>" type="email" name="email" value="<?= e($account['email'] ?? '') ?>" maxlength="190"></td>
                        <td>
                            <select form="update-user-<?= e($id) ?>" name="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= e($role) ?>"<?= selected($account['role'] ?? '', $role) ?>><?= e($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input form="update-user-<?= e($id) ?>" name="password" type="text" placeholder="New password"></td>
                        <td><?= e($account['created_at'] ?? '') ?></td>
                        <td class="table-actions">
                            <button form="update-user-<?= e($id) ?>" class="btn btn-compact" type="submit">Save</button>
                            <button form="delete-user-<?= e($id) ?>" class="btn btn-secondary btn-compact" type="submit">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?>
                    <tr><td colspan="8" class="empty">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
