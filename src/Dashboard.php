<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class Dashboard
{
    /**
     * @param list<TimeClassStatistic> $timeClasses
     * @param list<MonthlyStatistic> $months
     * @param list<OpeningStatistic> $openings
     * @param list<OpeningExample> $openingExamples
     */
    public function __construct(
        public Summary $summary,
        public array $timeClasses,
        public array $months,
        public array $openings,
        public ?OpeningStatistic $bestOpening,
        public ?OpeningStatistic $worstOpening,
        public array $openingExamples,
    ) {}
}
