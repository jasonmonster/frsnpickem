<?php
/**
 * Adds weekly_results.tiebreaker_participant_id — set by WeeklyResult::
 * finalize() only when a week's official winner was actually decided by
 * the tiebreaker guess (a real tie among paid participants), not just the
 * most correct picks outright. Feeds the new tiebreaker-ace badge
 * (Badge::tiebreakerAceWeeks()), part of the Session 9 badge expansion.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates the column per the updated
 * 001_initial_schema.sql. Safe to re-run.
 *
 * Usage: php migrations/006_add_tiebreaker_badge.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$exists = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'weekly_results' AND COLUMN_NAME = 'tiebreaker_participant_id'"
)->fetch();

if ((int) $exists['n'] > 0) {
    echo "weekly_results.tiebreaker_participant_id already exists — nothing to do.\n";
    exit(0);
}

$pdo->exec(
    "ALTER TABLE weekly_results
        ADD COLUMN tiebreaker_participant_id INT UNSIGNED NULL AFTER winner_participant_id,
        ADD CONSTRAINT fk_weekly_results_tiebreaker FOREIGN KEY (tiebreaker_participant_id) REFERENCES participants(id)"
);
echo "Added weekly_results.tiebreaker_participant_id.\n";
