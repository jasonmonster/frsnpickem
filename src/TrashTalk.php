<?php

namespace Pickem;

class TrashTalk
{
    public const MAX_LENGTH = 500;

    /**
     * Newest first — the wall reads top-down like a feed. Each row carries
     * 'score' (sum of all votes) and 'my_vote' (1/-1/null — this viewer's
     * own vote, for highlighting the active arrow), the way Reddit does.
     */
    public static function forSeason(int $seasonId, ?int $viewerParticipantId = null, int $limit = 200): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT tt.*, part.first_name, part.last_name, part.username, part.photo_path, part.favorite_nfl_team_id,
                COALESCE(SUM(v.value), 0) AS score,
                MAX(CASE WHEN v.participant_id = :viewer_id THEN v.value ELSE NULL END) AS my_vote
             FROM trash_talk tt
             JOIN participants part ON part.id = tt.participant_id
             LEFT JOIN trash_talk_votes v ON v.trash_talk_id = tt.id
             WHERE tt.season_id = :season_id
             GROUP BY tt.id
             ORDER BY tt.created_at DESC, tt.id DESC
             LIMIT ' . max(1, (int) $limit)
        );
        $stmt->execute([':season_id' => $seasonId, ':viewer_id' => $viewerParticipantId ?? 0]);
        return $stmt->fetchAll();
    }

    /**
     * Cast, change, or clear a vote — the click handler for the up/down
     * arrows. $direction is 1 (up) or -1 (down); clicking the arrow that
     * matches your existing vote clears it, same as Reddit's toggle.
     */
    public static function toggleVote(int $trashTalkId, int $participantId, int $direction): void
    {
        $stmt = Database::connect()->prepare(
            'SELECT value FROM trash_talk_votes WHERE trash_talk_id = :trash_talk_id AND participant_id = :participant_id'
        );
        $stmt->execute([':trash_talk_id' => $trashTalkId, ':participant_id' => $participantId]);
        $existing = $stmt->fetch();
        $current = $existing ? (int) $existing['value'] : 0;
        $new = $current === $direction ? 0 : $direction;

        if ($new === 0) {
            $del = Database::connect()->prepare(
                'DELETE FROM trash_talk_votes WHERE trash_talk_id = :trash_talk_id AND participant_id = :participant_id'
            );
            $del->execute([':trash_talk_id' => $trashTalkId, ':participant_id' => $participantId]);
            return;
        }

        $upsert = Database::connect()->prepare(
            'INSERT INTO trash_talk_votes (trash_talk_id, participant_id, value)
             VALUES (:trash_talk_id, :participant_id, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $upsert->execute([
            ':trash_talk_id' => $trashTalkId,
            ':participant_id' => $participantId,
            ':value' => $new,
        ]);
    }

    /**
     * @throws \InvalidArgumentException on an empty or too-long post — the
     *     caller should show this back to the user.
     */
    public static function post(int $seasonId, int $participantId, ?int $weekNumber, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new \InvalidArgumentException('Say something first.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Keep it under ' . self::MAX_LENGTH . ' characters.');
        }

        $stmt = Database::connect()->prepare(
            'INSERT INTO trash_talk (season_id, participant_id, week_number, body)
             VALUES (:season_id, :participant_id, :week_number, :body)'
        );
        $stmt->execute([
            ':season_id' => $seasonId,
            ':participant_id' => $participantId,
            ':week_number' => $weekNumber,
            ':body' => $body,
        ]);

        $id = (int) Database::connect()->lastInsertId();
        $stmt = Database::connect()->prepare(
            'SELECT tt.*, part.first_name, part.last_name, part.username, part.photo_path, part.favorite_nfl_team_id
             FROM trash_talk tt JOIN participants part ON part.id = tt.participant_id
             WHERE tt.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
}
