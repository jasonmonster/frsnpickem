<?php
/**
 * Adds the trash_talk_votes table for Reddit-style up/down voting on
 * trash-talk posts.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates the table per the updated
 * 001_initial_schema.sql. Safe to re-run (CREATE TABLE IF NOT EXISTS).
 *
 * Usage: php migrations/003_add_trash_talk_votes.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$exists = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trash_talk_votes'"
)->fetch();

if ((int) $exists['n'] > 0) {
    echo "trash_talk_votes already exists — nothing to do.\n";
    exit(0);
}

$pdo->exec(
    'CREATE TABLE trash_talk_votes (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        trash_talk_id   INT UNSIGNED NOT NULL,
        participant_id  INT UNSIGNED NOT NULL,
        value           TINYINT NOT NULL,
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_trash_talk_votes_post_participant (trash_talk_id, participant_id),
        CONSTRAINT fk_trash_talk_votes_post FOREIGN KEY (trash_talk_id) REFERENCES trash_talk(id),
        CONSTRAINT fk_trash_talk_votes_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

echo "Created trash_talk_votes.\n";
