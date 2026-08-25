<?php

namespace Pickem;

class Leaderboard
{
    /**
     * Season-long standings for every active participant — unofficial and
     * live, i.e. not gated by weekly_results.finalized (that's the
     * payment/payout reconciliation step, a later phase). Ranked by picks
     * correct, then fewer incorrect, then name.
     *
     * @return array<int, array{participant: array, correct: int, incorrect: int, graded: int, pct: float}>
     */
    public static function standings(int $seasonId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT p.*,
                SUM(CASE WHEN pk.is_correct = 1 THEN 1 ELSE 0 END) AS correct,
                SUM(CASE WHEN pk.is_correct = 0 THEN 1 ELSE 0 END) AS incorrect
             FROM participants p
             LEFT JOIN picks pk ON pk.participant_id = p.id
             WHERE p.season_id = :season_id AND p.is_active = 1
             GROUP BY p.id
             ORDER BY correct DESC, incorrect ASC, p.first_name ASC, p.last_name ASC"
        );
        $stmt->execute([':season_id' => $seasonId]);

        $standings = [];
        foreach ($stmt->fetchAll() as $row) {
            $correct = (int) $row['correct'];
            $incorrect = (int) $row['incorrect'];
            $graded = $correct + $incorrect;
            $standings[] = [
                'participant' => $row,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'graded' => $graded,
                'pct' => $graded > 0 ? round(($correct / $graded) * 100) : 0.0,
            ];
        }
        return $standings;
    }
}
