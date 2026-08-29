<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use PDO;
use PDOException;
use PDOStatement;

final class GameRepository
{
    private const MINIMUM_OPENING_SAMPLE = 50;

    private PDO $database;

    /**
     * Open the local store and initialize its application-owned schema.
     */
    public function __construct(private readonly string $databasePath)
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw ChessmasterException::storage('Das Datenverzeichnis konnte nicht angelegt werden.');
        }

        try {
            $this->database = new PDO(
                'sqlite:' . $databasePath,
                options: [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            );
            $this->database->exec('PRAGMA journal_mode = WAL');
            $this->database->exec('PRAGMA busy_timeout = 5000');
            $this->migrate();
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Die SQLite-Datenbank konnte nicht geöffnet werden.', $exception);
        }
    }

    /**
     * Locate validators proving that a historical month was already processed.
     */
    public function archive(string $url): ?ArchiveCache
    {
        try {
            $statement = $this->database->prepare('SELECT url, etag, last_modified FROM archives WHERE url = :url');
            $statement->execute(['url' => $url]);
            $row = $statement->fetch();
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Der Archivstatus konnte nicht gelesen werden.', $exception);
        }

        if (!is_array($row)) {
            return null;
        }

        return new ArchiveCache(
            url: (string) $row['url'],
            etag: $row['etag'] !== null ? (string) $row['etag'] : null,
            lastModified: $row['last_modified'] !== null ? (string) $row['last_modified'] : null,
        );
    }

    /**
     * Commit one complete month so interrupted imports can resume safely.
     *
     * @param list<Game> $games
     */
    public function storeArchive(string $url, ?string $etag, ?string $lastModified, array $games): int
    {
        try {
            $this->database->beginTransaction();
            $gameStatement = $this->database->prepare(
                'INSERT OR IGNORE INTO games (
                    url, player_username, opponent, player_color, player_rating, opponent_rating,
                    player_result, opponent_result, loss_reason, score, played_at, time_class, time_control, rules,
                    rated, player_accuracy, opponent_accuracy, opening_name, opening_url, pgn, raw_json
                ) VALUES (
                    :url, :player_username, :opponent, :player_color, :player_rating, :opponent_rating,
                    :player_result, :opponent_result, :loss_reason, :score, :played_at,
                    :time_class, :time_control, :rules,
                    :rated, :player_accuracy, :opponent_accuracy, :opening_name, :opening_url, :pgn, :raw_json
                )',
            );

            $addedGames = 0;
            foreach ($games as $game) {
                $gameStatement->execute([
                    'url' => $game->url,
                    'player_username' => strtolower($game->playerUsername),
                    'opponent' => $game->opponent,
                    'player_color' => $game->playerColor,
                    'player_rating' => $game->playerRating,
                    'opponent_rating' => $game->opponentRating,
                    'player_result' => $game->playerResult,
                    'opponent_result' => $game->opponentResult,
                    'loss_reason' => $game->lossReason?->value,
                    'score' => $game->score,
                    'played_at' => $game->playedAt,
                    'time_class' => $game->timeClass,
                    'time_control' => $game->timeControl,
                    'rules' => $game->rules,
                    'rated' => $game->rated ? 1 : 0,
                    'player_accuracy' => $game->playerAccuracy,
                    'opponent_accuracy' => $game->opponentAccuracy,
                    'opening_name' => $game->openingName,
                    'opening_url' => $game->openingUrl,
                    'pgn' => $game->pgn,
                    'raw_json' => $game->rawJson,
                ]);
                $addedGames += $gameStatement->rowCount();
            }

            $archiveStatement = $this->database->prepare(
                'INSERT INTO archives (url, etag, last_modified, imported_at)
                 VALUES (:url, :etag, :last_modified, :imported_at)
                 ON CONFLICT(url) DO UPDATE SET
                    etag = excluded.etag,
                    last_modified = excluded.last_modified,
                    imported_at = excluded.imported_at',
            );
            $archiveStatement->execute([
                'url' => $url,
                'etag' => $etag,
                'last_modified' => $lastModified,
                'imported_at' => time(),
            ]);
            $this->database->commit();

            return $addedGames;
        } catch (PDOException $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw ChessmasterException::storage('Ein Spielearchiv konnte nicht gespeichert werden.', $exception);
        }
    }

    /**
     * Return one bounded slice while retaining access to the complete history.
     */
    public function history(string $username, int $page, int $perPage): HistoryPage
    {
        $username = strtolower($username);
        $page = max(1, $page);

        try {
            $countStatement = $this->database->prepare('SELECT COUNT(*) FROM games WHERE player_username = :username');
            $countStatement->execute(['username' => $username]);
            $total = (int) $countStatement->fetchColumn();
            $pageCount = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pageCount);

            $statement = $this->database->prepare(
                'SELECT * FROM games
                 WHERE player_username = :username
                 ORDER BY played_at DESC, url DESC
                 LIMIT :limit OFFSET :offset',
            );
            $statement->bindValue(':username', $username);
            $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
            $statement->execute();
            $games = array_map(static fn(array $row): Game => Game::fromDatabase($row), $statement->fetchAll());
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Die Historie konnte nicht gelesen werden.', $exception);
        }

        return new HistoryPage(games: $games, page: $page, pageCount: $pageCount, total: $total);
    }

    /**
     * Aggregate stable dashboard values directly inside SQLite.
     */
    public function dashboard(string $username): Dashboard
    {
        $username = strtolower($username);

        try {
            $summaryRow = $this->fetchOne(
                'SELECT
                    COUNT(*) AS games,
                    COALESCE(SUM(score = 1), 0) AS wins,
                    COALESCE(SUM(score = 0.5), 0) AS draws,
                    COALESCE(SUM(score = 0), 0) AS losses,
                    COALESCE(AVG(score) * 100, 0) AS score_percentage,
                    AVG(player_accuracy) AS average_accuracy
                 FROM games WHERE player_username = :username',
                $username,
            );

            $timeClassRows = $this->fetchAll(
                'SELECT
                    game.time_class,
                    COUNT(*) AS games,
                    SUM(game.score = 1) AS wins,
                    SUM(game.score = 0.5) AS draws,
                    SUM(game.score = 0) AS losses,
                    AVG(game.score) * 100 AS score_percentage,
                    (
                        SELECT recent.player_rating FROM games recent
                        WHERE recent.player_username = game.player_username
                          AND recent.time_class = game.time_class
                          AND recent.player_rating IS NOT NULL
                        ORDER BY recent.played_at DESC LIMIT 1
                    ) AS latest_rating
                 FROM games game
                 WHERE game.player_username = :username
                 GROUP BY game.time_class
                 ORDER BY games DESC',
                $username,
            );

            $monthRows = array_reverse(
                $this->fetchAll(
                    'SELECT
                    strftime(\'%Y-%m\', played_at, \'unixepoch\') AS month,
                    COUNT(*) AS games,
                    AVG(score) * 100 AS score_percentage
                 FROM games
                 WHERE player_username = :username
                 GROUP BY month
                 ORDER BY month DESC
                 LIMIT 24',
                    $username,
                ),
            );

            $lossReasonRows = $this->fetchAll(
                'SELECT loss_reason, COUNT(*) AS games
                 FROM games
                 WHERE player_username = :username AND score = 0
                 GROUP BY loss_reason
                 ORDER BY games DESC, loss_reason IS NULL, loss_reason',
                $username,
            );

            $openingRows = $this->fetchAll(
                'SELECT opening_name, score
                 FROM games
                 WHERE player_username = :username',
                $username,
            );
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Die Statistiken konnten nicht berechnet werden.', $exception);
        }

        $summary = new Summary(
            games: (int) $summaryRow['games'],
            wins: (int) $summaryRow['wins'],
            draws: (int) $summaryRow['draws'],
            losses: (int) $summaryRow['losses'],
            scorePercentage: (float) $summaryRow['score_percentage'],
            averageAccuracy: $summaryRow['average_accuracy'] !== null ? (float) $summaryRow['average_accuracy'] : null,
        );
        $timeClasses = array_map(
            static fn(array $row): TimeClassStatistic => new TimeClassStatistic(
                timeClass: (string) $row['time_class'],
                games: (int) $row['games'],
                wins: (int) $row['wins'],
                draws: (int) $row['draws'],
                losses: (int) $row['losses'],
                scorePercentage: (float) $row['score_percentage'],
                latestRating: $row['latest_rating'] !== null ? (int) $row['latest_rating'] : null,
            ),
            $timeClassRows,
        );
        $months = array_map(
            static fn(array $row): MonthlyStatistic => new MonthlyStatistic(
                month: (string) $row['month'],
                games: (int) $row['games'],
                scorePercentage: (float) $row['score_percentage'],
            ),
            $monthRows,
        );
        $lossReasons = array_map(
            static fn(array $row): LossReasonStatistic => new LossReasonStatistic(
                reason: LossReason::tryFrom((string) ($row['loss_reason'] ?? '')),
                games: (int) $row['games'],
                percentage:
                    $summary->losses === 0 ? 0.0 : ((int) $row['games'] / $summary->losses) * 100,
            ),
            $lossReasonRows,
        );
        $openingGroups = [];
        foreach ($openingRows as $openingRow) {
            $openingName = (string) $openingRow['opening_name'];
            $familyName = $openingName;

            if ($openingName !== 'Unbekannt') {
                $words = preg_split('/\s+/', $openingName) ?: [$openingName];
                $familyWords = array_slice($words, 0, 3);

                foreach ($words as $wordIndex => $word) {
                    if (
                        preg_match('/^(Defense|Opening|Game|Gambit|Attack|System)(?:\.\.\..*)?$/', $word, $matches) !==
                        1
                    ) {
                        continue;
                    }

                    $familyWords = array_slice($words, 0, $wordIndex + 1);
                    $familyWords[$wordIndex] = $matches[1];
                    break;
                }

                $familyName = implode(' ', $familyWords);
            }

            if (!isset($openingGroups[$familyName])) {
                $openingGroups[$familyName] = [
                    'games' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'points' => 0.0,
                ];
            }

            $score = (float) $openingRow['score'];
            ++$openingGroups[$familyName]['games'];
            $openingGroups[$familyName]['points'] += $score;

            if ($score === 1.0) {
                ++$openingGroups[$familyName]['wins'];
            }
            if ($score === 0.5) {
                ++$openingGroups[$familyName]['draws'];
            }
            if ($score === 0.0) {
                ++$openingGroups[$familyName]['losses'];
            }
        }

        $openings = [];
        foreach ($openingGroups as $openingName => $openingGroup) {
            $games = (int) $openingGroup['games'];
            $openings[] = new OpeningStatistic(
                name: $openingName,
                url: $openingName !== 'Unbekannt'
                    ? 'https://www.chess.com/openings/' . str_replace(' ', '-', $openingName)
                    : null,
                games: $games,
                wins: (int) $openingGroup['wins'],
                draws: (int) $openingGroup['draws'],
                losses: (int) $openingGroup['losses'],
                scorePercentage: ((float) $openingGroup['points'] / $games) * 100,
            );
        }
        usort(
            $openings,
            static fn(OpeningStatistic $first, OpeningStatistic $second): int => $second->games <=> $first->games ?:
            $first->name <=> $second->name,
        );

        $rankedOpenings = array_values(
            array_filter(
                $openings,
                static fn(OpeningStatistic $opening): bool => $opening->name !== 'Unbekannt' &&
                    $opening->games >= self::MINIMUM_OPENING_SAMPLE,
            ),
        );
        usort($rankedOpenings, static function (OpeningStatistic $first, OpeningStatistic $second): int {
            $scoreOrder = $second->scorePercentage <=> $first->scorePercentage;
            return $scoreOrder !== 0 ? $scoreOrder : $second->games <=> $first->games;
        });
        $bestOpening = $rankedOpenings[0] ?? null;
        $worstOpening = $rankedOpenings === [] ? null : $rankedOpenings[array_key_last($rankedOpenings)];
        $openingExamples = [];
        try {
            $exampleStatement = $this->database->prepare(
                <<<'SQL'
                SELECT game.opening_name, game.pgn, game.player_color, game.url
                FROM games game
                WHERE game.player_username = :username
                  AND game.rules = 'chess'
                  AND game.pgn <> ''
                  AND (game.opening_name = :family_name OR game.opening_name LIKE :family_prefix ESCAPE '\')
                ORDER BY (
                    SELECT COUNT(*)
                    FROM games variation
                    WHERE variation.player_username = game.player_username
                      AND variation.opening_name = game.opening_name
                ) DESC, game.played_at DESC
                LIMIT 1
                SQL
                ,
            );

            foreach (array_slice($rankedOpenings, 0, 3) as $rankedOpening) {
                $familyPrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $rankedOpening->name) . '%';
                $exampleStatement->execute([
                    'username' => $username,
                    'family_name' => $rankedOpening->name,
                    'family_prefix' => $familyPrefix,
                ]);
                $exampleRow = $exampleStatement->fetch();
                if (!is_array($exampleRow)) {
                    continue;
                }

                $openingExamples[] = new OpeningExample(
                    familyName: $rankedOpening->name,
                    variationName: (string) $exampleRow['opening_name'],
                    pgn: (string) $exampleRow['pgn'],
                    playerColor: (string) $exampleRow['player_color'],
                    gameUrl: (string) $exampleRow['url'],
                    games: $rankedOpening->games,
                    scorePercentage: $rankedOpening->scorePercentage,
                );
            }
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Die Eröffnungsbeispiele konnten nicht geladen werden.', $exception);
        }

        return new Dashboard(
            summary: $summary,
            timeClasses: $timeClasses,
            months: $months,
            lossReasons: $lossReasons,
            openings: $openings,
            bestOpening: $bestOpening,
            worstOpening: $worstOpening,
            openingExamples: $openingExamples,
        );
    }

    /**
     * Supply a standard game without loading every PGN into the page.
     */
    public function randomTrainingGame(string $username): ?Game
    {
        try {
            $statement = $this->database->prepare(
                'SELECT * FROM games
                 WHERE player_username = :username AND rules = \'chess\' AND pgn <> \'\'
                 ORDER BY RANDOM()
                 LIMIT 1',
            );
            $statement->execute(['username' => strtolower($username)]);
            $row = $statement->fetch();
        } catch (PDOException $exception) {
            throw ChessmasterException::storage('Eine Trainingspartie konnte nicht gewählt werden.', $exception);
        }

        return is_array($row) ? Game::fromDatabase($row) : null;
    }

    /**
     * Coordinate concurrent web imports through a file beside the database.
     */
    public function lockPath(): string
    {
        return $this->databasePath . '.lock';
    }

    /**
     * Create only application-owned tables and indexes.
     */
    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS games (
                url TEXT PRIMARY KEY,
                player_username TEXT NOT NULL,
                opponent TEXT NOT NULL,
                player_color TEXT NOT NULL,
                player_rating INTEGER,
                opponent_rating INTEGER,
                player_result TEXT NOT NULL,
                opponent_result TEXT NOT NULL,
                loss_reason TEXT,
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
            );
            CREATE INDEX IF NOT EXISTS games_player_date ON games (player_username, played_at DESC);
            CREATE INDEX IF NOT EXISTS games_player_opening ON games (player_username, opening_name);
            CREATE TABLE IF NOT EXISTS archives (
                url TEXT PRIMARY KEY,
                etag TEXT,
                last_modified TEXT,
                imported_at INTEGER NOT NULL
            );',
        );

        $gameColumns = $this->database->query('PRAGMA table_info(games)')->fetchAll();
        if (!in_array('loss_reason', array_column($gameColumns, 'name'), true)) {
            $this->database->exec('ALTER TABLE games ADD COLUMN loss_reason TEXT');
        }

        $lossReasonValues = array_map(static fn(LossReason $reason): string => $reason->value, LossReason::cases());
        $lossReasonPlaceholders = implode(', ', array_fill(0, count($lossReasonValues), '?'));
        $backfillStatement = $this->database->prepare(
            'UPDATE games
             SET loss_reason = player_result
             WHERE score = 0 AND loss_reason IS NULL AND player_result IN (' . $lossReasonPlaceholders . ')',
        );
        $backfillStatement->execute($lossReasonValues);
    }

    /** @return array<string, mixed> */
    private function fetchOne(string $query, string $username): array
    {
        $statement = $this->statement($query, $username);
        $row = $statement->fetch();

        return is_array($row) ? $row : [];
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(string $query, string $username): array
    {
        return $this->statement($query, $username)->fetchAll();
    }

    /**
     * Bind the shared player filter consistently across aggregate queries.
     */
    private function statement(string $query, string $username): PDOStatement
    {
        $statement = $this->database->prepare($query);
        $statement->execute(['username' => $username]);

        return $statement;
    }
}
