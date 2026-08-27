<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final readonly class Application
{
    private const IMPORT_RESULT_SESSION_KEY = 'chessmaster_import_result';

    /**
     * Anchor configuration loading to the repository root.
     */
    public function __construct(private string $projectRoot) {}

    /**
     * Import on demand and load only the data required by the requested page.
     */
    public function render(Page $page): string
    {
        $renderer = new HtmlRenderer();

        try {
            if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
                throw ChessmasterException::storage('Die Sitzung für den Importstatus konnte nicht gestartet werden.');
            }

            $config = Config::fromProjectRoot($this->projectRoot);
            $repository = new GameRepository($config->databasePath);
            $importResult = $_SESSION[self::IMPORT_RESULT_SESSION_KEY] ?? null;
            unset($_SESSION[self::IMPORT_RESULT_SESSION_KEY]);
            if (!($importResult instanceof ImportResult)) {
                $importResult = new ImportResult(addedGames: 0, downloadedArchives: 0, skippedArchives: 0);
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'sync') {
                $importResult = new GameImporter(new ChessComClient(), $repository)->import($config->username);
                $_SESSION[self::IMPORT_RESULT_SESSION_KEY] = $importResult;
                session_write_close();
                http_response_code(303);
                header(
                    'Location: ' .
                        match ($page) {
                            Page::History => '/',
                            Page::Statistics => '/statistics',
                            Page::Openings => '/openings',
                            Page::Blunders => '/blunders',
                        },
                );
                return '';
            }
            $dashboard = match ($page) {
                Page::Statistics, Page::Openings => $repository->dashboard($config->username),
                default => null,
            };
            $history = null;
            if ($page === Page::History) {
                $requestedPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $history = $repository->history($config->username, $requestedPage ?: 1, 100);
            }

            return $renderer->render($page, $config, $dashboard, $history, $importResult);
        } catch (ChessmasterException $exception) {
            http_response_code(500);
            return $renderer->renderError($exception->getMessage());
        }
    }
}
