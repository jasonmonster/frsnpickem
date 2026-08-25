<?php
/**
 * @var array $season
 * @var array $weeklyResults  see WeeklyResult::forSeason()
 * @var int $seasonHoldbackCents
 */
use Pickem\View;

$centsToDollars = fn(int $cents) => '$' . number_format($cents / 100, 2);
?>
<div class="card">
  <span class="kicker"><?= (int) $season['year'] ?> season</span>
  <h1>Results</h1>
  <p class="subtitle">Official weekly winners and pots — locked in once each week's payments are reconciled and the admin finalizes it.</p>

  <div class="success" style="background:#EFF3FA; border-color:var(--navy-tint); color:var(--navy);">
    Season holdback so far: <strong><?= $centsToDollars($seasonHoldbackCents) ?></strong> — split
    <?= (int) $season['payout_pct_first'] ?>/<?= (int) $season['payout_pct_second'] ?>/<?= (int) $season['payout_pct_third'] ?>%
    across the top three finishers at season's end.
  </div>

  <?php if (empty($weeklyResults)): ?>
    <p class="subtitle">No weeks finalized yet — check back after the first week wraps up.</p>
  <?php else: ?>
  <table class="standings">
    <thead>
      <tr>
        <th>Week</th>
        <th>Winner</th>
        <th>Pot</th>
        <th>Holdback</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($weeklyResults as $r): ?>
        <tr>
          <td><?= (int) $r['week_number'] ?></td>
          <td><?= $r['winner_participant_id'] ? View::e($r['first_name'] . ' ' . $r['last_name']) : '—' ?></td>
          <td><?= $centsToDollars((int) $r['pot_cents']) ?></td>
          <td><?= $centsToDollars((int) $r['holdback_cents']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
