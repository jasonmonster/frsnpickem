<?php
/**
 * Wipes games, picks, and tiebreaker answers for a season — leaves
 * participants, payments, and the season row untouched.
 *
 * Built for clearing out fake test-week data (see Session 2 in the build
 * plan: Week 1 was loaded as a stand-in since preseason had already wrapped)
 * before real signups open. Nothing here is finalized-week aware since that
 * doesn't exist yet (Phase 2) — this is safe precisely because no official
 * results exist yet to protect.
 *
 * Usage: php migrations/wipe_test_games.php [season_year]
 */

require __DIR__ . '/../src/Database.php';

$seasonYear = isset($argv[1]) ? (int) $argv[1] : null;
$pdo = Pickem\Database::connect();

if ($seasonYear !== null) {
    $stmt = $pdo->prepare('SELECT id, year FROM seasons WHERE year = :year');
    $stmt->execute([':year' => $seasonYear]);
} else {
    $stmt = $pdo->query("SELECT id, year FROM seasons WHERE status = 'active' ORDER BY year DESC LIMIT 1");
}
$season = $stmt->fetch();
if (!$season) {
    fwrite(STDERR, "No matching season found.\n");
    exit(1);
}

$pdo->beginTransaction();

$pdo->prepare(
    'DELETE FROM tiebreaker_answers WHERE participant_id IN (SELECT id FROM participants WHERE season_id = :season_id)'
)->execute([':season_id' => $season['id']]);

$pdo->prepare(
    'DELETE FROM picks WHERE game_id IN (SELECT id FROM games WHERE season_id = :season_id)'
)->execute([':season_id' => $season['id']]);

$deleteGames = $pdo->prepare('DELETE FROM games WHERE season_id = :season_id');
$deleteGames->execute([':season_id' => $season['id']]);
$count = $deleteGames->rowCount();

$pdo->commit();

echo "Wiped $count game(s), plus their picks and tiebreaker answers, from the {$season['year']} season.\n";
echo "Participants and payments were left untouched.\n";
