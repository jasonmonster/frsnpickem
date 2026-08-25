<?php

namespace Pickem;

/**
 * Profile badges, per the Section 15 plan. Deliberately NOT a generic
 * badges/participant_badges schema — every badge here derives live from data
 * that already exists elsewhere, so there's nothing to keep in sync:
 *   - lodge badge: participants.lodge_affiliation
 *   - weekly winner: weekly_results.winner_participant_id
 *   - perfect week: picks.is_correct against every final game that week
 *   - iron man: a pick present for every game that's already locked
 *   - tiebreaker ace: weekly_results.tiebreaker_participant_id — only set
 *     when a week's official winner was actually decided by the tiebreaker
 *     guess (a real tie among paid participants), not just the most correct
 *     picks outright. See WeeklyResult::finalize().
 *   - trash talk champion / poop award: whoever currently holds the
 *     highest / lowest net-voted single post on the wall — one holder at a
 *     time each, not a running history.
 *
 * Perfect week and iron man are deliberately NOT gated on a week being
 * officially finalized (paid) — same philosophy as the live unofficial
 * leaderboard: personal picking achievements, not money results. Weekly
 * winner and tiebreaker ace are, since they only exist once a week's been
 * finalized in the first place.
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
        return self::weeksForParticipant($seasonId, $participantId, 'winner_participant_id');
    }

    /**
     * Every week this participant's official win was actually decided by
     * the tiebreaker guess rather than the most correct picks outright.
     */
    public static function tiebreakerAceWeeks(int $seasonId, int $participantId): array
    {
        return self::weeksForParticipant($seasonId, $participantId, 'tiebreaker_participant_id');
    }

    private static function weeksForParticipant(int $seasonId, int $participantId, string $column): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT week_number FROM weekly_results
             WHERE season_id = :season_id AND finalized = 1 AND $column = :participant_id
             ORDER BY week_number ASC"
        );
        $stmt->execute([':season_id' => $seasonId, ':participant_id' => $participantId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'week_number'));
    }

    /** Every week this participant went 100% on every graded (final) game that week. */
    public static function perfectWeeks(int $seasonId, int $participantId): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT g.week_number, COUNT(g.id) AS game_count,
                SUM(CASE WHEN pk.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
             FROM games g
             LEFT JOIN picks pk ON pk.game_id = g.id AND pk.participant_id = :participant_id
             WHERE g.season_id = :season_id AND g.status = 'final'
             GROUP BY g.week_number
             HAVING correct_count = game_count
             ORDER BY g.week_number ASC"
        );
        $stmt->execute([':participant_id' => $participantId, ':season_id' => $seasonId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'week_number'));
    }

    /**
     * Whether this participant has a submitted pick for every game that's
     * already locked this season — a running "never missed a pick" badge
     * rather than a per-week one, so it just stops showing the moment they
     * miss a game.
     */
    public static function isIronMan(int $seasonId, int $participantId): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT COUNT(g.id) AS locked_games,
                SUM(CASE WHEN pk.id IS NOT NULL THEN 1 ELSE 0 END) AS picked_games
             FROM games g
             LEFT JOIN picks pk ON pk.game_id = g.id AND pk.participant_id = :participant_id
             WHERE g.season_id = :season_id AND g.kickoff_at <= NOW()'
        );
        $stmt->execute([':participant_id' => $participantId, ':season_id' => $seasonId]);
        $row = $stmt->fetch();
        $locked = (int) $row['locked_games'];
        return $locked > 0 && $locked === (int) $row['picked_games'];
    }

    /**
     * The participant whose single trash-talk post currently has the
     * highest net score — null if nobody's ever earned a positive score.
     */
    public static function trashTalkChampion(int $seasonId): ?int
    {
        return self::trashTalkExtreme($seasonId, 'DESC');
    }

    /** The reverse — whoever's single post currently has the lowest net score. Null if nobody's ever gone negative. */
    public static function trashTalkPoop(int $seasonId): ?int
    {
        return self::trashTalkExtreme($seasonId, 'ASC');
    }

    private static function trashTalkExtreme(int $seasonId, string $direction): ?int
    {
        $stmt = Database::connect()->prepare(
            "SELECT tt.participant_id, COALESCE(SUM(v.value), 0) AS score
             FROM trash_talk tt
             LEFT JOIN trash_talk_votes v ON v.trash_talk_id = tt.id
             WHERE tt.season_id = :season_id
             GROUP BY tt.id
             ORDER BY score $direction, tt.created_at ASC, tt.id ASC
             LIMIT 1"
        );
        $stmt->execute([':season_id' => $seasonId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $score = (int) $row['score'];
        if ($direction === 'DESC' && $score <= 0) {
            return null;
        }
        if ($direction === 'ASC' && $score >= 0) {
            return null;
        }
        return (int) $row['participant_id'];
    }

    /**
     * Every badge chip a participant has earned, in display order: lodge,
     * weekly wins, perfect weeks, tiebreaker aces (each stacking, one chip
     * per week earned), then iron man, trash talk champion, and poop award
     * (each a single chip, shown at most once).
     *
     * @return array<int, array{emoji: string, label: string}>
     */
    public static function chipsFor(array $participant, int $seasonId): array
    {
        $participantId = (int) $participant['id'];
        $chips = [];

        $lodge = self::lodgeBadge($participant);
        if ($lodge !== null) {
            $chips[] = $lodge;
        }
        foreach (self::weeklyWinnerWeeks($seasonId, $participantId) as $week) {
            $chips[] = ['emoji' => '🥇', 'label' => "Week $week Winner"];
        }
        foreach (self::perfectWeeks($seasonId, $participantId) as $week) {
            $chips[] = ['emoji' => '💯', 'label' => "Week $week Perfect"];
        }
        foreach (self::tiebreakerAceWeeks($seasonId, $participantId) as $week) {
            $chips[] = ['emoji' => '🎯', 'label' => "Week $week Tiebreaker Ace"];
        }
        if (self::isIronMan($seasonId, $participantId)) {
            $chips[] = ['emoji' => '🛡️', 'label' => 'Iron Man'];
        }
        if (self::trashTalkChampion($seasonId) === $participantId) {
            $chips[] = ['emoji' => '👑', 'label' => 'Trash Talk Champion'];
        }
        if (self::trashTalkPoop($seasonId) === $participantId) {
            $chips[] = ['emoji' => '💩', 'label' => 'Poop Award'];
        }
        return $chips;
    }

    /**
     * Batched weekly-winner lookup for rendering a whole leaderboard
     * without a query per row.
     *
     * @return array<int, array<int>> participant_id => [week_number, ...]
     */
    public static function weeklyWinnerWeeksBySeason(int $seasonId): array
    {
        return self::weeksBySeasonByColumn($seasonId, 'winner_participant_id');
    }

    /** Same shape as weeklyWinnerWeeksBySeason(), for the tiebreaker-ace badge. */
    public static function tiebreakerAceWeeksBySeason(int $seasonId): array
    {
        return self::weeksBySeasonByColumn($seasonId, 'tiebreaker_participant_id');
    }

    private static function weeksBySeasonByColumn(int $seasonId, string $column): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT $column AS participant_id, week_number FROM weekly_results
             WHERE season_id = :season_id AND finalized = 1 AND $column IS NOT NULL
             ORDER BY week_number ASC"
        );
        $stmt->execute([':season_id' => $seasonId]);

        $byParticipant = [];
        foreach ($stmt->fetchAll() as $row) {
            $byParticipant[(int) $row['participant_id']][] = (int) $row['week_number'];
        }
        return $byParticipant;
    }

    /**
     * Batched perfect-week lookup for a whole leaderboard. Done as two
     * queries — total final games per week, then each participant's correct
     * count per week — rather than one participant at a time, since the
     * per-participant LEFT JOIN doesn't collapse cleanly across everyone at
     * once (an INNER JOIN version would silently ignore weeks a participant
     * skipped entirely, and treat a partial week as perfect).
     *
     * @return array<int, array<int>> participant_id => [week_number, ...]
     */
    public static function perfectWeeksBySeason(int $seasonId): array
    {
        $pdo = Database::connect();

        $totalsStmt = $pdo->prepare(
            "SELECT week_number, COUNT(*) AS game_count FROM games
             WHERE season_id = :season_id AND status = 'final'
             GROUP BY week_number"
        );
        $totalsStmt->execute([':season_id' => $seasonId]);
        $totals = [];
        foreach ($totalsStmt->fetchAll() as $row) {
            $totals[(int) $row['week_number']] = (int) $row['game_count'];
        }
        if (empty($totals)) {
            return [];
        }

        $correctStmt = $pdo->prepare(
            "SELECT pk.participant_id, g.week_number,
                SUM(CASE WHEN pk.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
             FROM picks pk
             JOIN games g ON g.id = pk.game_id
             WHERE g.season_id = :season_id AND g.status = 'final'
             GROUP BY pk.participant_id, g.week_number"
        );
        $correctStmt->execute([':season_id' => $seasonId]);

        $byParticipant = [];
        foreach ($correctStmt->fetchAll() as $row) {
            $week = (int) $row['week_number'];
            if (isset($totals[$week]) && (int) $row['correct_count'] === $totals[$week]) {
                $byParticipant[(int) $row['participant_id']][] = $week;
            }
        }
        return $byParticipant;
    }

    /**
     * Every currently-qualifying iron-man participant_id, batched the same
     * two-step way as perfectWeeksBySeason().
     *
     * @return array<int> participant ids
     */
    public static function ironManParticipantIdsBySeason(int $seasonId): array
    {
        $pdo = Database::connect();

        $totalStmt = $pdo->prepare(
            'SELECT COUNT(*) AS n FROM games WHERE season_id = :season_id AND kickoff_at <= NOW()'
        );
        $totalStmt->execute([':season_id' => $seasonId]);
        $totalLocked = (int) $totalStmt->fetch()['n'];
        if ($totalLocked === 0) {
            return [];
        }

        $pickedStmt = $pdo->prepare(
            'SELECT pk.participant_id, COUNT(*) AS n
             FROM picks pk JOIN games g ON g.id = pk.game_id
             WHERE g.season_id = :season_id AND g.kickoff_at <= NOW()
             GROUP BY pk.participant_id'
        );
        $pickedStmt->execute([':season_id' => $seasonId]);

        $ids = [];
        foreach ($pickedStmt->fetchAll() as $row) {
            if ((int) $row['n'] === $totalLocked) {
                $ids[] = (int) $row['participant_id'];
            }
        }
        return $ids;
    }
}
