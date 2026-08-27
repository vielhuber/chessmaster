<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class OpeningExample
{
    public function __construct(
        public string $familyName,
        public string $variationName,
        public string $pgn,
        public string $playerColor,
        public string $gameUrl,
        public int $games,
        public float $scorePercentage,
    ) {}
}
