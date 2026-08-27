<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class ImportResult
{
    public function __construct(public int $addedGames, public int $downloadedArchives, public int $skippedArchives) {}
}
