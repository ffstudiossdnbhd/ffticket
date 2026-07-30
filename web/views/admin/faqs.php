<section class="card page-header-card">
    <div class="page-heading">
        <div>
            <h1>FAQ Management</h1>
            <p>Create and maintain the answers shown in the FFTicket FAQ popup.</p>
        </div>
        <a class="icon-btn" href="<?= e($url('/admin/faqs')) ?>" aria-label="Refresh FAQs" title="Refresh FAQs"><span aria-hidden="true">&#xE72C;</span></a>
    </div>
</section>

<section class="card create-user-card">
    <div class="section-head"><div><h2>Add FAQ</h2><p>FAQs use a plain-text title and description.</p></div></div>
    <form method="post" action="<?= e($url('/admin/faqs/create')) ?>" class="faq-add-grid">
        <?= $csrf() ?>
        <label><span>Title</span><input type="text" name="title" maxlength="180" required></label>
        <label><span>Description</span><textarea name="description" rows="3" maxlength="5000" required></textarea></label>
        <button class="btn" type="submit">Add FAQ</button>
    </form>
</section>

<section class="card table-card">
    <?php foreach ($faqs as $faq): ?>
        <?php $id = (int)($faq['id'] ?? 0); ?>
        <form id="faq-update-<?= e($id) ?>" method="post" action="<?= e($url('/admin/faqs/update')) ?>"></form>
        <form id="faq-delete-<?= e($id) ?>" method="post" action="<?= e($url('/admin/faqs/delete')) ?>" data-confirm-delete></form>
    <?php endforeach; ?>
    <div class="section-head table-section-head"><div><h2>Existing FAQs</h2><p>Edit a row and save, or permanently delete it.</p></div></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Title</th><th>Description</th><th>Updated</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($faqs as $faq): ?>
            <?php $id = (int)($faq['id'] ?? 0); ?>
            <tr>
                <td>
                    <input form="faq-update-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                    <input form="faq-update-<?= e($id) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                    <input form="faq-delete-<?= e($id) ?>" type="hidden" name="_csrf" value="<?= e(\FFTicketWeb\Core\Csrf::token()) ?>">
                    <input form="faq-delete-<?= e($id) ?>" type="hidden" name="id" value="<?= e($id) ?>">
                    <input form="faq-update-<?= e($id) ?>" name="title" maxlength="180" value="<?= e($faq['title'] ?? '') ?>" required>
                </td>
                <td><textarea form="faq-update-<?= e($id) ?>" name="description" rows="3" maxlength="5000" required><?= e($faq['description'] ?? '') ?></textarea></td>
                <td><?= e($faq['updated_at'] ?? '') ?></td>
                <td class="table-actions"><button form="faq-update-<?= e($id) ?>" class="btn btn-compact" type="submit">Save</button><button form="faq-delete-<?= e($id) ?>" class="btn btn-secondary btn-compact" type="submit">Delete</button></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($faqs === []): ?><tr><td colspan="4" class="empty">No FAQs have been added.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>
