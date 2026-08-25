<?php

namespace Pickem;

class NflTeam
{
    /** All 32 teams, alphabetical by name — for the favorite-team dropdown. */
    public static function all(): array
    {
        return Database::connect()
            ->query('SELECT * FROM nfl_teams ORDER BY name')
            ->fetchAll();
    }

    /** All 32 teams keyed by ESPN id — for looking up a game's home/away team without a query per row. */
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
        $stmt = Database::connect()->prepare('SELECT * FROM nfl_teams WHERE espn_id = :id');
        $stmt->execute([':id' => $espnId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The logo filename that actually reads on FRSN navy, per the manifest's on_navy flag. */
    public static function logoOnNavy(array $team): string
    {
        return match ($team['on_navy']) {
            'dark' => $team['logo_dark'],
            'white' => $team['logo_white'],
            default => $team['logo_std'],
        };
    }

    public static function logoStd(array $team): string
    {
        return $team['logo_std'];
    }
}
