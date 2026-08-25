-- FRSN Pick'em — initial schema
-- Session 1: seasons, participants (auth+profile), nfl_teams reference.
-- Games/picks/payments/weekly_results/tiebreakers/trash_talk are laid down now
-- (per the build plan's full data model) so later sessions are additive, not
-- migrations against live data.

CREATE TABLE IF NOT EXISTS seasons (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year                  SMALLINT UNSIGNED NOT NULL,
  status                ENUM('active','archived') NOT NULL DEFAULT 'active',
  weekly_buy_in_cents   INT UNSIGNED NOT NULL DEFAULT 1000,
  holdback_pct          TINYINT UNSIGNED NOT NULL DEFAULT 20,
  payout_pct_first      TINYINT UNSIGNED NOT NULL DEFAULT 50,
  payout_pct_second     TINYINT UNSIGNED NOT NULL DEFAULT 30,
  payout_pct_third      TINYINT UNSIGNED NOT NULL DEFAULT 20,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_seasons_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nfl_teams (
  espn_id       SMALLINT UNSIGNED PRIMARY KEY,
  slug          VARCHAR(64) NOT NULL,
  name          VARCHAR(64) NOT NULL,
  short_name    VARCHAR(32) NOT NULL,
  abbr          CHAR(3) NOT NULL,
  color_primary CHAR(7) NOT NULL,
  color_secondary CHAR(7) NOT NULL,
  on_navy       ENUM('std','dark','white') NOT NULL DEFAULT 'std',
  logo_std      VARCHAR(128) NOT NULL,
  logo_dark     VARCHAR(128) NOT NULL,
  logo_white    VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS participants (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id             INT UNSIGNED NOT NULL,
  username              VARCHAR(32) NOT NULL,
  pin_hash              VARCHAR(255) NOT NULL,
  first_name            VARCHAR(64) NOT NULL,
  last_name             VARCHAR(64) NOT NULL,
  contact_email         VARCHAR(255) NULL,
  photo_path            VARCHAR(255) NULL,
  favorite_nfl_team_id  SMALLINT UNSIGNED NULL,
  favorite_college_team VARCHAR(128) NULL,
  bio                   VARCHAR(160) NULL,
  is_admin              TINYINT(1) NOT NULL DEFAULT 0,
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_participants_season_username (season_id, username),
  CONSTRAINT fk_participants_season FOREIGN KEY (season_id) REFERENCES seasons(id),
  CONSTRAINT fk_participants_fav_team FOREIGN KEY (favorite_nfl_team_id) REFERENCES nfl_teams(espn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS games (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id       INT UNSIGNED NOT NULL,
  week_number     TINYINT UNSIGNED NOT NULL,
  espn_event_id   VARCHAR(64) NOT NULL,
  home_team_id    SMALLINT UNSIGNED NOT NULL,
  away_team_id    SMALLINT UNSIGNED NOT NULL,
  kickoff_at      DATETIME NOT NULL,
  status          ENUM('scheduled','in_progress','final') NOT NULL DEFAULT 'scheduled',
  home_score      TINYINT UNSIGNED NULL,
  away_score      TINYINT UNSIGNED NULL,
  is_tiebreaker   TINYINT(1) NOT NULL DEFAULT 0,
  lock_group      ENUM('thursday','weekend') NOT NULL DEFAULT 'weekend',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_games_espn_event (espn_event_id),
  CONSTRAINT fk_games_season FOREIGN KEY (season_id) REFERENCES seasons(id),
  CONSTRAINT fk_games_home FOREIGN KEY (home_team_id) REFERENCES nfl_teams(espn_id),
  CONSTRAINT fk_games_away FOREIGN KEY (away_team_id) REFERENCES nfl_teams(espn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS picks (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id  INT UNSIGNED NOT NULL,
  game_id         INT UNSIGNED NOT NULL,
  chosen_team_id  SMALLINT UNSIGNED NOT NULL,
  is_correct      TINYINT(1) NULL,
  submitted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_picks_participant_game (participant_id, game_id),
  CONSTRAINT fk_picks_participant FOREIGN KEY (participant_id) REFERENCES participants(id),
  CONSTRAINT fk_picks_game FOREIGN KEY (game_id) REFERENCES games(id),
  CONSTRAINT fk_picks_team FOREIGN KEY (chosen_team_id) REFERENCES nfl_teams(espn_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiebreaker_answers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id  INT UNSIGNED NOT NULL,
  week_number     TINYINT UNSIGNED NOT NULL,
  guess_total     SMALLINT UNSIGNED NOT NULL,
  submitted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tiebreaker_participant_week (participant_id, week_number),
  CONSTRAINT fk_tiebreaker_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id  INT UNSIGNED NOT NULL,
  week_number     TINYINT UNSIGNED NOT NULL,
  paid            TINYINT(1) NOT NULL DEFAULT 0,
  marked_by       VARCHAR(64) NULL,
  marked_at       DATETIME NULL,
  UNIQUE KEY uq_payments_participant_week (participant_id, week_number),
  CONSTRAINT fk_payments_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS weekly_results (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id             INT UNSIGNED NOT NULL,
  week_number           TINYINT UNSIGNED NOT NULL,
  finalized             TINYINT(1) NOT NULL DEFAULT 0,
  finalized_at          DATETIME NULL,
  winner_participant_id INT UNSIGNED NULL,
  pot_cents             INT UNSIGNED NULL,
  holdback_cents        INT UNSIGNED NULL,
  UNIQUE KEY uq_weekly_results_season_week (season_id, week_number),
  CONSTRAINT fk_weekly_results_season FOREIGN KEY (season_id) REFERENCES seasons(id),
  CONSTRAINT fk_weekly_results_winner FOREIGN KEY (winner_participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trash_talk (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  season_id       INT UNSIGNED NOT NULL,
  participant_id  INT UNSIGNED NOT NULL,
  week_number     TINYINT UNSIGNED NULL,
  body            VARCHAR(500) NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trash_talk_season FOREIGN KEY (season_id) REFERENCES seasons(id),
  CONSTRAINT fk_trash_talk_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
