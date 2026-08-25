<?php
/**
 * @var array $season
 * @var int $week
 * @var array $standings  see Leaderboard::standings()
 * @var array $weeklyWinnerWeeks  see Badge::weeklyWinnerWeeksBySeason()
 * @var array $perfectWeeks  see Badge::perfectWeeksBySeason()
 * @var array $tiebreakerAceWeeks  see Badge::tiebreakerAceWeeksBySeason()
 * @var array $ironManIds  see Badge::ironManParticipantIdsBySeason()
 * @var int|null $championId  see Badge::trashTalkChampion()
 * @var int|null $poopId  see Badge::trashTalkPoop()
 */
use Pickem\Badge;
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
              <?php
                $pid = (int) $p['id'];
                $lodge = Badge::lodgeBadge($p);
                $wins = $weeklyWinnerWeeks[$pid] ?? [];
                $perfects = $perfectWeeks[$pid] ?? [];
                $aces = $tiebreakerAceWeeks[$pid] ?? [];
                $isIronMan = in_array($pid, $ironManIds, true);
                $isChampion = $championId === $pid;
                $isPoop = $poopId === $pid;

                // Priority order for the row's limited width — lodge and
                // weekly wins first (most "who is this" signal), the rest
                // as room allows. Capped at MAX_INLINE with a "+N" overflow
                // chip rather than letting a badge-heavy player's row run
                // long — see Section 15's note on this.
                $rowChips = [];
                if ($lodge) {
                    $rowChips[] = ['text' => $lodge['emoji'], 'title' => $lodge['label']];
                }
                if ($wins) {
                    $rowChips[] = ['text' => '🥇×' . count($wins), 'title' => 'Won week' . (count($wins) === 1 ? '' : 's') . ' ' . implode(', ', $wins)];
                }
                if ($perfects) {
                    $rowChips[] = ['text' => '💯×' . count($perfects), 'title' => 'Perfect week' . (count($perfects) === 1 ? '' : 's') . ' ' . implode(', ', $perfects)];
                }
                if ($aces) {
                    $rowChips[] = ['text' => '🎯×' . count($aces), 'title' => 'Tiebreaker ace week' . (count($aces) === 1 ? '' : 's') . ' ' . implode(', ', $aces)];
                }
                if ($isIronMan) {
                    $rowChips[] = ['text' => '🛡️', 'title' => 'Iron Man'];
                }
                if ($isChampion) {
                    $rowChips[] = ['text' => '👑', 'title' => 'Trash Talk Champion'];
                }
                if ($isPoop) {
                    $rowChips[] = ['text' => '💩', 'title' => 'Poop Award'];
                }

                $maxInline = 4;
                $visibleChips = array_slice($rowChips, 0, $maxInline);
                $hiddenChips = array_slice($rowChips, $maxInline);
              ?>
              <?php if ($rowChips): ?>
                <span class="badge-row">
                  <?php foreach ($visibleChips as $chip): ?>
                    <span class="badge-chip" title="<?= View::e($chip['title']) ?>"><?= $chip['text'] ?></span>
                  <?php endforeach; ?>
                  <?php if ($hiddenChips): ?>
                    <span class="badge-chip" title="<?= View::e(implode(' · ', array_column($hiddenChips, 'title'))) ?>">+<?= count($hiddenChips) ?></span>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
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
