<?php
/**
 * Adds the college_teams reference table and participants.favorite_college_team_id
 * — replaces the old free-text favorite_college_team field with a dropdown,
 * same pattern as favorite_nfl_team_id. Session 10.
 *
 * This only creates the table/column — it doesn't seed any rows. Run
 * migrations/seed_college_teams.php right after this to actually load the
 * 362 schools and copy their logos in.
 *
 * Only needed on a database that already ran 000_bootstrap.php before this
 * change — a fresh bootstrap already creates both per the updated
 * 001_initial_schema.sql. Safe to re-run.
 *
 * Usage: php migrations/008_add_college_teams.php
 */

require __DIR__ . '/../src/Database.php';

$pdo = Pickem\Database::connect();

$tableExists = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'college_teams'"
)->fetch();

if ((int) $tableExists['n'] === 0) {
    $pdo->exec(
        "CREATE TABLE college_teams (
          espn_id       INT UNSIGNED PRIMARY KEY,
          slug          VARCHAR(128) NOT NULL,
          name          VARCHAR(128) NOT NULL,
          short_name    VARCHAR(64) NOT NULL,
          abbr          VARCHAR(8) NOT NULL,
          color_primary CHAR(7) NOT NULL,
          color_secondary CHAR(7) NOT NULL,
          on_navy       ENUM('std','dark','white') NOT NULL DEFAULT 'std',
          logo_std      VARCHAR(160) NOT NULL,
          logo_dark     VARCHAR(160) NOT NULL,
          logo_white    VARCHAR(160) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "Created college_teams.\n";
} else {
    echo "college_teams already exists — nothing to do.\n";
}

$columnExists = $pdo->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'participants' AND COLUMN_NAME = 'favorite_college_team_id'"
)->fetch();

if ((int) $columnExists['n'] === 0) {
    $pdo->exec(
        "ALTER TABLE participants
            ADD COLUMN favorite_college_team_id INT UNSIGNED NULL AFTER favorite_college_team,
            ADD CONSTRAINT fk_participants_fav_college FOREIGN KEY (favorite_college_team_id) REFERENCES college_teams(espn_id)"
    );
    echo "Added participants.favorite_college_team_id.\n";
} else {
    echo "participants.favorite_college_team_id already exists — nothing to do.\n";
}
