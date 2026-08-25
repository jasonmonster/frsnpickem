<?php

namespace Pickem;

/**
 * Grades picks against final game results. This class never fetches
 * anything itself — it only reads what Game::sync*() already wrote
 * (games.status/home_score/away_score) and writes picks.is_correct from it.
 * Safe to call as often as you like: every run recomputes every final,
 * scored game's picks from scratch, so calling it twice in a row (or after
 * a score correction) just re-derives the current truth instead of
 * compounding anything.
 */
class Grading
{
    /**
     * Grade every pick attached to a final, scored game in this season.
     *
     * A tie (equal final scores — rare, but the NFL allows it) has no
     * straight-up winner, so every pick on that game is marked incorrect —
     * the simplest house rule, and the one most straight-up pick'em pools
     * use by default.
     *
     * @return int number of pick rows written this run
     */
    public static function gradeSeason(int $seasonId): int
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare(
            "SELECT id, home_team_id, away_team_id, home_score, away_score
             FROM games
             WHERE season_id = :season_id AND status = 'final'
               AND home_score IS NOT NULL AND away_score IS NOT NULL"
        );
        $stmt->execute([':season_id' => $seasonId]);
        $games = $stmt->fetchAll();

        if (empty($games)) {
            return 0;
        }

        $setCorrect = $pdo->prepare(
            'UPDATE picks SET is_correct = :is_correct WHERE game_id = :game_id AND chosen_team_id = :team_id'
        );
        $setAllIncorrect = $pdo->prepare('UPDATE picks SET is_correct = 0 WHERE game_id = :game_id');

        $touched = 0;
        foreach ($games as $game) {
            $gameId = (int) $game['id'];
            $homeScore = (int) $game['home_score'];
            $awayScore = (int) $game['away_score'];

            if ($homeScore === $awayScore) {
                $setAllIncorrect->execute([':game_id' => $gameId]);
                $touched += $setAllIncorrect->rowCount();
                continue;
            }

            $homeTeamId = (int) $game['home_team_id'];
            $awayTeamId = (int) $game['away_team_id'];
            $winnerId = $homeScore > $awayScore ? $homeTeamId : $awayTeamId;
            $loserId = $winnerId === $homeTeamId ? $awayTeamId : $homeTeamId;

            $setCorrect->execute([':is_correct' => 1, ':game_id' => $gameId, ':team_id' => $winnerId]);
            $touched += $setCorrect->rowCount();
            $setCorrect->execute([':is_correct' => 0, ':game_id' => $gameId, ':team_id' => $loserId]);
            $touched += $setCorrect->rowCount();
        }

        return $touched;
    }
}
