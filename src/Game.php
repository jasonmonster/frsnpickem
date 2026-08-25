<?php

namespace Pickem;

class Game
{
    /**
     * Pull a week's games from ESPN and upsert them.
     *
     * @return int number of games upserted
     * @throws \RuntimeException if the ESPN fetch fails — see Espn::fetchWeek()
     * @deprecated ESPN's scoreboard sits behind Akamai bot-detection that
     *     fingerprints the TLS handshake itself — confirmed unfixable from
     *     PHP (see Espn.php). Kept only in case it's ever useful again from
     *     a network Akamai doesn't flag; syncWeekFromSportsBlaze() is what
     *     the admin sync button actually calls now (syncWeekFromApiSports()
     *     is also dormant — its free tier turned out to be locked to
     *     2022-2024 seasons, no current-season access).
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

        return self::assignTiersAndUpsert($seasonId, $weekNumber, $parsed);
    }

    /**
     * Pull a week's games from API-Sports (api-sports.io) and upsert them.
     *
     * The season endpoint returns every game for the year — preseason,
     * regular season, and playoffs together — so week-scoping and the
     * regular-season filter both happen here, client-side.
     *
     * @return int number of games upserted
     * @throws \RuntimeException if the fetch fails, or if any team name in
     *     the response can't be matched against nfl_teams — see
     *     resolveTeamIdByName().
     */
    public static function syncWeekFromApiSports(int $seasonId, int $weekNumber, int $year): int
    {
        $games = ApiSports::fetchSeason($year);
        return self::syncFromApiSportsGames($seasonId, $weekNumber, $games);
    }

    /**
     * The parsing/upsert logic, split out from syncWeekFromApiSports() so it
     * can be tested against a saved response without a network call.
     *
     * @return int number of games upserted
     */
    public static function syncFromApiSportsGames(int $seasonId, int $weekNumber, array $games): int
    {
        $teamsByName = [];
        foreach (NflTeam::all() as $team) {
            $teamsByName[$team['name']] = $team;
        }

        $parsed = [];
        $unmatched = [];
        foreach ($games as $g) {
            $stage = $g['game']['stage'] ?? '';
            $weekLabel = $g['game']['week'] ?? '';
            if ($stage !== 'Regular Season' || !preg_match('/(\d+)/', $weekLabel, $m) || (int) $m[1] !== $weekNumber) {
                continue;
            }

            $gameId = $g['game']['id'] ?? null;
            $timestamp = $g['game']['date']['timestamp'] ?? null;
            $homeName = $g['teams']['home']['name'] ?? null;
            $awayName = $g['teams']['away']['name'] ?? null;
            if ($gameId === null || $timestamp === null || $homeName === null || $awayName === null) {
                continue;
            }

            $homeTeam = $teamsByName[$homeName] ?? null;
            $awayTeam = $teamsByName[$awayName] ?? null;
            if ($homeTeam === null) {
                $unmatched[] = $homeName;
            }
            if ($awayTeam === null) {
                $unmatched[] = $awayName;
            }
            if ($homeTeam === null || $awayTeam === null) {
                continue;
            }

            $parsed[] = [
                'espn_event_id' => 'as_' . $gameId,
                'home_team_id' => (int) $homeTeam['espn_id'],
                'away_team_id' => (int) $awayTeam['espn_id'],
                'home_score' => is_numeric($g['scores']['home']['total'] ?? null) ? (int) $g['scores']['home']['total'] : null,
                'away_score' => is_numeric($g['scores']['away']['total'] ?? null) ? (int) $g['scores']['away']['total'] : null,
                'kickoff_at' => gmdate('Y-m-d H:i:s', (int) $timestamp),
                'status' => self::mapApiSportsStatus($g['game']['status']['short'] ?? ''),
            ];
        }

        if (!empty($unmatched)) {
            $names = implode(', ', array_unique($unmatched));
            throw new \RuntimeException(
                "Couldn't match these team names against nfl_teams — check for a naming mismatch: $names"
            );
        }

        return self::assignTiersAndUpsert($seasonId, $weekNumber, $parsed);
    }

    /**
     * Pull a week's games from SportsBlaze's cache endpoint and upsert them.
     * This is what the admin "sync week" button actually calls for now —
     * see SportsBlaze.php for why (API-Sports' free tier turned out to be
     * locked to 2022-2024 seasons, no current-season access).
     *
     * The season endpoint returns every game for the year — preseason,
     * regular season, and playoffs together — so week-scoping and the
     * regular-season filter both happen here, client-side.
     *
     * @return int number of games upserted
     * @throws \RuntimeException if the fetch fails, or if any team name in
     *     the response can't be matched against nfl_teams.
     */
    public static function syncWeekFromSportsBlaze(int $seasonId, int $weekNumber, int $year): int
    {
        $events = SportsBlaze::fetchSeason($year);
        return self::syncFromSportsBlazeEvents($seasonId, $weekNumber, $events);
    }

