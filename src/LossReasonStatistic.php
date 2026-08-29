<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class LossReasonStatistic
{
    public function __construct(public ?LossReason $reason, public int $games, public float $percentage) {}
}
