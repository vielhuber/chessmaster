<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

enum LossReason: string
{
    case Checkmated = 'checkmated';
    case Timeout = 'timeout';
    case Resigned = 'resigned';
    case Lose = 'lose';
    case Abandoned = 'abandoned';
    case KingOfTheHill = 'kingofthehill';
    case ThreeCheck = 'threecheck';
    case BughousePartnerLose = 'bughousepartnerlose';

    /**
     * Translate the documented API result without changing its meaning.
     */
    public function label(): string
    {
        return match ($this) {
            self::Checkmated => 'Schachmatt',
            self::Timeout => 'Zeitüberschreitung',
            self::Resigned => 'Aufgabe',
            self::Lose => 'Sonstige Niederlage',
            self::Abandoned => 'Abbruch',
            self::KingOfTheHill => 'König erreichte den Hügel',
            self::ThreeCheck => 'Drittes Schach',
            self::BughousePartnerLose => 'Bughouse-Partner verlor',
        };
    }
}
