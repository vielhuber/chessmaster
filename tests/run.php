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
    LossReason::Blunder->value => 'Blunder',
    LossReason::Outplayed->value => 'Ausgespielt',
    LossReason::TooSlow->value => 'Zu langsam',
    LossReason::Abandoned->value => 'Abbruch',
    LossReason::Unknown->value => 'Unbekannt',
];
foreach (LossReason::cases() as $lossReason) {
    $assertSame($lossReasonLabels[$lossReason->value], $lossReason->label(), 'Every loss category needs its label.');
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

$pendingLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/pending',
        'white' => ['username' => 'Player', 'result' => 'checkmated'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
        'rules' => 'chess',
        'pgn' => '1. f3 e5 2. g4 Qh4#',
    ],
    'player',
);
$assertSame(LossReason::Unknown, $pendingLoss->lossReason, 'A board loss must await Stockfish analysis.');
$assertSame(null, $pendingLoss->lossAnalysisVersion, 'An analyzable board loss must remain pending.');

$tooSlowLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/timeout',
        'white' => ['username' => 'Player', 'result' => 'timeout'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
    ],
    'player',
);
$assertSame(LossReason::TooSlow, $tooSlowLoss->lossReason, 'A timeout must be classified without Stockfish.');
$assertSame(LossReason::ANALYSIS_VERSION, $tooSlowLoss->lossAnalysisVersion, 'A timeout must be complete.');

$abandonedLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/abandoned',
        'white' => ['username' => 'Player', 'result' => 'abandoned'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
    ],
    'player',
);
$assertSame(LossReason::Abandoned, $abandonedLoss->lossReason, 'An abandoned game must remain distinct.');

