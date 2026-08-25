<?php
/**
 * @var array $participants  see Participant::activeForSeason()
 * @var string|null $success
 * @var string|null $error
 */
use Pickem\Participant;
use Pickem\View;
?>
<div class="card">
  <span class="kicker">Admin</span>
  <h1>Roster</h1>
  <p class="subtitle">Fix a lodge affiliation someone got wrong at signup, or set it for anyone who's stuck at "not set."</p>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <?php if (empty($participants)): ?>
    <p class="subtitle">No active participants this season yet.</p>
  <?php else: ?>
  <form method="post" action="/admin/participants">
    <?php foreach ($participants as $p): ?>
      <div style="display:flex; align-items:center; gap:10px; margin:10px 0;">
        <img class="avatar" src="<?= View::e(View::avatarUrl($p)) ?>" alt="" style="width:32px;height:32px;">
        <span style="flex:1 1 auto; font-size:14px; color:var(--ink);"><?= View::e(Participant::displayName($p)) ?> <span class="hint">@<?= View::e($p['username']) ?></span></span>
        <select name="lodge_<?= (int) $p['id'] ?>" style="width:auto; margin:0;">
          <option value="den_17" <?= $p['lodge_affiliation'] === 'den_17' ? 'selected' : '' ?>>17 member</option>
          <option value="other" <?= $p['lodge_affiliation'] === 'other' ? 'selected' : '' ?>>Lodge member</option>
          <option value="not_elk" <?= $p['lodge_affiliation'] === 'not_elk' ? 'selected' : '' ?>>Not an Elk</option>
          <option value="" <?= empty($p['lodge_affiliation']) ? 'selected' : '' ?>>— not set —</option>
        </select>
      </div>
    <?php endforeach; ?>
    <button type="submit">Save roster</button>
  </form>
  <?php endif; ?>
</div>
