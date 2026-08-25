<?php
/**
 * @var array $season
 * @var int $week
 * @var array $standings  see Leaderboard::standings()
 */
use Pickem\Participant;
use Pickem\View;
?>
<div class="card">
  <span class="kicker">Week <?= (int) $week ?></span>
  <h1>Standings</h1>
  <p class="subtitle">Unofficial and live — updates every time games sync. Weekly pots and payouts get settled separately.</p>

  <?php if (empty($standings)): ?>
    <p class="subtitle">No participants yet.</p>
  <?php else: ?>
  <table class="standings">
    <thead>
      <tr>
        <th>#</th>
        <th>Player</th>
        <th>W</th>
        <th>L</th>
        <th>Pct</th>
      </tr>
    </thead>
    <tbody>
      <?php $rank = 0; $prevCorrect = null; $prevIncorrect = null; ?>
      <?php foreach ($standings as $i => $row): ?>
        <?php
          $p = $row['participant'];
          if ($row['correct'] !== $prevCorrect || $row['incorrect'] !== $prevIncorrect) {
              $rank = $i + 1;
              $prevCorrect = $row['correct'];
              $prevIncorrect = $row['incorrect'];
          }
        ?>
        <tr>
          <td><?= $rank ?></td>
          <td>
            <span class="standings-player">
              <img class="avatar" src="<?= View::e(View::avatarUrl($p)) ?>" alt="">
              <span><?= View::e(Participant::displayName($p)) ?></span>
            </span>
          </td>
          <td><?= (int) $row['correct'] ?></td>
          <td><?= (int) $row['incorrect'] ?></td>
          <td><?= $row['graded'] > 0 ? number_format((float) $row['pct'], 0) . '%' : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
