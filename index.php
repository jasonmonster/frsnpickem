<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Season.php';
require_once __DIR__ . '/src/NflTeam.php';
require_once __DIR__ . '/src/Participant.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/Photo.php';
require_once __DIR__ . '/src/Espn.php';
require_once __DIR__ . '/src/ApiSports.php';
require_once __DIR__ . '/src/SportsBlaze.php';
require_once __DIR__ . '/src/Game.php';
require_once __DIR__ . '/src/Pick.php';
require_once __DIR__ . '/src/TiebreakerAnswer.php';
require_once __DIR__ . '/src/Grading.php';
require_once __DIR__ . '/src/Leaderboard.php';
require_once __DIR__ . '/src/Payment.php';
require_once __DIR__ . '/src/WeeklyResult.php';
require_once __DIR__ . '/src/TrashTalk.php';
require_once __DIR__ . '/src/View.php';

use Pickem\Auth;
use Pickem\Game;
use Pickem\Grading;
use Pickem\Leaderboard;
use Pickem\NflTeam;
use Pickem\Participant;
use Pickem\Payment;
use Pickem\Photo;
use Pickem\Pick;
use Pickem\Season;
use Pickem\TiebreakerAnswer;
use Pickem\TrashTalk;
use Pickem\View;
use Pickem\WeeklyResult;

Auth::start();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// --- Home -------------------------------------------------------------
if ($path === '/' && $method === 'GET') {
    $me = Auth::currentParticipant();
    if ($me === null) {
        header('Location: /login');
        exit;
    }
    View::render('dashboard', ['pageTitle' => "FRSN Pick'em", 'participant' => $me]);
    exit;
}

// --- Signup -------------------------------------------------------------
if ($path === '/signup' && $method === 'GET') {
    View::render('signup', ['pageTitle' => "Sign up — FRSN Pick'em", 'teams' => NflTeam::all()]);
    exit;
}

if ($path === '/signup' && $method === 'POST') {
    $season = Season::active();
    if ($season === null) {
        View::render('signup', [
            'pageTitle' => "Sign up — FRSN Pick'em",
            'teams' => NflTeam::all(),
            'error' => 'Signups are closed right now — no active season. Check back soon.',
            'old' => $_POST,
        ]);
        exit;
    }

    try {
        $participant = Participant::create(array_merge($_POST, ['season_id' => $season['id']]));

        if (!empty($_FILES['photo']['name'])) {
            try {
                $filename = Photo::handleUpload($_FILES['photo'], $participant['id']);
                Participant::updatePhoto($participant['id'], $filename);
                $participant['photo_path'] = $filename;
            } catch (\InvalidArgumentException $e) {
                // Signup still succeeds without a photo — they can add one from their profile.
            }
        }

        Auth::login($participant);
        header('Location: /');
        exit;
    } catch (\InvalidArgumentException $e) {
        View::render('signup', [
            'pageTitle' => "Sign up — FRSN Pick'em",
            'teams' => NflTeam::all(),
            'error' => $e->getMessage(),
            'old' => $_POST,
        ]);
        exit;
    }
}

// --- Login / logout -------------------------------------------------------------
if ($path === '/login' && $method === 'GET') {
    View::render('login', ['pageTitle' => "Log in — FRSN Pick'em"]);
    exit;
}

