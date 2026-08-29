<?php
declare(strict_types=1);

use vielhuber\chessmaster\OpeningStatistic;
use vielhuber\chessmaster\Page;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$score = static fn(float $value): string => match ($value) {
    1.0 => '1',
    0.5 => '½',
    default => '0',
};
$resultClass = static fn(float $value): string => match ($value) {
    1.0 => 'win',
    0.5 => 'draw',
    default => 'loss',
};
$openingCard = static function (?OpeningStatistic $opening, string $fallback) use ($escape): string {
    if ($opening === null) {
        return '<strong>' . $fallback . '</strong><span>Noch keine Daten</span>';
    }

    return '<strong>' .
        $escape($opening->name) .
        '</strong><span>' .
        $opening->games .
        ' Partien · ' .
        number_format($opening->scorePercentage, 1, ',', '.') .
        ' % Score</span>';
};
$navigation = [
    Page::History->value => ['label' => 'Historie', 'url' => '/'],
    Page::Statistics->value => ['label' => 'Statistiken', 'url' => '/statistics'],
    Page::Openings->value => ['label' => 'Eröffnungen', 'url' => '/openings'],
    Page::Blunders->value => ['label' => 'Patzer', 'url' => '/blunders'],
];
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= $escape($navigation[$page->value]['label']) ?> · chessmaster</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <?php if ($page === Page::Statistics): ?><script src="/assets/chart.umd.min.js" defer></script><?php endif; ?>
    <?php if (
        $page === Page::Statistics ||
        $page === Page::Openings ||
        $page === Page::Blunders
    ): ?><script type="module" src="/assets/app.js"></script><?php endif; ?>
