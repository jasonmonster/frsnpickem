<?php

namespace Pickem;

/**
 * The official, money-attached weekly result — distinct from the live
 * unofficial leaderboard (Leaderboard.php), which counts everyone
 * regardless of payment. A week only gets a row here once an admin
 * finalizes it, after reconciling that week's payment log.
 */
class WeeklyResult
{
    /**
     * Compute and write the official result for a week: the winner among
     * paid participants only (most correct picks on that week's games,
     * tiebreaker-guess accuracy as the decider), plus the pot/holdback split
     * off that week's paid-in total (Season.weekly_buy_in_cents *
     * paid-participant count, split by Season.holdback_pct).
     *
     * Safe to call again later — re-finalizing recomputes from scratch and
     * overwrites the existing row, so correcting a payment after the fact
     * and re-running this just updates the record instead of duplicating it.
     *
     * @throws \RuntimeException if the week has no synced games, or any of
     *     them isn't final yet — grading and the tiebreaker both need a
     *     finished week to mean anything.
     */
    public static function finalize(int $seasonId, int $weekNumber): array
    {
        $pdo = Database::connect();

        $games = Game::forWeek($seasonId, $weekNumber);
        if (empty($games)) {
            throw new \RuntimeException("No games synced for week $weekNumber yet — nothing to finalize.");
        }
        foreach ($games as $game) {
            if ($game['status'] !== 'final') {
                throw new \RuntimeException(
                    "Week $weekNumber isn't final yet — every game needs a final score before the week can be finalized."
                );
            }
        }

        // Make sure is_correct reflects this week's final scores before reading it.
        Grading::gradeSeason($seasonId);

        $stmt = $pdo->prepare(
            "SELECT part.id, part.first_name, part.last_name,
                COALESCE(SUM(CASE WHEN pk.is_correct = 1 THEN 1 ELSE 0 END), 0) AS correct
             FROM participants part
             JOIN payments pay ON pay.participant_id = part.id AND pay.week_number = :week_a AND pay.paid = 1
             LEFT JOIN games g ON g.season_id = :season_id_a AND g.week_number = :week_b
             LEFT JOIN picks pk ON pk.participant_id = part.id AND pk.game_id = g.id
             WHERE part.season_id = :season_id_b
             GROUP BY part.id
             ORDER BY correct DESC"
        );
        $stmt->execute([
            ':week_a' => $weekNumber,
            ':season_id_a' => $seasonId,
            ':week_b' => $weekNumber,
            ':season_id_b' => $seasonId,
        ]);
        $standings = $stmt->fetchAll();

        $winnerId = null;
        if (!empty($standings)) {
            $topScore = (int) $standings[0]['correct'];
            $contenders = array_values(array_filter($standings, fn($r) => (int) $r['correct'] === $topScore));

            if (count($contenders) === 1) {
                $winnerId = (int) $contenders[0]['id'];
            } else {
                // Tie among paid participants — break it with the tiebreaker
                // guess, closest to the actual combined score of the week's
                // tiebreaker game.
                $tiebreakerGame = null;
                foreach ($games as $g) {
                    if ($g['is_tiebreaker']) {
                        $tiebreakerGame = $g;
                        break;
                    }
                }

                $guesses = [];
                if ($tiebreakerGame !== null) {
                    $ids = array_map(fn($r) => (int) $r['id'], $contenders);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $tbStmt = $pdo->prepare(
                        "SELECT participant_id, guess_total FROM tiebreaker_answers
                         WHERE week_number = ? AND participant_id IN ($placeholders)"
                    );
                    $tbStmt->execute(array_merge([$weekNumber], $ids));
                    foreach ($tbStmt->fetchAll() as $row) {
                        $guesses[(int) $row['participant_id']] = (int) $row['guess_total'];
                    }
                }

                $actualTotal = $tiebreakerGame !== null
                    ? (int) $tiebreakerGame['home_score'] + (int) $tiebreakerGame['away_score']
                    : null;

                // Closest guess wins; anyone who never submitted a guess sorts
                // last. A remaining tie (identical guesses, or no tiebreaker
                // game / nobody guessed) falls back to name order —
                // deterministic, but a genuine deadlock here needs a human
                // call, same as the plan flags for a season-end tie.
                usort($contenders, function ($a, $b) use ($guesses, $actualTotal) {
                    $da = ($actualTotal !== null && array_key_exists((int) $a['id'], $guesses))
                        ? abs($guesses[(int) $a['id']] - $actualTotal) : PHP_INT_MAX;
                    $db = ($actualTotal !== null && array_key_exists((int) $b['id'], $guesses))
                        ? abs($guesses[(int) $b['id']] - $actualTotal) : PHP_INT_MAX;
                    if ($da !== $db) {
                        return $da <=> $db;
                    }
                    return strcmp($a['first_name'] . $a['last_name'], $b['first_name'] . $b['last_name']);
                });
                $winnerId = (int) $contenders[0]['id'];
            }
        }

        $season = Season::find($seasonId);
        $paidCount = Payment::paidCount($seasonId, $weekNumber);
        $totalCents = $paidCount * (int) $season['weekly_buy_in_cents'];
        $holdbackCents = (int) round($totalCents * ((int) $season['holdback_pct'] / 100));
        $potCents = $totalCents - $holdbackCents;

        $upsert = $pdo->prepare(
            'INSERT INTO weekly_results (season_id, week_number, finalized, finalized_at, winner_participant_id, pot_cents, holdback_cents)
             VALUES (:season_id, :week_number, 1, NOW(), :winner_participant_id, :pot_cents, :holdback_cents)
             ON DUPLICATE KEY UPDATE
                finalized = 1, finalized_at = NOW(), winner_participant_id = VALUES(winner_participant_id),
                pot_cents = VALUES(pot_cents), holdback_cents = VALUES(holdback_cents)'
        );
        $upsert->execute([
            ':season_id' => $seasonId,
            ':week_number' => $weekNumber,
            ':winner_participant_id' => $winnerId,
            ':pot_cents' => $potCents,
            ':holdback_cents' => $holdbackCents,
        ]);

        return self::find($seasonId, $weekNumber);
    }

    public static function find(int $seasonId, int $weekNumber): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM weekly_results WHERE season_id = :season_id AND week_number = :week_number'
        );
        $stmt->execute([':season_id' => $seasonId, ':week_number' => $weekNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Every finalized week for a season, most recent first — for the results page. */
    public static function forSeason(int $seasonId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT wr.*, part.first_name, part.last_name
             FROM weekly_results wr
             LEFT JOIN participants part ON part.id = wr.winner_participant_id
             WHERE wr.season_id = :season_id AND wr.finalized = 1
             ORDER BY wr.week_number DESC"
        );
        $stmt->execute([':season_id' => $seasonId]);
        return $stmt->fetchAll();
    }

    /** Running season holdback total across every finalized week — the season-end payout pool. */
    public static function seasonHoldbackCents(int $seasonId): int
    {
        $stmt = Database::connect()->prepare(
            'SELECT COALESCE(SUM(holdback_cents), 0) AS total FROM weekly_results
             WHERE season_id = :season_id AND finalized = 1'
        );
        $stmt->execute([':season_id' => $seasonId]);
        return (int) $stmt->fetch()['total'];
    }
}
