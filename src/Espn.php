<?php

namespace Pickem;

class Espn
{
    private const BASE_URL = 'https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard';

    /**
     * @return array Raw decoded events array from ESPN's scoreboard feed.
     * @throws \RuntimeException on network/parse failure — caller decides how to surface it.
     */
    public static function fetchWeek(int $year, int $week, int $seasonType = 2): array
    {
        $url = self::BASE_URL . '?' . http_build_query([
            'year' => $year,
            'week' => $week,
            'seasontype' => $seasonType,
        ]);

        $body = self::get($url);

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['events'])) {
            throw new \RuntimeException("ESPN's response didn't look like a scoreboard — the feed may have changed format.");
        }

        return $data['events'];
    }

    /**
     * ESPN's scoreboard sits behind bot-detection that tends to 403 PHP's
     * plain file_get_contents() stream wrapper specifically, even though the
     * exact same URL works fine from a browser or from curl — a known quirk
     * of this endpoint, not an auth requirement. curl with browser-like
     * headers is the standard workaround, so that's the primary path here;
     * file_get_contents is kept only as a last-resort fallback for an
     * environment where curl genuinely isn't available.
     */
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
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_ENCODING => '', // ask curl to negotiate + auto-decompress gzip/deflate
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $errno !== 0) {
            throw new \RuntimeException("Couldn't reach ESPN — $error. Check your connection and try again.");
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("ESPN returned an unexpected response (HTTP $httpCode).");
        }

        return $body;
    }

    private static function getViaStream(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
                    . "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\n"
                    . "Accept: application/json, text/plain, */*\r\n"
                    . "Accept-Language: en-US,en;q=0.9\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException("Couldn't reach ESPN — check your connection and try again.");
        }

        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/\s(2\d\d)\s/', $statusLine)) {
            throw new \RuntimeException("ESPN returned an unexpected response ($statusLine).");
        }

        return $body;
    }
}
