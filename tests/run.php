<?php
declare(strict_types=1);

use vielhuber\chessmaster\Game;
use vielhuber\chessmaster\GameRepository;
use vielhuber\chessmaster\HtmlRenderer;
use vielhuber\chessmaster\ImportResult;
use vielhuber\chessmaster\LossReason;
use vielhuber\chessmaster\Page;

require dirname(__DIR__) . '/src/bootstrap.php';

/**
 * Fail with both values when a strict comparison differs.
 */
$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected === $actual) {
        return;
    }

    throw new RuntimeException(
        $message .
            PHP_EOL .
            'Expected: ' .
            var_export($expected, true) .
            PHP_EOL .
            'Actual: ' .
            var_export($actual, true),
    );
};

/**
 * Keep rendered output assertions readable.
 */
$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (str_contains($haystack, $needle)) {
        return;
    }

    throw new RuntimeException($message . PHP_EOL . 'Missing: ' . $needle);
};

$lossReasonLabels = [
    'checkmated' => 'Schachmatt',
    'timeout' => 'Zeitüberschreitung',
    'resigned' => 'Aufgabe',
    'lose' => 'Sonstige Niederlage',
    'abandoned' => 'Abbruch',
    'kingofthehill' => 'König erreichte den Hügel',
    'threecheck' => 'Drittes Schach',
    'bughousepartnerlose' => 'Bughouse-Partner verlor',
];

foreach ($lossReasonLabels as $result => $label) {
    $game = Game::fromApi(
        [
            'url' => 'https://www.chess.com/game/live/' . $result,
            'white' => ['username' => 'Player', 'rating' => 1200, 'result' => $result],
            'black' => ['username' => 'Opponent', 'rating' => 1250, 'result' => 'win'],
            'end_time' => 1_700_000_000,
            'time_class' => 'blitz',
            'time_control' => '300',
            'rules' => 'chess',
        ],
        'player',
    );
    $assertSame($result, $game->lossReason?->value, 'The documented loss result must be retained.');
    $assertSame($label, $game->lossReason?->label(), 'The documented loss result must have the expected label.');
}

$draw = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/draw',
        'white' => ['username' => 'Player', 'result' => 'agreed'],
        'black' => ['username' => 'Opponent', 'result' => 'agreed'],
    ],
    'player',
);
$assertSame(null, $draw->lossReason, 'A draw result must not become a loss reason.');

$unknownLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/unknown',
        'white' => ['username' => 'Player', 'result' => 'future-result'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
    ],
    'player',
);
$assertSame(null, $unknownLoss->lossReason, 'An undocumented result must not be presented as a known loss reason.');
$checkmatedLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/new-checkmate',
        'white' => ['username' => 'Player', 'result' => 'checkmated'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
    ],
    'player',
);

$databasePath = sys_get_temp_dir() . '/chessmaster-loss-reasons-' . bin2hex(random_bytes(8)) . '.sqlite';

try {
    $legacyDatabase = new PDO('sqlite:' . $databasePath);
    $legacyDatabase->exec(
        'CREATE TABLE games (
            url TEXT PRIMARY KEY,
            player_username TEXT NOT NULL,
            opponent TEXT NOT NULL,
            player_color TEXT NOT NULL,
            player_rating INTEGER,
            opponent_rating INTEGER,
            player_result TEXT NOT NULL,
            opponent_result TEXT NOT NULL,
            score REAL NOT NULL,
            played_at INTEGER NOT NULL,
            time_class TEXT NOT NULL,
            time_control TEXT NOT NULL,
            rules TEXT NOT NULL,
            rated INTEGER NOT NULL,
            player_accuracy REAL,
            opponent_accuracy REAL,
            opening_name TEXT NOT NULL,
            opening_url TEXT,
            pgn TEXT NOT NULL,
            raw_json TEXT NOT NULL
        )',
    );
    $legacyDatabase->exec(
        "INSERT INTO games VALUES (
            'https://www.chess.com/game/live/legacy', 'player', 'LegacyOpponent', 'white', 1200, 1250,
            'resigned', 'win', 0, 1700000000, 'blitz', '300', 'chess', 1, NULL, NULL,
            'Italian Game', NULL, '', '{}'
        )",
    );
    $legacyDatabase = null;

    $repository = new GameRepository($databasePath);
    $history = $repository->history('Player', 1, 100);
    $assertSame(LossReason::Resigned, $history->games[0]->lossReason, 'The migration must backfill existing losses.');

    $repository->storeArchive(
        url: 'https://api.chess.com/pub/player/player/games/2023/11',
        etag: null,
        lastModified: null,
        games: [$checkmatedLoss, $unknownLoss],
    );
    $dashboard = $repository->dashboard('Player');
    $assertSame(3, $dashboard->summary->losses, 'All known and unknown losses must remain in the total.');
    $lossReasonStatistics = [];
    foreach ($dashboard->lossReasons as $lossReasonStatistic) {
        $lossReasonStatistics[$lossReasonStatistic->reason?->value ?? 'unknown'] = $lossReasonStatistic;
    }
    $assertSame(1, $lossReasonStatistics['checkmated']->games, 'New loss reasons must be stored and aggregated.');
    $assertSame(1, $lossReasonStatistics['resigned']->games, 'Backfilled loss reasons must be aggregated.');
    $assertSame(
        null,
        $lossReasonStatistics['unknown']->reason,
        'Unknown loss reasons must stay explicitly unavailable.',
    );
    $assertSame(
        33.3,
        round($lossReasonStatistics['checkmated']->percentage, 1),
        'Loss reason percentages must use all losses.',
    );

    $_SERVER['USERNAME'] = 'Player';
    $_SERVER['DATABASE'] = $databasePath;
    $config = \vielhuber\chessmaster\Config::fromProjectRoot(dirname(__DIR__));
    $renderer = new HtmlRenderer();
    $importResult = new ImportResult(addedGames: 0, downloadedArchives: 0, skippedArchives: 0);
    $historyHtml = $renderer->render(
        Page::History,
        $config,
        null,
        $repository->history('Player', 1, 100),
        $importResult,
    );
    $statisticsHtml = $renderer->render(Page::Statistics, $config, $dashboard, null, $importResult);
    $assertContains('<th>Niederlagengrund</th>', $historyHtml, 'The history table must expose loss reasons.');
    $assertContains('Aufgabe', $historyHtml, 'The history table must render a known loss reason.');
    $assertContains('Nicht ermittelbar', $historyHtml, 'The history table must not invent an unknown loss reason.');
    $assertContains('Niederlagengründe', $statisticsHtml, 'The statistics page must aggregate loss reasons.');
    $assertContains('33,3 %', $statisticsHtml, 'The statistics table must render loss reason percentages.');
} finally {
    unset($_SERVER['USERNAME'], $_SERVER['DATABASE']);
    foreach ([$databasePath, $databasePath . '-shm', $databasePath . '-wal', $databasePath . '.lock'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

echo 'All tests passed.' . PHP_EOL;
