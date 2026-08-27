<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public ?string $etag,
        public ?string $lastModified
    ) {}
}
