<?php

namespace Pickem;

class Participant
{
    /** The only three valid answers to "are you an Elk?" — asked at signup, required. */
    private const LODGE_AFFILIATIONS = ['den_17', 'other', 'not_elk'];

    public static function findByUsername(int $seasonId, string $username): ?array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM participants WHERE season_id = :season_id AND username = :username'
        );
        $stmt->execute([':season_id' => $seasonId, ':username' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM participants WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @throws \InvalidArgumentException on a validation failure the caller should show back to the user.
     */
    public static function create(array $data): array
    {
        $username = self::normalizeUsername($data['username'] ?? '');
        $pin = trim($data['pin'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');

        self::validateUsername($username);
        self::validatePin($pin);
        if ($firstName === '' || $lastName === '') {
            throw new \InvalidArgumentException('First and last name are both required.');
        }

        $seasonId = (int) $data['season_id'];
        if (self::findByUsername($seasonId, $username) !== null) {
            throw new \InvalidArgumentException("The username \"$username\" is already taken this season — pick another.");
        }

        $bio = trim($data['bio'] ?? '');
        if (mb_strlen($bio) > 160) {
            $bio = mb_substr($bio, 0, 160);
        }
        $lodgeAffiliation = self::requireLodgeAffiliation((string) ($data['lodge_affiliation'] ?? ''));

        $stmt = Database::connect()->prepare(
            'INSERT INTO participants
                (season_id, username, pin_hash, first_name, last_name, contact_email, favorite_nfl_team_id, favorite_college_team_id, bio, lodge_affiliation)
             VALUES (:season_id, :username, :pin_hash, :first_name, :last_name, :contact_email, :favorite_nfl_team_id, :favorite_college_team_id, :bio, :lodge_affiliation)'
        );
        $stmt->execute([
            ':season_id'                 => $seasonId,
            ':username'                  => $username,
            ':pin_hash'                  => password_hash($pin, PASSWORD_DEFAULT),
            ':first_name'                => $firstName,
            ':last_name'                 => $lastName,
            ':contact_email'             => trim($data['contact_email'] ?? '') ?: null,
            ':favorite_nfl_team_id'      => !empty($data['favorite_nfl_team_id']) ? (int) $data['favorite_nfl_team_id'] : null,
            ':favorite_college_team_id'  => !empty($data['favorite_college_team_id']) ? (int) $data['favorite_college_team_id'] : null,
            ':bio'                       => $bio ?: null,
            ':lodge_affiliation'         => $lodgeAffiliation,
        ]);

        return self::find((int) Database::connect()->lastInsertId());
    }

    public static function updateProfile(int $id, array $data): array
    {
        $bio = trim($data['bio'] ?? '');
        if (mb_strlen($bio) > 160) {
            $bio = mb_substr($bio, 0, 160);
        }
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        if ($firstName === '' || $lastName === '') {
            throw new \InvalidArgumentException('First and last name are both required.');
        }
        $lodgeAffiliation = self::requireLodgeAffiliation((string) ($data['lodge_affiliation'] ?? ''));

        $stmt = Database::connect()->prepare(
            'UPDATE participants SET
                first_name = :first_name,
                last_name = :last_name,
                contact_email = :contact_email,
                favorite_nfl_team_id = :favorite_nfl_team_id,
                favorite_college_team_id = :favorite_college_team_id,
                bio = :bio,
                lodge_affiliation = :lodge_affiliation
             WHERE id = :id'
        );
        $stmt->execute([
            ':first_name'               => $firstName,
            ':last_name'                => $lastName,
            ':contact_email'            => trim($data['contact_email'] ?? '') ?: null,
            ':favorite_nfl_team_id'     => !empty($data['favorite_nfl_team_id']) ? (int) $data['favorite_nfl_team_id'] : null,
            ':favorite_college_team_id' => !empty($data['favorite_college_team_id']) ? (int) $data['favorite_college_team_id'] : null,
            ':bio'                      => $bio ?: null,
            ':lodge_affiliation'        => $lodgeAffiliation,
            ':id'                       => $id,
        ]);

        return self::find($id);
    }

    public static function updatePhoto(int $id, string $relativePath): void
    {
        $stmt = Database::connect()->prepare('UPDATE participants SET photo_path = :photo_path WHERE id = :id');
        $stmt->execute([':photo_path' => $relativePath, ':id' => $id]);
    }

    public static function updatePin(int $id, string $newPin): void
    {
        self::validatePin($newPin);
        $stmt = Database::connect()->prepare('UPDATE participants SET pin_hash = :pin_hash WHERE id = :id');
        $stmt->execute([':pin_hash' => password_hash($newPin, PASSWORD_DEFAULT), ':id' => $id]);
    }

    public static function verifyPin(array $participant, string $pin): bool
    {
        return password_verify($pin, $participant['pin_hash']);
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    public static function validateUsername(string $username): void
    {
        if (!preg_match('/^[a-z0-9_.]{3,32}$/', $username)) {
            throw new \InvalidArgumentException(
                'Usernames are 3–32 characters, lowercase letters, numbers, periods, and underscores only.'
            );
        }
    }

    public static function validatePin(string $pin): void
    {
        if (!preg_match('/^\d{4}$/', $pin)) {
            throw new \InvalidArgumentException('PIN must be exactly 4 digits.');
        }
    }

    public static function displayName(array $participant): string
    {
        return trim($participant['first_name'] . ' ' . $participant['last_name']);
    }

    /** Anything other than the three known values (or blank) is treated as "not set" rather than rejected. */
    public static function normalizeLodgeAffiliation(string $value): ?string
    {
        return in_array($value, self::LODGE_AFFILIATIONS, true) ? $value : null;
    }

    /**
     * Same as normalizeLodgeAffiliation(), but this is now a required
     * question — asked at signup and re-asked on any profile save until
     * it's answered — so a missing/invalid value is a validation error
     * rather than a silent null.
     *
     * @throws \InvalidArgumentException if $value isn't one of the three valid choices.
     */
    public static function requireLodgeAffiliation(string $value): string
    {
        $normalized = self::normalizeLodgeAffiliation($value);
        if ($normalized === null) {
            throw new \InvalidArgumentException("Let us know if you're a 17 member, a lodge member, or not an Elk.");
        }
        return $normalized;
    }

    /**
     * Admin-only: set someone else's lodge affiliation directly, without
     * touching the rest of their profile — backs the /admin/participants
     * roster toggle.
     *
     * @throws \InvalidArgumentException if $value isn't one of the three valid choices.
     */
    public static function updateLodgeAffiliation(int $id, string $value): void
    {
        $normalized = self::requireLodgeAffiliation($value);
        $stmt = Database::connect()->prepare('UPDATE participants SET lodge_affiliation = :lodge_affiliation WHERE id = :id');
        $stmt->execute([':lodge_affiliation' => $normalized, ':id' => $id]);
    }

    /** Every active participant in a season, name order — backs the admin roster page. */
    public static function activeForSeason(int $seasonId): array
    {
        $stmt = Database::connect()->prepare(
            'SELECT * FROM participants WHERE season_id = :season_id AND is_active = 1
             ORDER BY first_name, last_name'
        );
        $stmt->execute([':season_id' => $seasonId]);
        return $stmt->fetchAll();
    }
}
