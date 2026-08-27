<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

final class GameImporter
{
    /**
     * Compose remote retrieval and local persistence without global state.
     */
    public function __construct(
        private readonly ChessComGateway $gateway,
        private readonly GameRepository $repository
    ) {}

    /**
     * Resume whole-month imports and revisit only the active month.
     */
    public function import(string $username): ImportResult
    {
        $lock = fopen($this->repository->lockPath(), 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw ChessmasterException::storage('Der Import konnte nicht gesperrt werden.');
        }

        try {
            $archiveUrls = $this->gateway->fetchArchiveUrls($username);
            $latestArchiveUrl = $archiveUrls === [] ? null : $archiveUrls[array_key_last($archiveUrls)];
            $addedGames = 0;
            $downloadedArchives = 0;
            $skippedArchives = 0;

            foreach ($archiveUrls as $archiveUrl) {
                $cache = $this->repository->archive($archiveUrl);
                if ($cache !== null && $archiveUrl !== $latestArchiveUrl) {
                    $skippedArchives++;
                    continue;
                }

                $response = $this->gateway->fetchArchive(
                    url: $archiveUrl,
                    etag: $cache?->etag,
                    lastModified: $cache?->lastModified
                );
                if ($response->notModified) {
                    $skippedArchives++;
                    continue;
                }

                $games = array_map(static fn(array $game): Game => Game::fromApi($game, $username), $response->games);
                $addedGames += $this->repository->storeArchive(
                    url: $archiveUrl,
                    etag: $response->etag,
                    lastModified: $response->lastModified,
                    games: $games
                );
                $downloadedArchives++;
            }

            return new ImportResult(
                addedGames: $addedGames,
                downloadedArchives: $downloadedArchives,
                skippedArchives: $skippedArchives
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
