<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class MonthlyStatistic
{
    public function __construct(public string $month, public int $games, public float $scorePercentage) {}
}
