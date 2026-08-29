<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use vielhuber\chessmaster\ChessmasterException;
use vielhuber\chessmaster\Config;
use vielhuber\chessmaster\GameRepository;
use vielhuber\chessmaster\LossReason;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $action = (string) ($_GET['action'] ?? '');
    if (!in_array($action, ['random-game', 'loss-analysis'], true)) {
        http_response_code(404);
        echo json_encode(['error' => 'Unbekannte Aktion.'], JSON_THROW_ON_ERROR);
        exit();
    }

    $config = Config::fromProjectRoot(dirname(__DIR__));
    $repository = new GameRepository($config->databasePath);
    if ($action === 'random-game') {
        $game = $repository->randomTrainingGame($config->username);
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
                'opponent' => $game->opponent,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        exit();
    }

    $requestMethod = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($requestMethod === 'GET') {
        $game = $repository->pendingLossAnalysis($config->username);
        echo json_encode(
            $game === null
                ? ['game' => null]
                : [
                    'game' => [
                        'url' => $game->url,
                        'pgn' => $game->pgn,
                        'playerColor' => $game->playerColor,
                        'opponent' => $game->opponent,
                    ],
                ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        exit();
    }
    if ($requestMethod !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['error' => 'Methode nicht erlaubt.'], JSON_THROW_ON_ERROR);
        exit();
    }

    $requestBody = file_get_contents('php://input');
    try {
        $payload = json_decode(is_string($requestBody) ? $requestBody : '', true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Analysedaten.'], JSON_THROW_ON_ERROR);
        exit();
    }
    $reason = is_array($payload) ? LossReason::tryFrom((string) ($payload['reason'] ?? '')) : null;
    if (
        $reason === null ||
        !in_array($reason, [LossReason::Blunder, LossReason::Outplayed, LossReason::Unknown], true) ||
        !is_string($payload['url'] ?? null)
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiger Niederlagengrund.'], JSON_THROW_ON_ERROR);
        exit();
    }

    if (!$repository->completeLossAnalysis($config->username, $payload['url'], $reason)) {
        http_response_code(404);
        echo json_encode(['error' => 'Die Niederlage ist nicht mehr zur Analyse vorgemerkt.'], JSON_THROW_ON_ERROR);
        exit();
    }

    echo json_encode(['updated' => true], JSON_THROW_ON_ERROR);
} catch (ChessmasterException $exception) {
    http_response_code(500);
    echo json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    http_response_code(500);
    echo '{"error":"Die Antwort konnte nicht erzeugt werden."}';
}
