<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class OpeningStatistic
{
    public function __construct(
        public string $name,
        public ?string $url,
        public int $games,
        public int $wins,
        public int $draws,
        public int $losses,
        public float $scorePercentage
    ) {}
}
