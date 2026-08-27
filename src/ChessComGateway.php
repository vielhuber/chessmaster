<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

interface ChessComGateway
{
    /** @return list<string> */
    public function fetchArchiveUrls(string $username): array;

    /**
     * Allow conditional retrieval of one monthly payload.
     */
    public function fetchArchive(string $url, ?string $etag, ?string $lastModified): ArchiveResponse;
}
