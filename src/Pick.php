<?php

namespace Pickem;

class Pick
{
    /** @return array<int,int> game_id => chosen_team_id */
    public static function forParticipantWeek(int $participantId, array $gameIds): array
    {
        if (empty($gameIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
        $stmt = Database::connect()->prepare(
            "SELECT game_id, chosen_team_id FROM picks WHERE participant_id = ? AND game_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$participantId], $gameIds));

        $picks = [];
        foreach ($stmt->fetchAll() as $row) {
            $picks[(int) $row['game_id']] = (int) $row['chosen_team_id'];
        }
        return $picks;
    }

    public static function submit(int $participantId, int $gameId, int $chosenTeamId): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO picks (participant_id, game_id, chosen_team_id)
             VALUES (:participant_id, :game_id, :chosen_team_id)
             ON DUPLICATE KEY UPDATE chosen_team_id = VALUES(chosen_team_id)'
        );
        $stmt->execute([
            ':participant_id' => $participantId,
            ':game_id' => $gameId,
            ':chosen_team_id' => $chosenTeamId,
        ]);
    }
}
