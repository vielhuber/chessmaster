<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use vielhuber\chessmaster\ChessmasterException;
use vielhuber\chessmaster\Config;
use vielhuber\chessmaster\GameRepository;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (($_GET['action'] ?? '') !== 'random-game') {
        http_response_code(404);
        echo json_encode(['error' => 'Unbekannte Aktion.'], JSON_THROW_ON_ERROR);
        exit();
    }

    $config = Config::fromProjectRoot(dirname(__DIR__));
    $game = new GameRepository($config->databasePath)->randomTrainingGame($config->username);
    if ($game === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Noch keine Trainingspartie vorhanden.'], JSON_THROW_ON_ERROR);
        exit();
    }

    echo json_encode(
        [
            'url' => $game->url,
            'pgn' => $game->pgn,
            'playerColor' => $game->playerColor,
            'opponent' => $game->opponent
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (ChessmasterException $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    http_response_code(500);
    echo '{"error":"Die Antwort konnte nicht erzeugt werden."}';
}
