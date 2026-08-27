<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class Summary
{
    public function __construct(
        public int $games,
        public int $wins,
        public int $draws,
        public int $losses,
        public float $scorePercentage,
        public ?float $averageAccuracy
    ) {}
}