    /**
     * The parsing/upsert logic, split out from syncWeekFromSportsBlaze() so
     * it can be tested against a saved response without a network call.
     *
     * Team matching tries abbreviation first (fast, matches 31 of 32 teams),
     * then falls back to exact name match — SportsBlaze's abbreviation for
     * Washington is "WAS", our manifest has "WSH", and the two schemes
     * aren't guaranteed to line up elsewhere either, so name is the safety
     * net rather than the primary key.
     *
     * @return int number of games upserted
     */
    public static function syncFromSportsBlazeEvents(int $seasonId, int $weekNumber, array $events): int
    {
        $teamsByAbbr = [];
        $teamsByName = [];
        foreach (NflTeam::all() as $team) {
            $teamsByAbbr[strtoupper($team['abbr'])] = $team;
            $teamsByName[$team['name']] = $team;
        }
        $resolve = function (array $teamRef) use ($teamsByAbbr, $teamsByName): ?array {
            $abbr = strtoupper($teamRef['abbreviation'] ?? '');
            if (isset($teamsByAbbr[$abbr])) {
                return $teamsByAbbr[$abbr];
            }
            $name = $teamRef['name'] ?? '';
            return $teamsByName[$name] ?? null;
        };

        $parsed = [];
        $unmatched = [];
        foreach ($events as $event) {
            $season = $event['season'] ?? [];
            if (($season['type'] ?? '') !== 'Regular Season' || (int) ($season['week'] ?? -1) !== $weekNumber) {
                continue;
            }

            $eventId = $event['id'] ?? null;
            $date = $event['date'] ?? null;
            $homeRef = $event['teams']['home'] ?? null;
            $awayRef = $event['teams']['away'] ?? null;
            if ($eventId === null || $date === null || $homeRef === null || $awayRef === null) {
                continue;
            }

            $homeTeam = $resolve($homeRef);
            $awayTeam = $resolve($awayRef);
            if ($homeTeam === null) {
                $unmatched[] = $homeRef['name'] ?? ($homeRef['abbreviation'] ?? '?');
            }
            if ($awayTeam === null) {
                $unmatched[] = $awayRef['name'] ?? ($awayRef['abbreviation'] ?? '?');
            }
            if ($homeTeam === null || $awayTeam === null) {
                continue;
            }

            $parsed[] = [
                'espn_event_id' => 'sb_' . $eventId,
                'home_team_id' => (int) $homeTeam['espn_id'],
                'away_team_id' => (int) $awayTeam['espn_id'],
                'home_score' => is_numeric($event['scores']['total']['home'] ?? null) ? (int) $event['scores']['total']['home'] : null,
                'away_score' => is_numeric($event['scores']['total']['away'] ?? null) ? (int) $event['scores']['total']['away'] : null,
                'kickoff_at' => self::toMysqlDatetime($date),
                'status' => self::mapSportsBlazeStatus($event['status'] ?? ''),
            ];
        }

        if (!empty($unmatched)) {
            $names = implode(', ', array_unique($unmatched));
            throw new \RuntimeException(
                "Couldn't match these team names against nfl_teams — check for a naming mismatch: $names"
            );
        }

        return self::assignTiersAndUpsert($seasonId, $weekNumber, $parsed);
    }

    /**
     * Shared by both sources: assigns the two-tier lock (by kickoff RANK,
     * not day-of-week — the single earliest-kickoff game of the week is the
     * "thursday" tier, locking at its own kickoff; everything else is the
     * "weekend" tier, locking together at the second-earliest kickoff). The
     * actual NFL schedule doesn't always put the early game on an actual
     * Thursday — 2026 Week 1 opens on a Wednesday — so this has to be
     * rank-based to hold up.
     *
     * The tiebreaker game (MNF, or the last game of the week when there's no
     * Monday game) is likewise just "whichever game kicks off latest," which
     * satisfies both cases in the build plan with one rule. Then upserts.
     *
     * @return int number of games upserted
     */
    private static function assignTiersAndUpsert(int $seasonId, int $weekNumber, array $parsed): int
    {
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

    /**
     * API-Sports' status.short codes: NS (not started), Q1-Q4/HT/OT (live),
     * FT/AOT (final), CANC/PST (cancelled/postponed — no separate status in
     * our schema for these yet, so they fall back to 'scheduled' for now;
     * fine for the pilot, worth revisiting if a game actually gets CANC'd).
     */
    private static function mapApiSportsStatus(string $short): string
    {
        if ($short === 'FT' || $short === 'AOT') {
            return 'final';
        }
        if (in_array($short, ['Q1', 'Q2', 'Q3', 'Q4', 'HT', 'OT'], true)) {
            return 'in_progress';
        }
        return 'scheduled';
    }

    /**
     * SportsBlaze's status is a plain string ("Scheduled", "Final", etc. —
     * no published enum of every possible value, so this only special-cases
     * the two seen so far and treats anything else as scheduled).
     */
    private static function mapSportsBlazeStatus(string $status): string
    {
        return match ($status) {
            'Final' => 'final',
            'In Progress', 'Live' => 'in_progress',
            default => 'scheduled',
        };
    }
}
