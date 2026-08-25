<?php
/**
 * @var string|null $error
 * @var array $old
 * @var array $teams
 */
use Pickem\View;
$old = $old ?? [];
?>
<div class="card">
  <span class="kicker">Elks Lodge Pilot</span>
  <h1>Sign up</h1>
  <p class="subtitle">Pick winners, talk trash, win the pot. Free to join — you'll pay your weekly $10 in person at the bar.</p>

  <?php if (!empty($error)): ?>
    <div class="error"><?= View::e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="/signup" enctype="multipart/form-data">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= View::e($old['username'] ?? '') ?>" placeholder="lowercase, no spaces" required>
    <div class="hint">This plus your PIN is how you log in. 3–32 characters, letters/numbers/periods/underscores.</div>

    <label for="pin">Choose a 4-digit PIN</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="••••" required>

    <div class="field-row">
      <div>
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" value="<?= View::e($old['first_name'] ?? '') ?>" required>
      </div>
      <div>
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" value="<?= View::e($old['last_name'] ?? '') ?>" required>
      </div>
    </div>

    <label for="contact_email">Email <span style="text-transform:none;font-weight:400;color:var(--muted)">(for weekly updates)</span></label>
    <input type="email" id="contact_email" name="contact_email" value="<?= View::e($old['contact_email'] ?? '') ?>">

    <label for="photo">Profile photo <span style="text-transform:none;font-weight:400;color:var(--muted)">(optional)</span></label>
    <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
    <div class="hint">Skip it and your favorite team's logo stands in instead.</div>

    <label for="favorite_nfl_team_id">Favorite NFL team</label>
    <select id="favorite_nfl_team_id" name="favorite_nfl_team_id">
      <option value="">— pick one —</option>
      <?php foreach ($teams as $team): ?>
        <option value="<?= (int) $team['espn_id'] ?>" <?= (($old['favorite_nfl_team_id'] ?? '') == $team['espn_id']) ? 'selected' : '' ?>>
          <?= View::e($team['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="favorite_college_team">Favorite college team <span style="text-transform:none;font-weight:400;color:var(--muted)">(optional, just for fun)</span></label>
    <input type="text" id="favorite_college_team" name="favorite_college_team" value="<?= View::e($old['favorite_college_team'] ?? '') ?>" placeholder="e.g. Colorado Buffaloes">

    <label for="lodge_affiliation">Are you an Elk?</label>
    <select id="lodge_affiliation" name="lodge_affiliation" required>
      <option value="" disabled <?= empty($old['lodge_affiliation']) ? 'selected' : '' ?>>— choose one —</option>
      <option value="den_17" <?= ($old['lodge_affiliation'] ?? '') === 'den_17' ? 'selected' : '' ?>>17 member</option>
      <option value="other" <?= ($old['lodge_affiliation'] ?? '') === 'other' ? 'selected' : '' ?>>Lodge member (another lodge)</option>
      <option value="not_elk" <?= ($old['lodge_affiliation'] ?? '') === 'not_elk' ? 'selected' : '' ?>>Not an Elk</option>
    </select>

    <label for="bio">Bio <span style="text-transform:none;font-weight:400;color:var(--muted)">(optional, one line)</span></label>
    <textarea id="bio" name="bio" maxlength="160"><?= View::e($old['bio'] ?? '') ?></textarea>

    <button type="submit">Join the pool</button>
  </form>

  <div class="footer-link">Already signed up? <a href="/login">Log in</a></div>
</div>
