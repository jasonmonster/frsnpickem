<?php
/** @var array $participant */
use Pickem\View;
?>
<div class="card">
  <div class="avatar-row">
    <img class="avatar" src="<?= View::e(View::avatarUrl($participant)) ?>" alt="">
    <div>
      <div class="name">Welcome back, <?= View::e($participant['first_name']) ?></div>
      <div class="username">@<?= View::e($participant['username']) ?></div>
    </div>
  </div>
  <p class="subtitle">You're in the pool. Get your picks in, then check the standings to see where you land.</p>
  <a class="btn" href="/picks">Make your picks</a>
  <a class="btn btn-secondary" href="/leaderboard">Standings</a>
  <a class="btn btn-secondary" href="/profile">Edit your profile</a>
  <?php if (!empty($participant['is_admin'])): ?>
    <a class="btn btn-secondary" href="/admin/games">Admin: sync games</a>
  <?php endif; ?>
</div>
