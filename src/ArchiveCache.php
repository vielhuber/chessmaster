<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class ArchiveCache
{
    public function __construct(public string $url, public ?string $etag, public ?string $lastModified) {}
}
