<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use CurlHandle;
use JsonException;

final class ChessComClient implements ChessComGateway
{
    private const API_ROOT = 'https://api.chess.com/pub/player/';
    private const CONTACT = 'https://github.com/vielhuber/chessmaster';

    /** @return list<string> */
    public function fetchArchiveUrls(string $username): array
    {
        $response = $this->request(self::API_ROOT . rawurlencode($username) . '/games/archives');
        if ($response->statusCode !== 200) {
            throw ChessmasterException::api(
                'Die Archivliste von Chess.com konnte nicht geladen werden (HTTP ' . $response->statusCode . ').',
            );
        }

        $payload = $this->decode($response->body);
        $archives = $payload['archives'] ?? null;
        if (!is_array($archives)) {
            throw ChessmasterException::api('Chess.com hat keine gültige Archivliste geliefert.');
        }

        return array_values(array_filter($archives, static fn(mixed $url): bool => is_string($url)));
    }

    public function fetchArchive(string $url, ?string $etag, ?string $lastModified): ArchiveResponse
    {
        if (!str_starts_with($url, self::API_ROOT)) {
            throw ChessmasterException::api('Chess.com hat eine ungültige Archiv-URL geliefert.');
        }

        $response = $this->request($url, $etag, $lastModified);
        if ($response->statusCode === 304) {
            return ArchiveResponse::notModified(
                etag: $response->etag ?? $etag,
                lastModified: $response->lastModified ?? $lastModified,
            );
        }
        if ($response->statusCode !== 200) {
            throw ChessmasterException::api(
                'Das Chess.com-Archiv konnte nicht geladen werden (HTTP ' . $response->statusCode . ').',
            );
        }

        $payload = $this->decode($response->body);
        $games = $payload['games'] ?? null;
        if (!is_array($games)) {
            throw ChessmasterException::api('Chess.com hat kein gültiges Spielearchiv geliefert.');
        }

        return ArchiveResponse::changed(
            games: array_values(array_filter($games, static fn(mixed $game): bool => is_array($game))),
            etag: $response->etag,
            lastModified: $response->lastModified,
        );
    }

    /**
     * Keep all PubAPI traffic serial and conditionally cached.
     */
    private function request(string $url, ?string $etag = null, ?string $lastModified = null): HttpResponse
    {
        $curl = curl_init($url);
        if (!($curl instanceof CurlHandle)) {
            throw ChessmasterException::api('Die HTTP-Anfrage konnte nicht vorbereitet werden.');
        }

        $requestHeaders = ['Accept: application/json'];
        if ($etag !== null && $etag !== '') {
            $requestHeaders[] = 'If-None-Match: ' . $etag;
        }
        if ($lastModified !== null && $lastModified !== '') {
            $requestHeaders[] = 'If-Modified-Since: ' . $lastModified;
        }

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => $this->userAgent(),
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $header = trim($header);
                if ($header === '') {
                    return $length;
                }
                if (str_starts_with($header, 'HTTP/')) {
                    $responseHeaders = [];
                    return $length;
                }

                $separator = strpos($header, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($header, 0, $separator)));
                    $responseHeaders[$name] = trim(substr($header, $separator + 1));
                }

                return $length;
            },
        ]);

        $body = curl_exec($curl);
        if ($body === false) {
            $message = curl_error($curl);
            throw ChessmasterException::api('Chess.com ist nicht erreichbar: ' . $message);
        }

        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        return new HttpResponse(
            statusCode: $statusCode,
            body: $body,
            etag: $responseHeaders['etag'] ?? null,
            lastModified: $responseHeaders['last-modified'] ?? null,
        );
    }

    /**
     * Identify this client as recommended by the PubAPI usage policy.
     */
    private function userAgent(): string
    {
        return 'chessmaster/1.0 (contact: ' . self::CONTACT . ')';
    }

    /** @return array<string, mixed> */
    private function decode(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ChessmasterException::api('Chess.com hat ungültiges JSON geliefert.', $exception);
        }

        if (!is_array($payload)) {
            throw ChessmasterException::api('Chess.com hat ein unerwartetes Datenformat geliefert.');
        }

        return $payload;
    }
}
