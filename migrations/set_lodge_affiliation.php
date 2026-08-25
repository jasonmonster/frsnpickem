<?php
/**
 * Quick one-off: set a participant's lodge affiliation directly from the
 * command line — same thing the /admin/participants roster page does now,
 * useful before that page exists on a given deploy, or just faster than
 * clicking through it for a single person.
 *
 * Usage:
 *   php migrations/set_lodge_affiliation.php <username> <den_17|other|not_elk> [season_year]
 *
 * Defaults to the active season if season_year isn't given. Not a schema
 * migration — doesn't need to be run more than once per person, and
 * running it again just overwrites with whatever value you pass.
 */

require __DIR__ . '/../src/Database.php';

$username = $argv[1] ?? null;
$value = $argv[2] ?? null;
$seasonYear = isset($argv[3]) ? (int) $argv[3] : null;

$valid = ['den_17', 'other', 'not_elk'];
if (!$username || !in_array($value, $valid, true)) {
    fwrite(STDERR, "Usage: php migrations/set_lodge_affiliation.php <username> <den_17|other|not_elk> [season_year]\n");
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
    'SELECT id, first_name, last_name FROM participants WHERE season_id = :season_id AND username = :username'
);
$stmt->execute([':season_id' => $season['id'], ':username' => $username]);
$participant = $stmt->fetch();

if (!$participant) {
    fwrite(STDERR, "No participant \"$username\" found in the {$season['year']} season.\n");
    exit(1);
}

$pdo->prepare('UPDATE participants SET lodge_affiliation = :lodge_affiliation WHERE id = :id')
    ->execute([':lodge_affiliation' => $value, ':id' => $participant['id']]);

$name = trim($participant['first_name'] . ' ' . $participant['last_name']);
echo "Done — $username ($name) is now set to \"$value\". ({$season['year']} season)\n";
