<?php
/**
 * Read-only public profile — any logged-in participant can view anyone
 * else's. Same info the owner sees on /profile, minus the edit forms.
 *
 * @var array $player
 * @var array $badges  see Badge::chipsFor()
 * @var bool $isSelf
 */
use Pickem\CollegeTeam;
use Pickem\NflTeam;
use Pickem\Participant;
use Pickem\View;

$favoriteNfl = !empty($player['favorite_nfl_team_id']) ? NflTeam::find((int) $player['favorite_nfl_team_id']) : null;
$favoriteCollege = !empty($player['favorite_college_team_id']) ? CollegeTeam::find((int) $player['favorite_college_team_id']) : null;
?>
<div class="card">
  <span class="kicker">Player</span>
  <div class="avatar-row">
    <img class="avatar avatar-lg" src="<?= View::e(View::avatarUrl($player)) ?>" alt="">
    <div>
      <div class="name"><?= View::e(Participant::displayName($player)) ?></div>
      <div class="username">@<?= View::e($player['username']) ?></div>
      <?php if (!empty($badges)): ?>
        <div class="badge-row" style="margin-top:6px;">
          <?php foreach ($badges as $b): ?>
            <span class="badge-chip"><?= $b['emoji'] ?> <?= View::e($b['label']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($favoriteNfl || $favoriteCollege): ?>
    <div style="margin-top:20px; display:flex; flex-direction:column; gap:10px;">
      <?php if ($favoriteNfl): ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <img src="/assets/team-logos/<?= View::e(NflTeam::logoStd($favoriteNfl)) ?>" alt="" style="width:32px;height:32px;object-fit:contain;">
          <span style="font-size:14px; color:var(--ink);">Roots for the <?= View::e($favoriteNfl['name']) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($favoriteCollege): ?>
        <div style="display:flex; align-items:center; gap:10px;">
          <img src="/assets/team-logos/<?= View::e(CollegeTeam::logoStd($favoriteCollege)) ?>" alt="" style="width:32px;height:32px;object-fit:contain;">
          <span style="font-size:14px; color:var(--ink);">College pick: <?= View::e($favoriteCollege['name']) ?></span>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($player['bio'])): ?>
    <p style="margin-top:16px; font-size:14px; color:var(--ink);"><?= nl2br(View::e($player['bio'])) ?></p>
  <?php endif; ?>

  <?php if ($isSelf): ?>
    <p class="hint" style="margin-top:20px;"><a href="/profile">Edit your profile →</a></p>
  <?php endif; ?>
</div>
