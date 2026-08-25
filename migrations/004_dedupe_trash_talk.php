<?php
/**
 * One-time cleanup for the form-resubmission bug in Session 7: refreshing
 * the /talk page right after posting re-submitted the same POST, creating
 * a run of identical posts (same participant, same body, seconds apart)
 * every time the page reloaded. The bug itself is fixed (POST now redirects
 * instead of re-rendering), but the duplicate rows it already wrote are
 * still sitting in the database.
 *
 * This keeps the OLDEST post in each (participant_id, body) group and
 * deletes the rest, along with any votes on the deleted posts (a vote
 * legitimately cast on a duplicate has nowhere correct to go, so it's
 * dropped along with it — cheap to recast on the surviving post).
 *
 * Safe to re-run — a second pass finds nothing left to dedupe. Prints what
 * it deleted so you can eyeball it before trusting the result.
 *
 * Usage: php migrations/004_dedupe_trash_talk.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$votesTableExists = (int) $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trash_talk_votes'"
)->fetch()['n'] > 0;

$groups = $pdo->query(
    'SELECT participant_id, body, COUNT(*) AS n, MIN(id) AS keep_id
     FROM trash_talk
     GROUP BY participant_id, body
     HAVING COUNT(*) > 1'
)->fetchAll();

if (empty($groups)) {
    echo "No duplicate trash-talk posts found — nothing to do.\n";
    exit(0);
}

$totalDeleted = 0;
foreach ($groups as $g) {
    $stmt = $pdo->prepare(
        'SELECT id FROM trash_talk WHERE participant_id = :pid AND body = :body AND id != :keep_id'
    );
    $stmt->execute([':pid' => $g['participant_id'], ':body' => $g['body'], ':keep_id' => $g['keep_id']]);
    $dupeIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    if (empty($dupeIds)) {
        continue;
    }

    $placeholders = implode(',', array_fill(0, count($dupeIds), '?'));
    if ($votesTableExists) {
        $pdo->prepare("DELETE FROM trash_talk_votes WHERE trash_talk_id IN ($placeholders)")->execute($dupeIds);
    }
    $pdo->prepare("DELETE FROM trash_talk WHERE id IN ($placeholders)")->execute($dupeIds);

    $preview = mb_strlen($g['body']) > 40 ? mb_substr($g['body'], 0, 40) . '…' : $g['body'];
    echo "participant {$g['participant_id']}: kept post {$g['keep_id']}, deleted " . count($dupeIds) . " duplicate(s) of \"$preview\"\n";
    $totalDeleted += count($dupeIds);
}

echo "\nDone — deleted $totalDeleted duplicate post(s).\n";
