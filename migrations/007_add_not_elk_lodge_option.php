<?php
/**
 * Widens participants.lodge_affiliation from ENUM('den_17','other') to
 * ENUM('den_17','other','not_elk') — the signup/profile question changed
 * from an optional two-way pick to a required three-way one (Session 10):
 * "17 member", "lodge member" (another lodge), or "not an Elk". Existing
 * 'den_17'/'other' rows are untouched; this only widens what's allowed.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates the column per the updated
 * 001_initial_schema.sql. Safe to re-run.
 *
 * Usage: php migrations/007_add_not_elk_lodge_option.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$column = $pdo->query(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participants' AND COLUMN_NAME = 'lodge_affiliation'"
)->fetch();

if ($column === false) {
    fwrite(STDERR, "participants.lodge_affiliation doesn't exist yet — run migrations/005_add_lodge_affiliation.php first.\n");
    exit(1);
}

if (str_contains($column['COLUMN_TYPE'], 'not_elk')) {
    echo "participants.lodge_affiliation already allows 'not_elk' — nothing to do.\n";
    exit(0);
}

$pdo->exec("ALTER TABLE participants MODIFY COLUMN lodge_affiliation ENUM('den_17','other','not_elk') NULL");
echo "Widened participants.lodge_affiliation to allow 'not_elk'.\n";
