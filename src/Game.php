<?php

namespace Pickem;

class Game
{
    /**
     * Pull a week's games from ESPN and upsert them.
     *
     * @return int number of games upserted
     * @throws \RuntimeException if the ESPN fetch fails — see Espn::fetchWeek()
     */
    public static function syncWeek(int $seasonId, int $weekNumber, int $year, int $seasonType = 2): int
    {
        $events = Espn::fetchWeek($year, $weekNumber, $seasonType);
        return self::syncFromEvents($seasonId, $weekNumber, $events);
    }

    /**
     * The parsing/upsert logic, split out from syncWeek() so it can be tested
     * against a saved ESPN response without a network call.
     *
     * Lock tiers are assigned by kickoff RANK, not day-of-week: the single
     * earliest-kickoff game of the week is the "thursday" tier (locks at its
     * own kickoff), everything else is the "weekend" tier (locks together at
     * the second-earliest kickoff). The actual NFL schedule doesn't always
     * put the early game on an actual Thursday — 2026 Week 1 opens on a
     * Wednesday — so this has to be rank-based to hold up.
     *
     * The tiebreaker game (MNF, or the last game of the week when there's no
     * Monday game) is likewise just "whichever game kicks off latest," which
     * satisfies both cases in the build plan with one rule.
     *
     * @return int number of games upserted
     */
    public static function syncFromEvents(int $seasonId, int $weekNumber, array $events): int
    {
        $parsed = [];
        foreach ($events as $event) {
            $competition = $event['competitions'][0] ?? null;
            if ($competition === null || empty($event['id']) || empty($event['date'])) {
                continue;
            }

            $home = null;
            $away = null;
            foreach ($competition['competitors'] ?? [] as $competitor) {
                if (($competitor['homeAway'] ?? '') === 'home') {
                    $home = $competitor;
                } elseif (($competitor['homeAway'] ?? '') === 'away') {
                    $away = $competitor;
                }
            }
            if ($home === null || $away === null || empty($home['team']['id']) || empty($away['team']['id'])) {
                continue;
            }

            $parsed[] = [
                'espn_event_id' => (string) $event['id'],
                'home_team_id' => (int) $home['team']['id'],
                'away_team_id' => (int) $away['team']['id'],
                'home_score' => is_numeric($home['score'] ?? null) ? (int) $home['score'] : null,
                'away_score' => is_numeric($away['score'] ?? null) ? (int) $away['score'] : null,
                'kickoff_at' => self::toMysqlDatetime($event['date']),
                'status' => self::mapStatus($event['status']['type'] ?? []),
            ];
        }

        if (empty($parsed)) {
            return 0;
        }

        usort($parsed, fn($a, $b) => $a['kickoff_at'] <=> $b['kickoff_at']);
        $lastIndex = count($parsed) - 1;
        foreach ($parsed as $i => &$game) {
            $game['lock_group'] = $i === 0 ? 'thursday' : 'weekend';
            $game['is_tiebreaker'] = $i === $lastIndex ? 1 : 0;
        }
        unset($game);

        $stmt = Database::connect()->prepare(
            'INSERT INTO games
                (season_id, week_number, espn_event_id, home_team_id, away_team_id, kickoff_at, status, home_score, away_score, is_tiebreaker, lock_group)
             VALUES (:season_id, :week_number, :espn_event_id, :home_team_id, :away_team_id, :kickoff_at, :status, :home_score, :away_score, :is_tiebreaker, :lock_group)
             ON DUPLICATE KEY UPDATE
                home_team_id = VALUES(home_team_id), away_team_id = VALUES(away_team_id),
                kickoff_at = VALUES(kickoff_at), status = VALUES(status),
                home_score = VALUES(home_score), away_score = VALUES(away_score),
                is_tiebreaker = VALUES(is_tiebreaker), lock_group = VALUES(lock_group)'
        );

        foreach ($parsed as $game) {
            $stmt->execute([
                ':season_id' => $seasonId,
                ':week_number' => $weekNumber,
                ':espn_event_id' => $game['espn_event_id'],
                ':home_team_id' => $game['home_team_id'],
                ':away_team_id' => $game['away_team_id'],
                ':kickoff_at' => $game['kickoff_at'],
                ':status' => $game['status'],
                ':home_score' => $game['home_score'],
                ':away_score' => $game['away_score'],
                ':is_tiebreaker' => $game['is_tiebreaker'],
                ':lock_group' => $game['lock_group'],
            ]);
        }

        return count($parsed);
    }

    public static function forWeek(int $seasonId, int $weekNumber): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM games WHERE season_id = :season_id AND week_number = :week_number ORDER BY kickoff_at ASC'
        );
        $stmt->execute([':season_id' => $seasonId, ':week_number' => $weekNumber]);
        return $stmt->fetchAll();
    }

    /**
     * The two lock timestamps in effect for a set of games from the same
     * week — every game in a tier shares its tier's single lock time.
     *
     * @return array{thursday: ?\DateTimeImmutable, weekend: ?\DateTimeImmutable}
     */
    public static function lockTimes(array $games): array
    {
        $times = ['thursday' => null, 'weekend' => null];
        foreach ($games as $game) {
            $kickoff = new \DateTimeImmutable($game['kickoff_at'], new \DateTimeZone('UTC'));
            $tier = $game['lock_group'];
            if (!isset($times[$tier]) || $times[$tier] === null || $kickoff < $times[$tier]) {
                $times[$tier] = $kickoff;
            }
        }
        return $times;
    }

    public static function isLocked(array $game, array $lockTimes, \DateTimeImmutable $now): bool
    {
        $lockAt = $lockTimes[$game['lock_group']] ?? null;
        return $lockAt !== null && $now >= $lockAt;
    }

    public static function formatKickoff(string $mysqlDatetimeUtc): string
    {
        $dt = new \DateTimeImmutable($mysqlDatetimeUtc, new \DateTimeZone('UTC'));
        $dt = $dt->setTimezone(new \DateTimeZone('America/Denver'));
        return $dt->format('D n/j g:i A') . ' MT';
    }

    private static function toMysqlDatetime(string $espnDate): string
    {
        $dt = new \DateTimeImmutable($espnDate, new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }

    private static function mapStatus(array $statusType): string
    {
        return match ($statusType['state'] ?? 'pre') {
            'in' => 'in_progress',
            'post' => 'final',
            default => 'scheduled',
        };
    }
}
