<?php
$activeType = (string)($_GET['type'] ?? 'categories');
if (!array_key_exists($activeType, $groups)) {
    $activeType = 'categories';
}
?>
<section class="option-tabs">
    <?php foreach ($groups as $type => $group): ?>
        <a href="<?= e($url('/admin/customize?type=' . urlencode($type))) ?>" class="<?= $activeType === $type ? 'active' : '' ?>"><?= e($group['label']) ?></a>
    <?php endforeach; ?>
</section>

<?php foreach ($groups as $type => $group): ?>
    <?php if ($activeType !== $type) { continue; } ?>
    <section class="card">
        <form method="post" action="<?= e($url('/admin/customize/add')) ?>" class="option-add-grid">
            <?= $csrf() ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="text" name="name" maxlength="100" placeholder="Name" required>
            <input type="text" name="description" maxlength="255" placeholder="Description">
            <button class="btn" type="submit"><?= e($group['add']) ?></button>
        </form>
    </section>

    <section class="card table-card">
        <?php foreach ($group['items'] as $option): ?>
            <form id="update-option-<?= e($type) ?>-<?= e($option['id'] ?? '') ?>" method="post" action="<?= e($url('/admin/customize/update')) ?>"></form>
            <form id="delete-option-<?= e($type) ?>-<?= e($option['id'] ?? '') ?>" method="post" action="<?= e($url('/admin/customize/deactivate')) ?>"></form>
        <?php endforeach; ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['items'] as $option): ?>
                        <?php $id = (int)($option['id'] ?? 0); $formId = 'update-option-' . $type . '-' . $id; $deleteId = 'delete-option-' . $type . '-' . $id; ?>
                        <tr>
                            <td>
                                <?= e($id) ?>
                                <input form="<?= e($formId) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                                <input form="<?= e($deleteId) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                                <input form="<?= e($formId) ?>" type="hidden" name="type" value="<?= e($type) ?>">
                                <input form="<?= e($deleteId) ?>" type="hidden" name="type" value="<?= e($type) ?>">
                                <input form="<?= e($formId) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                                <input form="<?= e($deleteId) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                            </td>
                            <td><input form="<?= e($formId) ?>" name="name" value="<?= e($option['name'] ?? '') ?>" maxlength="100"></td>
                            <td><input form="<?= e($formId) ?>" name="description" value="<?= e($option['description'] ?? '') ?>" maxlength="255"></td>
                            <td><input form="<?= e($formId) ?>" type="checkbox" name="is_active"<?= checked($option['is_active'] ?? false) ?>></td>
                            <td class="table-actions">
                                <button form="<?= e($formId) ?>" class="btn btn-compact" type="submit">Save</button>
                                <button form="<?= e($deleteId) ?>" class="btn btn-secondary btn-compact" type="submit">Deactivate</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($group['items'] === []): ?>
                        <tr><td colspan="5" class="empty">No options found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endforeach; ?>
