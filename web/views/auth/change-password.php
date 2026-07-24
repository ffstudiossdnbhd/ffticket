<section class="narrow-card card">
    <h1>Change Password</h1>
    <form method="post" action="<?= e($url('/change-password')) ?>" class="stack-form">
        <?= $csrf() ?>
        <label>
            <span>Current Password</span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label>
            <span>New Password</span>
            <input type="password" name="new_password" autocomplete="new-password" required>
        </label>
        <label>
            <span>Confirm New Password</span>
            <input type="password" name="confirm_password" autocomplete="new-password" required>
        </label>
        <button class="btn" type="submit">Change Password</button>
    </form>
</section>
