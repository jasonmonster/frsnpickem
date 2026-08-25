<?php

namespace Pickem;

/**
 * Profile badges, per the Section 15 plan. Deliberately NOT a generic
 * badges/participant_badges schema (that's flagged in the plan as the move
 * once there's a real second wave of badge ideas) — both badge types here
 * derive live from data that already exists, so a new table would just be
 * a cache with nothing to invalidate it:
 *   - lodge badge: read straight off participants.lodge_affiliation
 *   - weekly-winner badge: read straight off weekly_results.winner_participant_id
 *
 * The icons are plain emoji standing in for real artwork — nothing custom
 * has been sourced for badges the way team logos were, so this is a
 * placeholder pending a real design pass.
 */
class Badge
{
    private const LODGE_LABELS = [
        'den_17' => ['emoji' => '🦌', 'label' => 'Den 17'],
        'other' => ['emoji' => '🦌', 'label' => 'Lodge member'],
    ];

    /** @return array{emoji: string, label: string}|null */
    public static function lodgeBadge(array $participant): ?array
    {
        return self::LODGE_LABELS[$participant['lodge_affiliation'] ?? ''] ?? null;
    }

    /** Every week this participant has been recorded as the official winner of, ascending. */
    public static function weeklyWinnerWeeks(int $seasonId, int $participantId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT week_number FROM weekly_results
             WHERE season_id = :season_id AND finalized = 1 AND winner_participant_id = :participant_id
             ORDER BY week_number ASC'
        );
        $stmt->execute([':season_id' => $seasonId, ':participant_id' => $participantId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'week_number'));
    }

    /**
     * Every badge chip a participant has earned, in display order: lodge
     * badge first (if set), then one "Week N Winner" chip per week they've
     * been recorded as the official winner of — per the plan, someone who
     * wins weeks 3 and 7 shows both, not a combined count.
     *
     * @return array<int, array{emoji: string, label: string}>
     */
    public static function chipsFor(array $participant, int $seasonId): array
    {
        $chips = [];
        $lodge = self::lodgeBadge($participant);
        if ($lodge !== null) {
            $chips[] = $lodge;
        }
        foreach (self::weeklyWinnerWeeks($seasonId, (int) $participant['id']) as $week) {
            $chips[] = ['emoji' => '🥇', 'label' => "Week $week Winner"];
        }
        return $chips;
    }

    /**
     * The same thing, batched for every participant in a season at once —
     * for rendering a whole leaderboard without a query per row.
     *
     * @return array<int, array<int>> participant_id => [week_number, ...]
     */
    public static function weeklyWinnerWeeksBySeason(int $seasonId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT winner_participant_id, week_number FROM weekly_results
             WHERE season_id = :season_id AND finalized = 1 AND winner_participant_id IS NOT NULL
             ORDER BY week_number ASC'
        );
        $stmt->execute([':season_id' => $seasonId]);

        $byParticipant = [];
        foreach ($stmt->fetchAll() as $row) {
            $byParticipant[(int) $row['winner_participant_id']][] = (int) $row['week_number'];
        }
        return $byParticipant;
    }
}
