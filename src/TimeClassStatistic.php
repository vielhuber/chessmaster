<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class TimeClassStatistic
{
    public function __construct(
        public string $timeClass,
        public int $games,
        public int $wins,
        public int $draws,
        public int $losses,
        public float $scorePercentage,
        public ?int $latestRating
    ) {}
}
