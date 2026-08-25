<?php
/**
 * @var int $week
 * @var array $season
 * @var array $statuses  see Payment::statusForWeek()
 * @var array|null $result  see WeeklyResult::find()
 * @var string|null $success
 * @var string|null $error
 */
use Pickem\Participant;
use Pickem\View;

$centsToDollars = fn(int $cents) => '$' . number_format($cents / 100, 2);
$paidCount = count(array_filter($statuses, fn($s) => $s['paid']));
?>
<div class="card">
  <span class="kicker">Admin</span>
  <h1>Payments — week <?= (int) $week ?></h1>
  <p class="subtitle">A reconciliation log, not a gate — picks work whether someone's marked paid or not. This is only what "finalize week" reads to decide who's in the official result.</p>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <form method="get" action="/admin/payments" style="display:flex; gap:8px; align-items:flex-end; margin-bottom:16px;">
    <div>
      <label for="week">Week</label>
      <input type="number" id="week" name="week" min="1" max="22" value="<?= (int) $week ?>">
    </div>
    <button type="submit" class="btn-secondary" style="margin-top:0;">View</button>
  </form>

  <?php if ($result): ?>
    <div class="success">
      Week <?= (int) $week ?> is finalized.
      Winner: <?= $result['winner_participant_id'] ? View::e($result['first_name'] . ' ' . $result['last_name']) : '(no winner — no paid participants)' ?>.
      Pot: <?= $centsToDollars((int) $result['pot_cents']) ?>. Holdback: <?= $centsToDollars((int) $result['holdback_cents']) ?>.
    </div>
  <?php endif; ?>

  <?php if (empty($statuses)): ?>
    <p class="subtitle">No active participants this season yet.</p>
  <?php else: ?>
  <form method="post" action="/admin/payments">
    <input type="hidden" name="week" value="<?= (int) $week ?>">
    <?php foreach ($statuses as $s): ?>
      <label style="display:flex; align-items:center; gap:10px; margin:10px 0; text-transform:none; font-weight:400; font-size:14px; color:var(--ink);">
        <input type="checkbox" name="paid_<?= (int) $s['id'] ?>" value="1" <?= $s['paid'] ? 'checked' : '' ?> style="width:auto;">
        <img class="avatar" src="<?= View::e(View::avatarUrl($s)) ?>" alt="" style="width:32px;height:32px;">
        <span><?= View::e(Participant::displayName($s)) ?></span>
        <?php if ($s['paid'] && $s['marked_at']): ?>
          <span class="hint">marked paid <?= View::e($s['marked_at']) ?><?= $s['marked_by'] ? ' by ' . View::e($s['marked_by']) : '' ?></span>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <button type="submit">Save payments (<?= $paidCount ?>/<?= count($statuses) ?> paid)</button>
  </form>

  <hr style="margin:24px 0;border:none;border-top:1px solid var(--navy-tint)">

  <form method="post" action="/admin/weekly-results/finalize">
    <input type="hidden" name="week" value="<?= (int) $week ?>">
    <p class="hint">Finalizing computes the week's winner from paid participants only, and splits that week's paid-in total (<?= (int) $season['weekly_buy_in_cents'] / 100 ?> &times; <?= $paidCount ?> paid = <?= $centsToDollars($paidCount * (int) $season['weekly_buy_in_cents']) ?>) into a pot and a season holdback (<?= (int) $season['holdback_pct'] ?>%). Every game in the week needs a final score first. Safe to re-run if a payment gets corrected later.</p>
    <button type="submit" class="btn-secondary"><?= $result ? 'Re-finalize week' : 'Finalize week' ?> <?= (int) $week ?></button>
  </form>
  <?php endif; ?>
</div>
