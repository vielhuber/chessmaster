<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use DateTimeImmutable;
use JsonException;

final class HtmlRenderer
{
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
