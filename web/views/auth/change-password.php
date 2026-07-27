<section class="narrow-card card">
    <div class="page-heading compact-heading">
        <div>
            <h1>Change Password</h1>
            <p>Update the password used to access your account.</p>
        </div>
    </div>
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
        <div class="form-actions">
            <a class="btn btn-secondary" href="<?= e($url('/dashboard')) ?>">Cancel</a>
            <button class="btn" type="submit">Change Password</button>
        </div>
    </form>
</section>
