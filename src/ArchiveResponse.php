<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class ArchiveResponse
{
    /**
     * Distinguish cached responses from fresh game payloads.
     *
     * @param list<array<string, mixed>> $games
     */
    private function __construct(
        public bool $notModified,
        public array $games,
        public ?string $etag,
        public ?string $lastModified
    ) {}

    /**
     * Carry a fresh monthly payload into the importer.
     *
     * @param list<array<string, mixed>> $games
     */
    public static function changed(array $games, ?string $etag, ?string $lastModified): self
    {
        return new self(false, $games, $etag, $lastModified);
    }

    /**
     * Preserve validators when Chess.com confirms cached data.
     */
    public static function notModified(?string $etag, ?string $lastModified): self
    {
        return new self(true, [], $etag, $lastModified);
    }
}
