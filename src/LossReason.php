<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

enum LossReason: string
{
    public const ANALYSIS_VERSION = 1;

    case Blunder = 'blunder';
    case Outplayed = 'outplayed';
    case TooSlow = 'too_slow';
    case Abandoned = 'abandoned';
    case Unknown = 'unknown';

    /**
     * Expose the stable label used by history and statistics.
     */
    public function label(): string
    {
        return match ($this) {
            self::Blunder => 'Blunder',
            self::Outplayed => 'Ausgespielt',
            self::TooSlow => 'Zu langsam',
            self::Abandoned => 'Abbruch',
            self::Unknown => 'Unbekannt',
        };
    }
}
