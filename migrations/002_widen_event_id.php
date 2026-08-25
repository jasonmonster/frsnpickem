<?php
/**
 * Widens games.espn_event_id from VARCHAR(32) to VARCHAR(64).
 *
 * Needed because the "espn_event_id" column now holds source-prefixed ids
 * from whichever feed synced a game — SportsBlaze's UUIDs ("sb_" + a
 * 36-char UUID = 39 chars) don't fit in 32. Column name is a holdover from
 * when ESPN was the only source; not worth a rename since nothing outside
 * this table cares about the column name itself, just that it's unique.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates the column at VARCHAR(64) per
 * the updated 001_initial_schema.sql. Safe to re-run.
 *
 * Usage: php migrations/002_widen_event_id.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$column = $pdo->query(
    "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'games' AND COLUMN_NAME = 'espn_event_id'"
)->fetch();

if ($column === false) {
    fwrite(STDERR, "Couldn't find games.espn_event_id — is the schema loaded?\n");
    exit(1);
}

if ((int) $column['CHARACTER_MAXIMUM_LENGTH'] >= 64) {
    echo "Already widened (currently VARCHAR({$column['CHARACTER_MAXIMUM_LENGTH']})) — nothing to do.\n";
    exit(0);
}

$pdo->exec('ALTER TABLE games MODIFY espn_event_id VARCHAR(64) NOT NULL');
echo "Widened games.espn_event_id from VARCHAR({$column['CHARACTER_MAXIMUM_LENGTH']}) to VARCHAR(64).\n";
