<?php
/**
 * @var int $week
 * @var array $games
 * @var array $lockTimes  ['thursday'=>?DateTimeImmutable, 'weekend'=>?DateTimeImmutable]
 * @var \DateTimeImmutable $now
 * @var array $picks  game_id => chosen_team_id
 * @var int|null $tiebreaker
 * @var array $teams  espn_id => team row
 * @var string|null $success
 * @var string|null $error
 */
use Pickem\Game;
use Pickem\NflTeam;
use Pickem\View;

$thursdayGames = array_values(array_filter($games, fn($g) => $g['lock_group'] === 'thursday'));
$weekendGames = array_values(array_filter($games, fn($g) => $g['lock_group'] === 'weekend'));
$thursdayLocked = $lockTimes['thursday'] !== null && $now >= $lockTimes['thursday'];
$weekendLocked = $lockTimes['weekend'] !== null && $now >= $lockTimes['weekend'];

$renderGameRow = function (array $game, bool $locked) use ($teams, $picks) {
    $away = $teams[$game['away_team_id']] ?? null;
    $home = $teams[$game['home_team_id']] ?? null;
    if ($away === null || $home === null) {
        return; // team data missing — shouldn't happen once teams are seeded, but don't fatal on it
    }
    $chosen = $picks[$game['id']] ?? null;
    $name = 'pick_' . (int) $game['id'];
    ?>
    <div class="game-row">
      <div class="game-meta">
        <span class="kickoff"><?= View::e(Game::formatKickoff($game['kickoff_at'])) ?></span>
        <?php if ($game['is_tiebreaker']): ?><span class="tiebreaker-tag">Tiebreaker</span><?php endif; ?>
      </div>
      <div class="matchup-picks">
        <label class="team-pick team-pick--away" style="background-color: <?= View::e($away['color_primary']) ?>">
          <input type="radio" name="<?= $name ?>" value="<?= (int) $game['away_team_id'] ?>"
            <?= $chosen === (int) $game['away_team_id'] ? 'checked' : '' ?>
            <?= $locked ? 'disabled' : '' ?>>
          <img class="team-pick-logo" src="/assets/team-logos/<?= View::e(NflTeam::logoStd($away)) ?>" alt="">
          <span class="team-pick-text">
            <span class="team-abbr"><?= View::e($away['abbr']) ?></span>
            <span class="team-name"><?= View::e($away['short_name']) ?></span>
          </span>
        </label>
        <span class="at">@</span>
        <label class="team-pick team-pick--home" style="background-color: <?= View::e($home['color_primary']) ?>">
          <input type="radio" name="<?= $name ?>" value="<?= (int) $game['home_team_id'] ?>"
            <?= $chosen === (int) $game['home_team_id'] ? 'checked' : '' ?>
            <?= $locked ? 'disabled' : '' ?>>
          <img class="team-pick-logo" src="/assets/team-logos/<?= View::e(NflTeam::logoStd($home)) ?>" alt="">
          <span class="team-pick-text">
            <span class="team-abbr"><?= View::e($home['abbr']) ?></span>
            <span class="team-name"><?= View::e($home['short_name']) ?></span>
          </span>
        </label>
      </div>
    </div>
    <?php
};
?>
<div class="card">
  <span class="kicker">Week <?= (int) $week ?></span>
  <h1>Make your picks</h1>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <?php if (empty($games)): ?>
    <p class="subtitle">No games loaded for week <?= (int) $week ?> yet — check back once they're synced.</p>
  <?php else: ?>
  <form method="post" action="/picks">
    <input type="hidden" name="week" value="<?= (int) $week ?>">

    <?php if (!empty($thursdayGames)): ?>
      <h2 class="section-label">
        Thursday game
        <?php if ($thursdayLocked): ?>
          <span class="lock-badge">Locked</span>
        <?php elseif ($lockTimes['thursday']): ?>
          <span class="lock-time">Locks <?= View::e(Game::formatKickoff($lockTimes['thursday']->format('Y-m-d H:i:s'))) ?></span>
        <?php endif; ?>
      </h2>
      <?php foreach ($thursdayGames as $game): ?>
        <?php $renderGameRow($game, $thursdayLocked); ?>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($weekendGames)): ?>
      <h2 class="section-label">
        Weekend games
        <?php if ($weekendLocked): ?>
          <span class="lock-badge">Locked</span>
        <?php elseif ($lockTimes['weekend']): ?>
          <span class="lock-time">Locks <?= View::e(Game::formatKickoff($lockTimes['weekend']->format('Y-m-d H:i:s'))) ?></span>
        <?php endif; ?>
      </h2>
      <?php foreach ($weekendGames as $game): ?>
        <?php $renderGameRow($game, $weekendLocked); ?>
      <?php endforeach; ?>

      <label for="tiebreaker">Tiebreaker — combined final score of the last game of the week</label>
      <input type="number" id="tiebreaker" name="tiebreaker" min="0" max="200"
             value="<?= $tiebreaker !== null ? (int) $tiebreaker : '' ?>"
             <?= $weekendLocked ? 'disabled' : '' ?>>
    <?php endif; ?>

    <button type="submit">Save picks</button>
  </form>
  <?php endif; ?>
</div>
