<?php
/**
 * Adds participants.lodge_affiliation ('den_17' | 'other' | NULL) — the
 * signup field the Session 2 plan flagged as needed for the profile-badge
 * system, built out in Session 8.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates the column per the updated
 * 001_initial_schema.sql. Safe to re-run.
 *
 * Usage: php migrations/005_add_lodge_affiliation.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$exists = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participants' AND COLUMN_NAME = 'lodge_affiliation'"
)->fetch();

if ((int) $exists['n'] > 0) {
    echo "participants.lodge_affiliation already exists — nothing to do.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE participants ADD COLUMN lodge_affiliation ENUM('den_17','other') NULL AFTER bio");
echo "Added participants.lodge_affiliation.\n";