if ($path === '/login' && $method === 'POST') {
    $season = Season::active();
    $username = Participant::normalizeUsername($_POST['username'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $participant = $season ? Participant::findByUsername((int) $season['id'], $username) : null;

    if ($participant === null || !$participant['is_active'] || !Participant::verifyPin($participant, $pin)) {
        View::render('login', [
            'pageTitle' => "Log in — FRSN Pick'em",
            'error' => 'Username or PIN not recognized.',
            'old' => $_POST,
        ]);
        exit;
    }

    Auth::login($participant);
    header('Location: /');
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// --- Profile -------------------------------------------------------------
if ($path === '/profile' && $method === 'GET') {
    $me = Auth::requireLogin();
    View::render('profile', ['pageTitle' => 'Your profile', 'participant' => $me, 'teams' => NflTeam::all()]);
    exit;
}

if ($path === '/profile' && $method === 'POST') {
    $me = Auth::requireLogin();
    try {
        $updated = Participant::updateProfile((int) $me['id'], $_POST);
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => $updated,
            'teams' => NflTeam::all(),
            'success' => 'Profile updated.',
        ]);
    } catch (\InvalidArgumentException $e) {
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => Participant::find((int) $me['id']),
            'teams' => NflTeam::all(),
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

if ($path === '/profile/photo' && $method === 'POST') {
    $me = Auth::requireLogin();
    try {
        $filename = Photo::handleUpload($_FILES['photo'] ?? [], (int) $me['id']);
        Participant::updatePhoto((int) $me['id'], $filename);
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => Participant::find((int) $me['id']),
            'teams' => NflTeam::all(),
            'success' => 'Photo updated.',
        ]);
    } catch (\InvalidArgumentException $e) {
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => $me,
            'teams' => NflTeam::all(),
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

if ($path === '/profile/pin' && $method === 'POST') {
    $me = Auth::requireLogin();
    try {
        Participant::updatePin((int) $me['id'], trim($_POST['pin'] ?? ''));
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => Participant::find((int) $me['id']),
            'teams' => NflTeam::all(),
            'success' => 'PIN updated.',
        ]);
    } catch (\InvalidArgumentException $e) {
        View::render('profile', [
            'pageTitle' => 'Your profile',
            'participant' => $me,
            'teams' => NflTeam::all(),
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

// --- Picks -------------------------------------------------------------
if ($path === '/picks' && $method === 'GET') {
    $me = Auth::requireLogin();
    $week = Season::currentWeek((int) $me['season_id']);
    $games = Game::forWeek((int) $me['season_id'], $week);
    View::render('picks', [
        'pageTitle' => "Week $week picks",
        'week' => $week,
        'games' => $games,
        'lockTimes' => Game::lockTimes($games),
        'now' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        'picks' => Pick::forParticipantWeek((int) $me['id'], array_column($games, 'id')),
        'tiebreaker' => TiebreakerAnswer::forParticipantWeek((int) $me['id'], $week),
        'teams' => NflTeam::allById(),
    ]);
    exit;
}

if ($path === '/picks' && $method === 'POST') {
    $me = Auth::requireLogin();
    $week = (int) ($_POST['week'] ?? Season::currentWeek((int) $me['season_id']));
    $games = Game::forWeek((int) $me['season_id'], $week);
    $lockTimes = Game::lockTimes($games);
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $saved = 0;
    $skippedLocked = 0;
    foreach ($games as $game) {
        $field = 'pick_' . $game['id'];
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            continue;
        }
        if (Game::isLocked($game, $lockTimes, $now)) {
            $skippedLocked++;
            continue;
        }
        Pick::submit((int) $me['id'], (int) $game['id'], (int) $_POST[$field]);
        $saved++;
    }

    $weekendLocked = $lockTimes['weekend'] !== null && $now >= $lockTimes['weekend'];
    if (!$weekendLocked && isset($_POST['tiebreaker']) && $_POST['tiebreaker'] !== '') {
        TiebreakerAnswer::submit((int) $me['id'], $week, (int) $_POST['tiebreaker']);
    }

    $message = "$saved pick" . ($saved === 1 ? '' : 's') . " saved.";
    if ($skippedLocked > 0) {
        $message .= " $skippedLocked game" . ($skippedLocked === 1 ? ' was' : 's were') . " already locked and skipped.";
    }

    View::render('picks', [
        'pageTitle' => "Week $week picks",
        'week' => $week,
        'games' => $games,
        'lockTimes' => $lockTimes,
        'now' => $now,
        'picks' => Pick::forParticipantWeek((int) $me['id'], array_column($games, 'id')),
        'tiebreaker' => TiebreakerAnswer::forParticipantWeek((int) $me['id'], $week),
        'teams' => NflTeam::allById(),
        'success' => $message,
    ]);
    exit;
}

// --- Admin: game sync -------------------------------------------------------------
if ($path === '/admin/games' && $method === 'GET') {
    $me = Auth::requireAdmin();
    $season = Season::find((int) $me['season_id']);
    $week = (int) ($_GET['week'] ?? Season::currentWeek((int) $me['season_id']));
    View::render('admin-games', [
        'pageTitle' => 'Admin — games',
        'week' => $week,
        'season' => $season,
        'games' => Game::forWeek((int) $me['season_id'], $week),
        'teams' => NflTeam::allById(),
    ]);
    exit;
}

if ($path === '/admin/games/sync' && $method === 'POST') {
    $me = Auth::requireAdmin();
    $season = Season::find((int) $me['season_id']);
    $week = (int) ($_POST['week'] ?? Season::currentWeek((int) $me['season_id']));
    $success = null;
    $error = null;
    try {
        $count = Game::syncWeekFromSportsBlaze((int) $season['id'], $week, (int) $season['year']);
        $graded = Grading::gradeSeason((int) $season['id']);
        $success = "Synced $count game" . ($count === 1 ? '' : 's') . " for week $week.";
        if ($graded > 0) {
            $success .= " Graded $graded pick" . ($graded === 1 ? '' : 's') . " from final games.";
        }
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }
    View::render('admin-games', [
        'pageTitle' => 'Admin — games',
        'week' => $week,
        'season' => $season,
        'games' => Game::forWeek((int) $me['season_id'], $week),
        'teams' => NflTeam::allById(),
        'success' => $success,
        'error' => $error,
    ]);
    exit;
}

// --- Admin: payment log + weekly finalize -------------------------------------------------------------
if ($path === '/admin/payments' && $method === 'GET') {
    $me = Auth::requireAdmin();
    $season = Season::find((int) $me['season_id']);
    $week = (int) ($_GET['week'] ?? Season::currentWeek((int) $me['season_id']));
    View::render('admin-payments', [
        'pageTitle' => 'Admin — payments',
        'week' => $week,
        'season' => $season,
        'statuses' => Payment::statusForWeek((int) $season['id'], $week),
        'result' => WeeklyResult::find((int) $season['id'], $week),
    ]);
    exit;
}

if ($path === '/admin/payments' && $method === 'POST') {
    $me = Auth::requireAdmin();
    $season = Season::find((int) $me['season_id']);
    $week = (int) ($_POST['week'] ?? Season::currentWeek((int) $me['season_id']));

    $statuses = Payment::statusForWeek((int) $season['id'], $week);
    foreach ($statuses as $s) {
        $paid = isset($_POST['paid_' . $s['id']]);
        Payment::setPaid((int) $s['id'], $week, $paid, (string) $me['username']);
    }

    View::render('admin-payments', [
        'pageTitle' => 'Admin — payments',
        'week' => $week,
        'season' => $season,
        'statuses' => Payment::statusForWeek((int) $season['id'], $week),
        'result' => WeeklyResult::find((int) $season['id'], $week),
        'success' => 'Payments saved for week ' . $week . '.',
    ]);
    exit;
}

if ($path === '/admin/weekly-results/finalize' && $method === 'POST') {
    $me = Auth::requireAdmin();
    $season = Season::find((int) $me['season_id']);
    $week = (int) ($_POST['week'] ?? Season::currentWeek((int) $me['season_id']));

    $success = null;
    $error = null;
    try {
        WeeklyResult::finalize((int) $season['id'], $week);
        $success = "Week $week finalized.";
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }

    View::render('admin-payments', [
        'pageTitle' => 'Admin — payments',
        'week' => $week,
        'season' => $season,
        'statuses' => Payment::statusForWeek((int) $season['id'], $week),
        'result' => WeeklyResult::find((int) $season['id'], $week),
        'success' => $success,
        'error' => $error,
    ]);
    exit;
}

// --- Results (official, finalized weeks) -------------------------------------------------------------
if ($path === '/results' && $method === 'GET') {
    $me = Auth::requireLogin();
    $season = Season::find((int) $me['season_id']);
    View::render('results', [
        'pageTitle' => 'Results',
        'season' => $season,
        'weeklyResults' => WeeklyResult::forSeason((int) $season['id']),
        'seasonHoldbackCents' => WeeklyResult::seasonHoldbackCents((int) $season['id']),
    ]);
    exit;
}

// --- Trash talk -------------------------------------------------------------
if ($path === '/talk' && $method === 'GET') {
    $me = Auth::requireLogin();
    View::render('talk', [
        'pageTitle' => 'Trash talk',
        'week' => Season::currentWeek((int) $me['season_id']),
        'posts' => TrashTalk::forSeason((int) $me['season_id'], (int) $me['id']),
        'success' => isset($_GET['posted']) ? 'Posted.' : null,
    ]);
    exit;
}

if ($path === '/talk' && $method === 'POST') {
    $me = Auth::requireLogin();
    $week = Season::currentWeek((int) $me['season_id']);
    try {
        TrashTalk::post((int) $me['season_id'], (int) $me['id'], $week, (string) ($_POST['body'] ?? ''));
        // Redirect rather than re-render: re-rendering the POST response
        // directly meant reloading that page (or hitting back then forward)
        // re-submitted the same post every time — that's what produced the
        // repeated "you guys suck" spam. Redirecting to a GET breaks that.
        header('Location: /talk?posted=1');
        exit;
    } catch (\InvalidArgumentException $e) {
        // Nothing was written, so re-rendering in place here is safe —
        // no resubmission risk on a refresh since there's no POST to replay
        // once the browser has this response.
        View::render('talk', [
            'pageTitle' => 'Trash talk',
            'week' => $week,
            'posts' => TrashTalk::forSeason((int) $me['season_id'], (int) $me['id']),
            'error' => $e->getMessage(),
            'old' => (string) ($_POST['body'] ?? ''),
        ]);
        exit;
    }
}

if ($path === '/talk/vote' && $method === 'POST') {
    $me = Auth::requireLogin();
    $postId = (int) ($_POST['post_id'] ?? 0);
    $direction = ($_POST['direction'] ?? '') === 'down' ? -1 : 1;
    if ($postId > 0) {
        TrashTalk::toggleVote($postId, (int) $me['id'], $direction);
    }
    header('Location: /talk#talk-' . $postId);
    exit;
}

// --- Leaderboard -------------------------------------------------------------
if ($path === '/leaderboard' && $method === 'GET') {
    $me = Auth::requireLogin();
    $season = Season::find((int) $me['season_id']);
    // Cheap and idempotent — keeps standings honest even if someone lands
    // here between a sync and whatever else would normally trigger grading.
    Grading::gradeSeason((int) $season['id']);
    View::render('leaderboard', [
        'pageTitle' => 'Standings',
        'season' => $season,
        'week' => Season::currentWeek((int) $me['season_id']),
        'standings' => Leaderboard::standings((int) $season['id']),
    ]);
    exit;
}

// --- Avatar streaming (photos live outside the public webroot) -------------------------------------------------------------
if (preg_match('#^/avatar/(\d+)$#', $path, $m) && $method === 'GET') {
    $participant = Participant::find((int) $m[1]);
    if ($participant === null || empty($participant['photo_path'])) {
        header('Location: /assets/default-avatar.svg');
        exit;
    }
    $file = Photo::path($participant['photo_path']);
    if (!is_file($file)) {
        header('Location: /assets/default-avatar.svg');
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=3600');
    readfile($file);
    exit;
}

// --- 404 -------------------------------------------------------------
http_response_code(404);
echo "Not found.";
