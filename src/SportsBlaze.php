<?php

namespace Pickem;

/**
 * SportsBlaze's undocumented "cache" host — no API key, no published
 * pricing/rate-limit/contract anywhere. This is the free option Jason chose
 * to get the season loaded and testable now, with the explicit understanding
 * that it could change or disappear without notice — not something to trust
 * for live in-game score polling later (Session 3). API-Sports (ApiSports.php,
 * dormant in this codebase) is the fallback if this stops working or if the
 * live-score cron ends up needing something more solid — $15/mo for their
 * PRO plan buys current-season access plus 7,500 requests/day, well past
 * what a paid API-Sports free tier offers (which turned out to be locked to
 * 2022-2024 seasons only — discovered live, after ESPN's Akamai block).
 *
 * The season schedule endpoint returns the WHOLE season (pre + regular +
 * post) in one call, so week-scoping happens client-side in Game.php.
 */
class SportsBlaze
{
    private const SEASON_SCHEDULE_URL = 'https://cache.sportsblaze.com/schedule/nfl';

    /**
     * @return array Raw decoded events array covering the full season.
     * @throws \RuntimeException on network/parse failure.
     */
    public static function fetchSeason(int $year): array
    {
        $url = self::SEASON_SCHEDULE_URL . '/' . $year;
        $body = self::get($url);

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['events'])) {
            throw new \RuntimeException("SportsBlaze's response didn't look like a schedule — the feed may have changed format.");
        }

        return $data['events'];
    }

    private static function get(string $url): string
    {
        if (function_exists('curl_init')) {
            return self::getViaCurl($url);
        }
        return self::getViaStream($url);
    }

    private static function getViaCurl(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'FRSN-Pickem/1.0 (+https://frsn.tv)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_ENCODING => '',
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // No explicit curl_close() -- it's a deprecated no-op as of PHP 8.5
        // (the handle frees itself when $ch goes out of scope either way).

        if ($body === false || $errno !== 0) {
            throw new \RuntimeException("Couldn't reach SportsBlaze — $error. Check your connection and try again.");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("SportsBlaze returned an unexpected response (HTTP $httpCode).");
        }

        return $body;
    }

    private static function getViaStream(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: FRSN-Pickem/1.0 (+https://frsn.tv)\r\nAccept: application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException("Couldn't reach SportsBlaze — check your connection and try again.");
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(2\d\d)\s/', $statusLine)) {
            throw new \RuntimeException("SportsBlaze returned an unexpected response ($statusLine).");
        }

        return $body;
    }
}
