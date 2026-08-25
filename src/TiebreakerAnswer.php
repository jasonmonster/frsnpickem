<?php

namespace Pickem;

class TiebreakerAnswer
{
    public static function forParticipantWeek(int $participantId, int $weekNumber): ?int
    {
        $stmt = Database::connect()->prepare(
            'SELECT guess_total FROM tiebreaker_answers WHERE participant_id = :participant_id AND week_number = :week_number'
        );
        $stmt->execute([':participant_id' => $participantId, ':week_number' => $weekNumber]);
        $row = $stmt->fetch();
        return $row ? (int) $row['guess_total'] : null;
    }

    public static function submit(int $participantId, int $weekNumber, int $guessTotal): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO tiebreaker_answers (participant_id, week_number, guess_total)
             VALUES (:participant_id, :week_number, :guess_total)
             ON DUPLICATE KEY UPDATE guess_total = VALUES(guess_total)'
        );
        $stmt->execute([
            ':participant_id' => $participantId,
            ':week_number' => $weekNumber,
            ':guess_total' => $guessTotal,
        ]);
    }
}
