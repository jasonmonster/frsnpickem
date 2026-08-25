<?php
use Pickem\View;
$old = $old ?? [];
?>
<div class="card">
  <span class="kicker">Elks Lodge Pilot</span>
  <h1>Log in</h1>
  <p class="subtitle">Username and PIN — stays logged in on this device until you log out.</p>

  <?php if (!empty($error)): ?>
    <div class="error"><?= View::e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/login">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= View::e($old['username'] ?? '') ?>" required autofocus>

    <label for="pin">PIN</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="••••" required>

    <button type="submit">Log in</button>
  </form>

  <div class="footer-link">New here? <a href="/signup">Sign up</a></div>
</div>
