<?php

namespace Pickem;

/**
 * API-Sports' American Football API (api-sports.io) — a real, documented,
 * keyed API. Built as the successor to ESPN (whose scoreboard sits behind
 * Akamai bot-detection that fingerprints the TLS handshake itself —
 * confirmed via a plain curl on Jason's own machine getting a clean 403
 * from AkamaiGHost, not fixable with headers).
 *
 * DORMANT — not what the admin sync button currently calls. Its free tier
 * turned out to be locked to the 2022-2024 seasons only, discovered live
 * when Jason actually tried it — no current-season access without the
 * $15/mo PRO plan (7,500 requests/day). SportsBlaze.php is what's wired up
 * for now instead, since it's free and already confirmed to have live 2026
 * data, with the explicit tradeoff that it's undocumented and could change
 * without notice. Come back to this — Game::syncWeekFromApiSports() — if
 * that becomes a real problem, or once Session 3's live-score poller needs
 * something with an actual contract behind it.
 *
 * Free tier is 100 requests/day, 10/minute, quota resets daily at 00:00 UTC
 * (PRO plan keeps the same shape at higher limits). The one-time-per-week
 * admin "sync week" call barely touches that either way. The budget
 * question that matters is Session 3's live-score poller: polling once
 * every 10 minutes from 11am-11pm MT on a Sunday is 72 calls — comfortably
 * under 100 — using a single /games?date=YYYY-MM-DD call that returns the
 * WHOLE day's slate at once, not one call per game. Thursday/Monday nights
 * get their own narrower windows (single game, evening only) on the same
 * cron, on a fresh quota since it resets per calendar day. Not built yet.
 */
class ApiSports
{
    private const BASE_URL = 'https://v1.american-football.api-sports.io';
    private const NFL_LEAGUE_ID = 1;

    /**
     * @return array Raw decoded game objects for the whole season (all
     *                stages — preseason/regular/playoffs — filtered by
     *                caller). One call, cheap, meant for the admin's
     *                once-a-week "sync this week's matchups" action.
     * @throws \RuntimeException on missing key / network / parse failure.
     */
    public static function fetchSeason(int $year): array
    {
        $key = Env::get('API_SPORTS_KEY');
        if (!$key) {
            throw new \RuntimeException('API_SPORTS_KEY is not set in .env — get a free key at api-sports.io and add it.');
        }

        $url = self::BASE_URL . '/games?' . http_build_query([
            'league' => self::NFL_LEAGUE_ID,
            'season' => $year,
        ]);

        $body = self::get($url, $key);

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['response'])) {
            throw new \RuntimeException("API-Sports' response didn't look like a games list — the feed may have changed format.");
        }
        if (!empty($data['errors'])) {
            $errors = is_array($data['errors']) ? implode('; ', array_map('strval', $data['errors'])) : (string) $data['errors'];
            throw new \RuntimeException("API-Sports rejected the request: $errors");
        }

        return $data['response'];
    }

    private static function get(string $url, string $key): string
    {
        if (function_exists('curl_init')) {
            return self::getViaCurl($url, $key);
        }
        return self::getViaStream($url, $key);
    }

    private static function getViaCurl(string $url, string $key): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'x-apisports-key: ' . $key,
                'Accept: application/json',
            ],
            CURLOPT_ENCODING => '',
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // No explicit curl_close() -- it's a deprecated no-op as of PHP 8.5
        // (the handle frees itself when $ch goes out of scope either way).

        if ($body === false || $errno !== 0) {
            throw new \RuntimeException("Couldn't reach API-Sports — $error. Check your connection and try again.");
        }
        if ($httpCode === 429) {
            throw new \RuntimeException('API-Sports rate limit hit (100/day or 10/minute on the free tier) — wait and try again.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("API-Sports returned an unexpected response (HTTP $httpCode).");
        }

        return $body;
    }

    private static function getViaStream(string $url, string $key): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "x-apisports-key: $key\r\nAccept: application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException("Couldn't reach API-Sports — check your connection and try again.");
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(2\d\d)\s/', $statusLine)) {
            throw new \RuntimeException("API-Sports returned an unexpected response ($statusLine).");
        }

        return $body;
    }
}
