<?php
/**
 * @var int $week
 * @var array $games
 * @var array $teams
 * @var array $season
 * @var string|null $success
 * @var string|null $error
 */
use Pickem\Game;
use Pickem\View;
?>
<div class="card">
  <span class="kicker">Admin</span>
  <h1>Games — week <?= (int) $week ?></h1>
  <p class="subtitle">Pulls the week's matchups and kickoff times for the week. Safe to run again — it updates in place rather than duplicating.</p>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <form method="get" action="/admin/games" style="display:flex; gap:8px; align-items:flex-end; margin-bottom:16px;">
    <div>
      <label for="week">Week</label>
      <input type="number" id="week" name="week" min="1" max="22" value="<?= (int) $week ?>">
    </div>
    <button type="submit" class="btn-secondary" style="margin-top:0;">View</button>
  </form>

  <form method="post" action="/admin/games/sync">
    <input type="hidden" name="week" value="<?= (int) $week ?>">
    <button type="submit">Sync week <?= (int) $week ?></button>
  </form>

  <hr style="margin:24px 0;border:none;border-top:1px solid var(--navy-tint)">

  <?php if (empty($games)): ?>
    <p class="subtitle">Nothing synced for week <?= (int) $week ?> yet.</p>
  <?php else: ?>
    <?php foreach ($games as $game): $away = $teams[$game['away_team_id']] ?? null; $home = $teams[$game['home_team_id']] ?? null; ?>
      <div class="game-row">
        <div class="matchup">
          <?= View::e($away['abbr'] ?? '?') ?> @ <?= View::e($home['abbr'] ?? '?') ?>
          <span class="kickoff"><?= View::e(Game::formatKickoff($game['kickoff_at'])) ?></span>
        </div>
        <div class="hint">
          <?= View::e($game['lock_group']) ?> tier
          <?= $game['is_tiebreaker'] ? ' · tiebreaker' : '' ?>
          · <?= View::e($game['status']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
