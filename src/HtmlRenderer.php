<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use DateTimeImmutable;
use JsonException;

final class HtmlRenderer
{
    private const CHESS_QUOTES = [
        [
            'text' => 'Das Schach hat wie die Liebe, wie die Musik die Fähigkeit, den Menschen glücklich zu machen.',
            'author' => 'Siegbert Tarrasch',
            'source' => 'Siegbert Tarrasch: Das Schachspiel (Berlin 1931), Seite 4',
            'url' => 'https://www.chesshistory.com/winter/extra/tarrasch.html',
        ],
        [
            'text' => 'Nun, auf dem Schachbrett der Meister gilt Lüge und Heuchelei nicht lange.',
            'author' => 'Emanuel Lasker',
            'source' => 'Emanuel Lasker: Lehrbuch des Schachspiels (Berlin 1926), Seite 201',
            'url' => 'https://www.chesshistory.com/winter/extra/lasker1.html',
        ],
        [
            'text' => 'Die Bauern sind die Seele des Schachspiels.',
            'author' => 'François-André Danican Philidor',
            'source' => 'François-André Danican Philidor: L\'Analyze des Échecs (London 1749), Seite xix; deutsche Übersetzung',
            'url' => 'https://www.chesshistory.com/winter/extra/philidor.html',
        ],
        [
            'text' => 'Im Schach können sich die Taktiken ändern, doch die strategischen Grundprinzipien bleiben immer gleich.',
            'author' => 'José Raúl Capablanca',
            'source' => 'José Raúl Capablanca: Chess Fundamentals, Vorwort zur Ausgabe von 1934; deutsche Übersetzung',
            'url' => 'https://www.gutenberg.org/files/33870/33870-h/33870-h.htm',
        ],
        [
            'text' => 'Man mag zwar die Partie gegen seinen Gegner verlieren, gewinnt aber etwas Besseres: seine Achtung, seinen Respekt und seine Zuneigung.',
            'author' => 'Benjamin Franklin',
            'source' => 'Benjamin Franklin: The Morals of Chess (Fassung von 1786); deutsche Übersetzung',
            'url' => 'https://founders.archives.gov/documents/Franklin/01-29-02-0608',
        ],
        [
            'text' => 'Die Leidenschaft für das Schachspiel ist eine der unerklärlichsten der Welt.',
            'author' => 'H. G. Wells',
            'source' => 'H. G. Wells: Certain Personal Matters, Concerning Chess (1897); deutsche Übersetzung',
            'url' => 'https://www.gutenberg.org/files/17508/17508-h/17508-h.htm',
        ],
    ];

    /**
     * Isolate presentation variables from application orchestration.
     */
    public function render(
        Page $page,
        Config $config,
        ?Dashboard $dashboard,
        ?HistoryPage $history,
        ImportResult $importResult,
    ): string {
        $chartData = $dashboard === null ? '{}' : $this->chartData($dashboard);
        $openingExamplesData = '[]';
        if ($dashboard !== null && $dashboard->openingExamples !== []) {
            $openingExamplesData = json_encode(
                array_map(
                    static fn(OpeningExample $example): array => [
                        'pgn' => $example->pgn,
                        'playerColor' => $example->playerColor,
                    ],
                    $dashboard->openingExamples,
                ),
                JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            );
        }
        $timezone = $config->timezone;
        $username = $config->username;
        $currentYear = (new DateTimeImmutable('now', $timezone))->format('Y');
        clearstatcache(true, $config->databasePath);
        $databaseSizeBytes = is_file($config->databasePath) ? filesize($config->databasePath) : false;
        if ($databaseSizeBytes === false) {
            throw ChessmasterException::storage('Die Größe der SQLite-Datenbank konnte nicht gelesen werden.');
        }
        $databaseSize = (float) $databaseSizeBytes;
        $databaseSizeUnits = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $databaseSizeUnitIndex = 0;
        while ($databaseSize >= 1024 && $databaseSizeUnitIndex < count($databaseSizeUnits) - 1) {
            $databaseSize /= 1024;
            $databaseSizeUnitIndex++;
        }
        $formattedDatabaseSize =
            number_format($databaseSize, $databaseSizeUnitIndex === 0 ? 0 : 2, ',', '.') .
            ' ' .
            $databaseSizeUnits[$databaseSizeUnitIndex];
        $chessQuotes = self::CHESS_QUOTES;
        $chessQuote = $chessQuotes[random_int(0, count($chessQuotes) - 1)];
        $template = dirname(__DIR__) . '/templates/index.php';

        ob_start();
        require $template;
        $html = ob_get_clean();

        return is_string($html) ? $html : '';
    }

    /**
     * Return a usable response without exposing an exception trace.
     */
    public function renderError(string $message): string
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" ' .
            'content="width=device-width, initial-scale=1"><title>chessmaster</title><link rel="stylesheet" ' .
            'href="assets/style.css"></head><body><main class="error"><p class="eyebrow">chessmaster</p>' .
            '<h1>Import fehlgeschlagen</h1><p>' .
            $safeMessage .
            '</p></main></body></html>';
    }

    /**
     * Serialize only aggregate values required by Chart.js.
     *
     * @throws JsonException
     */
    private function chartData(Dashboard $dashboard): string
    {
        return json_encode(
            [
                'results' => [
                    'labels' => ['Siege', 'Remis', 'Niederlagen'],
                    'values' => [$dashboard->summary->wins, $dashboard->summary->draws, $dashboard->summary->losses],
                ],
                'timeClasses' => [
                    'labels' => array_map(
                        static fn(TimeClassStatistic $statistic): string => ucfirst($statistic->timeClass),
                        $dashboard->timeClasses,
                    ),
                    'wins' => array_map(
                        static fn(TimeClassStatistic $statistic): int => $statistic->wins,
                        $dashboard->timeClasses,
                    ),
                    'draws' => array_map(
                        static fn(TimeClassStatistic $statistic): int => $statistic->draws,
                        $dashboard->timeClasses,
                    ),
                    'losses' => array_map(
                        static fn(TimeClassStatistic $statistic): int => $statistic->losses,
                        $dashboard->timeClasses,
                    ),
                ],
                'months' => [
                    'labels' => array_map(
                        static fn(MonthlyStatistic $statistic): string => $statistic->month,
                        $dashboard->months,
                    ),
                    'scores' => array_map(
                        static fn(MonthlyStatistic $statistic): float => round($statistic->scorePercentage, 1),
                        $dashboard->months,
                    ),
                    'games' => array_map(
                        static fn(MonthlyStatistic $statistic): int => $statistic->games,
                        $dashboard->months,
                    ),
                ],
            ],
            JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }
}
