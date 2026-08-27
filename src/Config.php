<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use DateTimeZone;

final readonly class Config
{
    private const TIMEZONE = 'Europe/Berlin';

    /**
     * Keep validated settings immutable for one request.
     */
    private function __construct(public string $username, public string $databasePath, public DateTimeZone $timezone) {}

    /**
     * Resolve local settings while allowing server-level overrides.
     */
    public static function fromProjectRoot(string $projectRoot): self
    {
        $envPath = $projectRoot . '/.env';
        $fileValues = [];
        if (is_file($envPath)) {
            $parsedValues = parse_ini_file($envPath, false, INI_SCANNER_RAW);
            if ($parsedValues === false) {
                throw ChessmasterException::configuration('Die .env konnte nicht gelesen werden.');
            }
            $fileValues = $parsedValues;
        }

        $username = self::value('USERNAME', $fileValues);
        if ($username === '') {
            throw ChessmasterException::configuration(
                'USERNAME fehlt. Bitte .env.example nach .env kopieren und den Benutzernamen eintragen.',
            );
        }
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $username) !== 1) {
            throw ChessmasterException::configuration('USERNAME enthält ungültige Zeichen.');
        }

        $databasePath = self::value('DATABASE', $fileValues);
        if ($databasePath === '') {
            $databasePath = '_data/chessmaster.sqlite';
        }
        if (!str_starts_with($databasePath, '/')) {
            $databasePath = $projectRoot . '/' . ltrim($databasePath, '/');
        }

        return new self(username: $username, databasePath: $databasePath, timezone: new DateTimeZone(self::TIMEZONE));
    }

    /**
     * Prefer process configuration over values from the local file.
     *
     * @param array<string, mixed> $fileValues
     */
    private static function value(string $key, array $fileValues): string
    {
        $serverValue = $_SERVER[$key] ?? ($_ENV[$key] ?? getenv($key));
        if ($serverValue !== false && $serverValue !== null) {
            return trim((string) $serverValue);
        }

        return trim((string) ($fileValues[$key] ?? ''));
    }
}
