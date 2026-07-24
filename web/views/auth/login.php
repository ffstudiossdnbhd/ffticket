<div class="login-brand">
    <img src="<?= e($asset('app-icon.png')) ?>" alt="" class="login-icon">
    <div>
        <h1>FFTicket</h1>
        <p>IT Support Ticketing</p>
    </div>
</div>

<form method="post" action="<?= e($url('/login')) ?>" class="stack-form">
    <?= $csrf() ?>
    <label>
        <span>Email</span>
        <input type="email" name="email" placeholder="Email address" autocomplete="email" required>
    </label>
    <label>
        <span>Password</span>
        <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <label class="check-row">
        <input type="checkbox" name="remember" checked>
        <span>Remember me</span>
    </label>
    <button class="btn" type="submit">Sign in</button>
</form>