$variantLoss = Game::fromApi(
    [
        'url' => 'https://www.chess.com/game/live/variant',
        'white' => ['username' => 'Player', 'result' => 'threecheck'],
        'black' => ['username' => 'Opponent', 'result' => 'win'],
        'rules' => 'threecheck',
        'pgn' => '1. e4 e5',
    ],
    'player',
);
$assertSame(LossReason::Unknown, $variantLoss->lossReason, 'Unsupported variants must remain unknown.');
$assertSame(LossReason::ANALYSIS_VERSION, $variantLoss->lossAnalysisVersion, 'Unsupported variants must not be queued.');

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
            'Italian Game', NULL, '1. e4 e5', '{}'
        );
        INSERT INTO games VALUES (
            'https://www.chess.com/game/live/legacy-timeout', 'player', 'SlowOpponent', 'white', 1200, 1250,
            'timeout', 'win', 0, 1699999999, 'blitz', '300', 'chess', 1, NULL, NULL,
            'Italian Game', NULL, '1. e4 e5', '{}'
        );
        INSERT INTO games VALUES (
            'https://www.chess.com/game/live/legacy-abandoned', 'player', 'GoneOpponent', 'white', 1200, 1250,
            'abandoned', 'win', 0, 1699999998, 'blitz', '300', 'chess', 1, NULL, NULL,
            'Italian Game', NULL, '1. e4 e5', '{}'
        )",
    );
    $legacyDatabase = null;

    $repository = new GameRepository($databasePath);
    $history = $repository->history('Player', 1, 100);
    $historyByUrl = [];
    foreach ($history->games as $historyGame) {
        $historyByUrl[$historyGame->url] = $historyGame;
    }
    $assertSame(
        LossReason::Unknown,
        $historyByUrl['https://www.chess.com/game/live/legacy']->lossReason,
        'Old board results must await analysis.',
    );
    $assertSame(
        null,
        $historyByUrl['https://www.chess.com/game/live/legacy']->lossAnalysisVersion,
        'An old standard loss must be queued.',
    );
    $assertSame(
        LossReason::TooSlow,
        $historyByUrl['https://www.chess.com/game/live/legacy-timeout']->lossReason,
        'Old timeouts must migrate directly.',
    );
    $assertSame(
        LossReason::Abandoned,
        $historyByUrl['https://www.chess.com/game/live/legacy-abandoned']->lossReason,
        'Old abandoned games must migrate directly.',
    );

    $repository->storeArchive(
        url: 'https://api.chess.com/pub/player/player/games/2023/11',
        etag: null,
        lastModified: null,
        games: [$pendingLoss, $variantLoss],
    );
    $assertSame(
        LossReason::Unknown,
        $repository->pendingLossAnalysis('Player')?->lossReason,
        'A standard board loss must be available for Stockfish.',
    );
    $assertSame(
        true,
        $repository->completeLossAnalysis(
            'Player',
            'https://www.chess.com/game/live/legacy',
            LossReason::Blunder,
        ),
        'A Stockfish blunder classification must be persisted.',
    );
    $assertSame(
        false,
        $repository->completeLossAnalysis('Player', $pendingLoss->url, LossReason::TooSlow),
        'Engine analysis must not overwrite documented terminal categories.',
    );
    $assertSame(
        true,
        $repository->completeLossAnalysis('Player', $pendingLoss->url, LossReason::Outplayed),
        'A Stockfish outplayed classification must be persisted.',
    );
    $assertSame(null, $repository->pendingLossAnalysis('Player'), 'Completed and unsupported losses must leave the queue.');

    $repository = new GameRepository($databasePath);
    $dashboard = $repository->dashboard('Player');
    $assertSame(5, $dashboard->summary->losses, 'Every loss category must remain in the total.');
    $lossReasonStatistics = [];
    foreach ($dashboard->lossReasons as $lossReasonStatistic) {
        $lossReasonStatistics[$lossReasonStatistic->reason->value] = $lossReasonStatistic;
    }
    $assertSame(array_keys($lossReasonLabels), array_keys($lossReasonStatistics), 'Exactly five categories must exist.');
    foreach ($lossReasonStatistics as $lossReasonStatistic) {
        $assertSame(1, $lossReasonStatistic->games, 'Every fixture category must be aggregated once.');
        $assertSame(20.0, $lossReasonStatistic->percentage, 'Percentages must use all losses.');
    }

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
    $quoteSourceUrls = [
        'https://www.chesshistory.com/winter/extra/tarrasch.html',
        'https://www.chesshistory.com/winter/extra/lasker1.html',
        'https://www.chesshistory.com/winter/extra/philidor.html',
        'https://www.gutenberg.org/files/33870/33870-h/33870-h.htm',
        'https://founders.archives.gov/documents/Franklin/01-29-02-0608',
        'https://www.gutenberg.org/files/17508/17508-h/17508-h.htm',
    ];
    $assertSame(1, substr_count($historyHtml, '<blockquote>'), 'Every response must contain exactly one quote.');
    $assertSame(1, substr_count($historyHtml, '<cite>— '), 'Every quote must contain one unlinked attribution.');
    $assertSame(6, substr_count($historyHtml, 'class="quote-source"'), 'Every source must be listed once.');
    $assertContains('<details class="quote-sources">', $historyHtml, 'The source list must use native disclosure.');
    foreach ($quoteSourceUrls as $quoteSourceUrl) {
        $assertContains($quoteSourceUrl, $historyHtml, 'Every documented quote source must be linked.');
    }
    $assertContains('<th>Niederlagengrund</th>', $historyHtml, 'The history table must expose loss reasons.');
    $assertContains('Blunder', $historyHtml, 'The history table must render an engine classification.');
    $assertContains('Unbekannt', $historyHtml, 'The history table must render unknown losses explicitly.');
    $assertContains('Niederlagengründe', $statisticsHtml, 'The statistics page must aggregate loss reasons.');
    $assertContains('20,0 %', $statisticsHtml, 'The statistics table must render loss reason percentages.');
} finally {
    unset($_SERVER['USERNAME'], $_SERVER['DATABASE']);
    foreach ([$databasePath, $databasePath . '-shm', $databasePath . '-wal', $databasePath . '.lock'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

echo 'All tests passed.' . PHP_EOL;
