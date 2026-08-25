<?php
/** @var array $participant @var array $teams @var string|null $error @var string|null $success */
use Pickem\View;
?>
<div class="card">
  <span class="kicker">Your Profile</span>
  <h1>Edit profile</h1>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <div class="avatar-row">
    <img class="avatar avatar-lg" src="<?= View::e(View::avatarUrl($participant)) ?>" alt="">
    <div>
      <div class="name"><?= View::e($participant['first_name'] . ' ' . $participant['last_name']) ?></div>
      <div class="username">@<?= View::e($participant['username']) ?></div>
      <?php if (!empty($badges)): ?>
        <div class="badge-row" style="margin-top:6px;">
          <?php foreach ($badges as $b): ?>
            <span class="badge-chip"><?= $b['emoji'] ?> <?= View::e($b['label']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" action="/profile/photo" enctype="multipart/form-data">
    <label for="photo">Change photo</label>
    <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
    <button type="submit" class="btn-secondary">Upload</button>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--navy-tint)">

  <form method="post" action="/profile">
    <div class="field-row">
      <div>
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" value="<?= View::e($participant['first_name']) ?>" required>
      </div>
      <div>
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" value="<?= View::e($participant['last_name']) ?>" required>
      </div>
    </div>

    <label for="contact_email">Email</label>
    <input type="email" id="contact_email" name="contact_email" value="<?= View::e($participant['contact_email']) ?>">

    <label for="favorite_nfl_team_id">Favorite NFL team</label>
    <select id="favorite_nfl_team_id" name="favorite_nfl_team_id">
      <option value="">— pick one —</option>
      <?php foreach ($teams as $team): ?>
        <option value="<?= (int) $team['espn_id'] ?>" <?= ((int) $participant['favorite_nfl_team_id'] === (int) $team['espn_id']) ? 'selected' : '' ?>>
          <?= View::e($team['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="favorite_college_team">Favorite college team</label>
    <input type="text" id="favorite_college_team" name="favorite_college_team" value="<?= View::e($participant['favorite_college_team']) ?>">

    <label for="lodge_affiliation">Lodge</label>
    <select id="lodge_affiliation" name="lodge_affiliation">
      <option value="">— not set —</option>
      <option value="den_17" <?= ($participant['lodge_affiliation'] ?? '') === 'den_17' ? 'selected' : '' ?>>Den 17</option>
      <option value="other" <?= ($participant['lodge_affiliation'] ?? '') === 'other' ? 'selected' : '' ?>>Another lodge</option>
    </select>

    <label for="bio">Bio</label>
    <textarea id="bio" name="bio" maxlength="160"><?= View::e($participant['bio']) ?></textarea>

    <button type="submit">Save changes</button>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid var(--navy-tint)">

  <form method="post" action="/profile/pin">
    <label for="pin">Change PIN</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="New 4-digit PIN">
    <button type="submit" class="btn-secondary">Update PIN</button>
  </form>
</div>
