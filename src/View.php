<?php

namespace Pickem;

class View
{
    public static function render(string $template, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        $viewsDir = dirname(__DIR__) . '/src/views';
        include $viewsDir . '/layout-header.php';
        include $viewsDir . "/$template.php";
        include $viewsDir . '/layout-footer.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** Photo if they uploaded one, else their favorite team's logo, else a generic mark. */
    public static function avatarUrl(?array $participant): string
    {
        if ($participant === null) {
            return '/assets/default-avatar.svg';
        }
        if (!empty($participant['photo_path'])) {
            return '/avatar/' . $participant['id'];
        }
        if (!empty($participant['favorite_nfl_team_id'])) {
            $team = NflTeam::find((int) $participant['favorite_nfl_team_id']);
            if ($team) {
                return '/assets/team-logos/' . NflTeam::logoStd($team);
            }
        }
        return '/assets/default-avatar.svg';
    }
}
