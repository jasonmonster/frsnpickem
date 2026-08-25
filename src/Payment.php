<?php

namespace Pickem;

/**
 * The weekly payment log — a reconciliation record, not a gate. Nothing
 * about picking or grading checks this; it only feeds WeeklyResult::finalize()
 * when an admin decides a week is settled.
 */
class Payment
{
    /**
     * Every active participant in the season, each carrying whether they're
     * marked paid for the given week (no payments row yet = not marked paid).
     *
     * @return array<int, array> participant row + 'paid' (bool), 'marked_by', 'marked_at'
     */
    public static function statusForWeek(int $seasonId, int $weekNumber): array
    {
        $stmt = Database::connect()->prepare(
            "SELECT part.*, pay.paid AS pay_paid, pay.marked_by, pay.marked_at
             FROM participants part
             LEFT JOIN payments pay ON pay.participant_id = part.id AND pay.week_number = :week_number
             WHERE part.season_id = :season_id AND part.is_active = 1
             ORDER BY part.first_name, part.last_name"
        );
        $stmt->execute([':season_id' => $seasonId, ':week_number' => $weekNumber]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['paid'] = !empty($row['pay_paid']);
            $rows[] = $row;
        }
        return $rows;
    }

    public static function setPaid(int $participantId, int $weekNumber, bool $paid, string $markedBy): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO payments (participant_id, week_number, paid, marked_by, marked_at)
             VALUES (:participant_id, :week_number, :paid, :marked_by, NOW())
             ON DUPLICATE KEY UPDATE paid = VALUES(paid), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)'
        );
        $stmt->execute([
            ':participant_id' => $participantId,
            ':week_number' => $weekNumber,
            ':paid' => $paid ? 1 : 0,
            ':marked_by' => $markedBy,
        ]);
    }

    /** Count of participants marked paid for a season/week — what a pot/holdback calc is based on. */
    public static function paidCount(int $seasonId, int $weekNumber): int
    {
        $stmt = Database::connect()->prepare(
            "SELECT COUNT(*) AS n
             FROM payments pay
             JOIN participants part ON part.id = pay.participant_id
             WHERE part.season_id = :season_id AND pay.week_number = :week_number AND pay.paid = 1"
        );
        $stmt->execute([':season_id' => $seasonId, ':week_number' => $weekNumber]);
        return (int) $stmt->fetch()['n'];
    }
}
