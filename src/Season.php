<?php

namespace Pickem;

class Season
{
    /** The one season new signups join — the most recent active season. */
    public static function active(): ?array
    {
        $stmt = Database::connect()->query(
            "SELECT * FROM seasons WHERE status = 'active' ORDER BY year DESC LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM seasons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * "Current" week isn't tracked as its own field — it's derived from
     * whatever's actually been synced and graded, so it advances on its own
     * as weeks finish rather than needing an admin to bump a counter. The
     * earliest synced week that isn't fully final yet is current; once every
     * synced week is done, it's the week after the last one synced.
     */
    public static function currentWeek(int $seasonId): int
    {
        $pdo = Database::connect();

        $stmt = $pdo->prepare('SELECT MAX(week_number) AS max_week FROM games WHERE season_id = :season_id');
        $stmt->execute([':season_id' => $seasonId]);
        $maxWeek = $stmt->fetch()['max_week'];
        if ($maxWeek === null) {
            return 1;
        }

        $stmt = $pdo->prepare(
            "SELECT week_number FROM games WHERE season_id = :season_id AND status != 'final'
             ORDER BY week_number ASC LIMIT 1"
        );
        $stmt->execute([':season_id' => $seasonId]);
        $row = $stmt->fetch();
        return $row ? (int) $row['week_number'] : ((int) $maxWeek + 1);
    }
}
