<?php
/**
 * @var array $posts  see TrashTalk::forSeason()
 * @var int $week
 * @var string|null $success
 * @var string|null $error
 * @var string|null $old
 */
use Pickem\Participant;
use Pickem\TrashTalk;
use Pickem\View;
?>
<div class="card">
  <span class="kicker">Week <?= (int) $week ?></span>
  <h1>Trash talk</h1>
  <p class="subtitle">Say your piece. Everyone in the pool sees this.</p>

  <?php if (!empty($error)): ?><div class="error"><?= View::e($error) ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="success"><?= View::e($success) ?></div><?php endif; ?>

  <form method="post" action="/talk">
    <label for="body">Post something</label>
    <textarea id="body" name="body" maxlength="<?= TrashTalk::MAX_LENGTH ?>" placeholder="Called it." required><?= View::e($old ?? '') ?></textarea>
    <button type="submit">Post</button>
  </form>

  <hr style="margin:24px 0;border:none;border-top:1px solid var(--navy-tint)">

  <?php if (empty($posts)): ?>
    <p class="subtitle">Nobody's said anything yet — be the first.</p>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
      <div class="talk-post" id="talk-<?= (int) $post['id'] ?>">
        <div class="vote-control">
          <form method="post" action="/talk/vote">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="vote-btn <?= (int) $post['my_vote'] === 1 ? 'active-up' : '' ?>" aria-label="Upvote">&#9650;</button>
          </form>
          <span class="vote-score"><?= (int) $post['score'] ?></span>
          <form method="post" action="/talk/vote">
            <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="vote-btn <?= (int) $post['my_vote'] === -1 ? 'active-down' : '' ?>" aria-label="Downvote">&#9660;</button>
          </form>
        </div>
        <img class="avatar" src="<?= View::e(View::avatarUrl($post)) ?>" alt="">
        <div class="talk-post-body">
          <div class="talk-post-meta">
            <span class="name"><?= View::e(Participant::displayName($post)) ?></span>
            <?php if ($post['week_number']): ?><span class="tiebreaker-tag">Week <?= (int) $post['week_number'] ?></span><?php endif; ?>
            <span class="hint"><?= View::e($post['created_at']) ?></span>
          </div>
          <div><?= nl2br(View::e($post['body'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
