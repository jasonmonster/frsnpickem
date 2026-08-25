<?php

namespace Pickem;

/**
 * The favorite-college-team reference data — same shape and purpose as
 * NflTeam, seeded from the same FRSN team-logos manifest (the `ncaa` league
 * slice of it, 362 D1 schools). Session 10 replaced the old free-text
 * favorite_college_team field with this dropdown, same pattern as the NFL
 * team picker.
 */
class CollegeTeam
{
    /** All 362 schools, alphabetical by name — for the favorite-college dropdown. */
    public static function all(): array
    {
        return Database::connect()
            ->query('SELECT * FROM college_teams ORDER BY name')
            ->fetchAll();
    }

    /** All schools keyed by ESPN id — for looking up a participant's favorite without a query per row. */
    public static function allById(): array
    {
        $byId = [];
        foreach (self::all() as $team) {
            $byId[(int) $team['espn_id']] = $team;
        }
        return $byId;
    }

    public static function find(int $espnId): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM college_teams WHERE espn_id = :id');
        $stmt->execute([':id' => $espnId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function logoStd(array $team): string
    {
        return $team['logo_std'];
    }
}
