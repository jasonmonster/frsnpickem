<?php
/**
 * Promote (or demote) a participant to admin for a season.
 *
 * Usage:
 *   php migrations/promote_admin.php <username> [season_year] [--revoke]
 *
 * Defaults to the active season if season_year isn't given.
 *
 * is_admin doesn't do anything on its own yet — Session 1 has no admin UI
 * or admin-gated routes. It's here so the flag is set ahead of Session 2's
 * payment log and finalize-week action, which will check it.
 */

require __DIR__ . '/../src/Database.php';

$username = $argv[1] ?? null;
$revoke = in_array('--revoke', $argv, true);
$seasonYear = null;
foreach (array_slice($argv, 2) as $arg) {
    if ($arg !== '--revoke' && ctype_digit($arg)) {
        $seasonYear = (int) $arg;
    }
}

if (!$username) {
    fwrite(STDERR, "Usage: php migrations/promote_admin.php <username> [season_year] [--revoke]\n");
    exit(1);
}

$username = strtolower(trim($username));
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

$stmt = $pdo->prepare(
    'SELECT id, first_name, last_name, is_admin FROM participants WHERE season_id = :season_id AND username = :username'
);
$stmt->execute([':season_id' => $season['id'], ':username' => $username]);
$participant = $stmt->fetch();

if (!$participant) {
    fwrite(STDERR, "No participant \"$username\" found in the {$season['year']} season. Sign up first, then run this.\n");
    exit(1);
}

$pdo->prepare('UPDATE participants SET is_admin = :is_admin WHERE id = :id')
    ->execute([':is_admin' => $revoke ? 0 : 1, ':id' => $participant['id']]);

$name = trim($participant['first_name'] . ' ' . $participant['last_name']);
echo $revoke
    ? "Done — $username ($name) is no longer an admin. ({$season['year']} season)\n"
    : "Done — $username ($name) is now an admin. ({$season['year']} season)\n";
