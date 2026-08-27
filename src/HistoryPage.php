<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class HistoryPage
{
    /** @param list<Game> $games */
    public function __construct(public array $games, public int $page, public int $pageCount, public int $total) {}
}
