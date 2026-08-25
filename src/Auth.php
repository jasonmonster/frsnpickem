<?php

namespace Pickem;

class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $days = (int) Env::get('SESSION_COOKIE_DAYS', '365');
            session_set_cookie_params([
                'lifetime' => 60 * 60 * 24 * $days,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (Env::get('APP_ENV', 'local') !== 'local'),
            ]);
            session_start();
        }
    }

    public static function login(array $participant): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['participant_id'] = $participant['id'];
        $_SESSION['season_id'] = $participant['season_id'];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path']);
        }
        session_destroy();
    }

    public static function currentParticipant(): ?array
    {
        self::start();
        if (empty($_SESSION['participant_id'])) {
            return null;
        }
        return Participant::find((int) $_SESSION['participant_id']);
    }

    public static function requireLogin(): array
    {
        $participant = self::currentParticipant();
        if ($participant === null) {
            header('Location: /login');
            exit;
        }
        return $participant;
    }

    public static function requireAdmin(): array
    {
        $participant = self::requireLogin();
        if (empty($participant['is_admin'])) {
            http_response_code(403);
            echo 'Admins only.';
            exit;
        }
        return $participant;
    }
}