</head>
<body>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="/"><span>♞</span> chessmaster</a>
            <nav class="nav" aria-label="Hauptnavigation">
                <?php foreach ($navigation as $key => $item): ?>
                    <a class="<?= $page->value === $key ? 'active' : '' ?>" href="<?= $item['url'] ?>"><?= $item[
    'label'
] ?></a>
                <?php endforeach; ?>
            </nav>
            <form class="sync" method="post" action="<?= $navigation[$page->value]['url'] ?>">
                <?php if ($importResult->downloadedArchives + $importResult->skippedArchives > 0): ?>
                    <span class="sync-status"><?= $importResult->addedGames ?> neu</span>
                <?php endif; ?>
                <input type="hidden" name="action" value="sync">
                <button class="sync-button" type="submit">Spiele aktualisieren</button>
            </form>
        </div>
    </header>

    <main class="shell page">
        <?php if ($page === Page::History && $history !== null): ?>
            <div class="page-heading">
                <div><p class="eyebrow">Chess.com Archiv</p><h1>Historie</h1></div>
                <p><?= number_format(
                    $history->total,
                    0,
                    ',',
                    '.',
                ) ?> Spiele von <a href="https://www.chess.com/member/<?= $escape(
     strtolower($username),
 ) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($username) ?> ↗</a></p>
            </div>

            <div class="panel table-panel">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Datum</th><th>Gegner</th><th>Score</th><th>Niederlagengrund</th><th>Farbe</th>
                                <th>Rating</th><th>Tempo</th><th>Eröffnung</th><th>Partie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history->games as $game): ?>
                                <?php
                                $playedAt = new DateTimeImmutable('@' . $game->playedAt)->setTimezone($timezone);
                                $timeControl = $game->timeControl;
                                if (preg_match('/^(\d+)(?:\+(\d+))?$/', $timeControl, $matches) === 1) {
                                    $baseSeconds = (int) $matches[1];
                                    $baseMinutes = $baseSeconds / 60;
                                    $formattedBase =
                                        $baseSeconds % 60 === 0
                                            ? (string) (int) $baseMinutes
                                            : rtrim(rtrim(number_format($baseMinutes, 1, '.', ''), '0'), '.');
                                    $timeControl = $formattedBase . (isset($matches[2]) ? '+' . $matches[2] : '');
                                }
                                ?>
                                <tr>
                                    <td><?= $playedAt->format('d.m.Y H:i') ?></td>
                                    <td><strong><?= $escape(
                                        $game->opponent,
                                    ) ?></strong><small><?= $game->opponentRating ?? '–' ?></small></td>
                                    <td><span class="result <?= $resultClass($game->score) ?>"><?= $score(
    $game->score,
) ?></span></td>
                                    <td><?= $game->lossReason !== null
                                        ? $escape($game->lossReason->label())
                                        : ($game->score === 0.0
                                            ? 'Nicht ermittelbar'
                                            : '–') ?></td>
                                    <td><?= $game->playerColor === 'white' ? 'Weiß' : 'Schwarz' ?></td>
                                    <td><?= $game->playerRating ?? '–' ?></td>
                                    <td><?= $escape(ucfirst($game->timeClass)) ?><small><?= $escape(
    $timeControl,
) ?></small></td>
                                    <td><?= $escape($game->openingName) ?></td>
                                    <td><a class="game-link" href="<?= $escape(
                                        $game->url,
                                    ) ?>" target="_blank" rel="noopener noreferrer" aria-label="Partie auf Chess.com öffnen" title="Partie auf Chess.com öffnen">↗</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($history->pageCount > 1): ?>
                <nav class="pagination" aria-label="Seitennavigation">
                    <?php if ($history->page > 1): ?><a href="?page=<?= $history->page -
    1 ?>">← Neuer</a><?php endif; ?>
                    <span><?= $history->page ?> / <?= $history->pageCount ?></span>
                    <?php if ($history->page < $history->pageCount): ?><a href="?page=<?= $history->page +
    1 ?>">Älter →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($page === Page::Statistics && $dashboard !== null): ?>
            <div class="page-heading">
                <div><p class="eyebrow">Auswertung</p><h1>Statistiken</h1></div>
                <p><?= number_format($dashboard->summary->scorePercentage, 1, ',', '.') ?> % Score über alle Partien</p>
            </div>

            <div class="metrics">
                <article><span>Partien</span><strong><?= number_format(
                    $dashboard->summary->games,
                    0,
                    ',',
                    '.',
                ) ?></strong></article>
                <article><span>Siege</span><strong><?= number_format(
                    $dashboard->summary->wins,
                    0,
                    ',',
                    '.',
                ) ?></strong></article>
                <article><span>Remis</span><strong><?= number_format(
                    $dashboard->summary->draws,
                    0,
                    ',',
                    '.',
                ) ?></strong></article>
                <article><span>Niederlagen</span><strong><?= number_format(
                    $dashboard->summary->losses,
                    0,
                    ',',
                    '.',
                ) ?></strong></article>
                <article><span>Ø Genauigkeit</span><strong><?= $dashboard->summary->averageAccuracy === null
                    ? '–'
                    : number_format($dashboard->summary->averageAccuracy, 1, ',', '.') . ' %' ?></strong></article>
            </div>

            <div class="charts">
                <article class="panel chart-small"><div class="panel-title"><h2>Ergebnisse</h2><span>Gesamt</span></div><div class="chart-wrap"><canvas id="result-chart"></canvas></div></article>
                <article class="panel chart-large"><div class="panel-title"><h2>Zeitformate</h2><span>Partien</span></div><div class="chart-wrap"><canvas id="time-class-chart"></canvas></div></article>
                <article class="panel chart-wide"><div class="panel-title"><h2>Formkurve</h2><span>Score der letzten 24 Monate</span></div><div class="chart-wrap"><canvas id="month-chart"></canvas></div></article>
            </div>

            <div class="ratings">
                <?php foreach ($dashboard->timeClasses as $timeClass): ?>
                    <article><span><?= $escape(
                        ucfirst($timeClass->timeClass),
                    ) ?></span><strong><?= $timeClass->latestRating ?? '–' ?></strong><small><?= number_format(
    $timeClass->scorePercentage,
    1,
    ',',
    '.',
) ?> % · <?= $timeClass->games ?> Spiele</small></article>
                <?php endforeach; ?>
            </div>

            <?php if ($dashboard->summary->losses > 0): ?>
                <div class="panel table-panel statistic-table">
                    <div class="panel-title">
                        <h2>Niederlagengründe</h2><span><?= $dashboard->summary->losses ?> Niederlagen</span>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>Grund</th><th>Partien</th><th>Anteil</th></tr></thead>
                            <tbody>
                                <?php foreach ($dashboard->lossReasons as $lossReason): ?>
                                    <tr>
                                        <td><?= $lossReason->reason === null
                                            ? 'Nicht ermittelbar'
                                            : $escape($lossReason->reason->label()) ?></td>
                                        <td><?= $lossReason->games ?></td>
                                        <td><?= number_format($lossReason->percentage, 1, ',', '.') ?> %</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <script id="dashboard-data" type="application/json"><?= $chartData ?></script>
        <?php endif; ?>

        <?php if ($page === Page::Openings && $dashboard !== null): ?>
            <div class="page-heading">
                <div><p class="eyebrow">ECO-Auswertung</p><h1>Eröffnungen</h1></div>
                <p><?= count($dashboard->openings) ?> gespielte Familien</p>
            </div>

            <?php if ($dashboard->openingExamples !== []): ?>
                <section class="opening-examples" aria-label="Die drei erfolgreichsten Eröffnungen">
                    <?php foreach ($dashboard->openingExamples as $exampleIndex => $openingExample): ?>
                        <article class="opening-example panel" data-opening-example="<?= $exampleIndex ?>">
                            <div class="opening-example-board">
                                <div class="board" data-opening-board aria-label="Beispiel für <?= $escape(
                                    $openingExample->familyName,
                                ) ?>"></div>
                            </div>
                            <div class="opening-example-copy">
                                <p class="eyebrow">Platz <?= $exampleIndex + 1 ?></p>
                                <h2><?= $escape($openingExample->familyName) ?></h2>
                                <p class="opening-example-score"><?= number_format(
                                    $openingExample->scorePercentage,
                                    1,
                                    ',',
                                    '.',
                                ) ?> % Score · <?= $openingExample->games ?> Partien</p>
                                <p><?= $escape(
                                    $openingExample->variationName,
                                ) ?> als realer Beispielweg über vier Zugpaare.</p>
                                <div class="opening-example-moves" data-opening-moves></div>
                                <p class="opening-example-status" data-opening-status aria-live="polite">Ausgangsstellung</p>
                                <div class="opening-example-controls">
                                    <button type="button" data-opening-previous aria-label="Vorheriger Zug">←</button>
                                    <button type="button" data-opening-play>Abspielen</button>
                                    <button type="button" data-opening-next aria-label="Nächster Zug">→</button>
                                </div>
                                <a class="game-source" href="<?= $escape(
                                    $openingExample->gameUrl,
                                ) ?>" target="_blank" rel="noopener noreferrer">Beispielpartie ↗</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                <script id="opening-examples-data" type="application/json"><?= $openingExamplesData ?></script>
            <?php endif; ?>

            <div class="opening-highlights single">
                <article class="opening-worst"><span>Schlechteste Eröffnung</span><?= $openingCard(
                    $dashboard->worstOpening,
                    'Noch offen',
                ) ?></article>
            </div>

            <div class="panel table-panel">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Eröffnung</th><th>Partien</th><th>+ / = / −</th><th>Score</th></tr></thead>
                        <tbody>
                            <?php foreach ($dashboard->openings as $opening): ?>
                                <tr>
                                    <td>
                                        <?php if ($opening->url !== null): ?>
                                            <a href="<?= $escape(
                                                $opening->url,
                                            ) ?>" target="_blank" rel="noopener noreferrer"><?= $escape(
    $opening->name,
) ?> ↗</a>
                                        <?php else: ?>
                                            <?= $escape($opening->name) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $opening->games ?></td>
                                    <td><?= $opening->wins ?> / <?= $opening->draws ?> / <?= $opening->losses ?></td>
                                    <td><span class="bar"><i style="width: <?= min(
                                        100,
                                        max(0, $opening->scorePercentage),
                                    ) ?>%"></i></span><?= number_format(
    $opening->scorePercentage,
    1,
    ',',
    '.',
) ?> %</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($page === Page::Blunders): ?>
            <div class="page-heading">
                <div><p class="eyebrow">Stockfish-Training</p><h1>Patzer</h1></div>
            </div>

            <div class="trainer panel">
                <div class="trainer-board-wrap">
                    <div class="evaluation-bar" id="evaluation-bar" aria-live="polite" hidden>
                        <i id="evaluation-white"></i>
                        <span id="evaluation-score">0,0</span>
                    </div>
                    <div class="board-stage">
                        <div class="board" id="chessboard" aria-label="Interaktives Schachbrett"></div>
                        <div class="board-placeholder" id="board-placeholder"><span>♞</span><p>Analyse startet …</p></div>
                    </div>
                    <div class="board-navigation" id="board-navigation" hidden>
                        <button id="board-previous" type="button" aria-label="Vorheriger Zug">←</button>
                        <span id="board-position">Trainingsstellung</span>
                        <button id="board-next" type="button" aria-label="Nächster Zug">→</button>
                    </div>
                </div>
                <div class="trainer-copy">
                    <p class="eyebrow">Zufällige Partie</p>
                    <h2 id="trainer-title">Finde einen besseren Zug</h2>
                    <p id="trainer-description">Stockfish sucht den größten Fehler in einer zufälligen Partie. Ziehe anschließend per Klick auf Start- und Zielfeld.</p>
                    <div class="analysis-progress" id="analysis-progress" hidden><i id="analysis-progress-bar"></i></div>
                    <p class="trainer-feedback" id="trainer-feedback" aria-live="polite"></p>
                    <a class="game-source" id="trainer-source" href="#" target="_blank" rel="noopener noreferrer" hidden>Partie auf Chess.com ↗</a>
                    <button class="button" id="new-blunder" type="button">Patzer suchen</button>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
