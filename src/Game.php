<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use JsonException;

final readonly class Game
{
    /**
     * Keep imported and persisted representations structurally identical.
     */
    public function __construct(
        public string $url,
        public string $playerUsername,
        public string $opponent,
        public string $playerColor,
        public ?int $playerRating,
        public ?int $opponentRating,
        public string $playerResult,
        public string $opponentResult,
        public ?LossReason $lossReason,
        public ?int $lossAnalysisVersion,
        public float $score,
        public int $playedAt,
        public string $timeClass,
        public string $timeControl,
        public string $rules,
        public bool $rated,
        public ?float $playerAccuracy,
        public ?float $opponentAccuracy,
        public string $openingName,
        public ?string $openingUrl,
        public string $pgn,
        public string $rawJson
    ) {}

    /**
     * Normalize one public API game from the configured player's perspective.
     *
     * @param array<string, mixed> $data
     */
    public static function fromApi(array $data, string $username): self
    {
        $white = $data['white'] ?? null;
        $black = $data['black'] ?? null;
        if (!is_array($white) || !is_array($black)) {
            throw ChessmasterException::api('Eine Chess.com-Partie enthält keine vollständigen Spielerdaten.');
        }

        $whiteUsername = (string) ($white['username'] ?? '');
        $blackUsername = (string) ($black['username'] ?? '');
        $isWhite = strcasecmp($whiteUsername, $username) === 0;
        $isBlack = strcasecmp($blackUsername, $username) === 0;
        if (!$isWhite && !$isBlack) {
            throw ChessmasterException::api('Eine Chess.com-Partie gehört nicht zum konfigurierten Benutzer.');
        }

        $player = $isWhite ? $white : $black;
        $opponent = $isWhite ? $black : $white;
        $playerResult = (string) ($player['result'] ?? 'unknown');
        $opponentResult = (string) ($opponent['result'] ?? 'unknown');
        $score = 0.5;
        if ($playerResult === 'win') {
            $score = 1.0;
        }
        if ($opponentResult === 'win') {
            $score = 0.0;
        }

        $accuracies = is_array($data['accuracies'] ?? null) ? $data['accuracies'] : [];
        $playerAccuracy = $accuracies[$isWhite ? 'white' : 'black'] ?? null;
        $opponentAccuracy = $accuracies[$isWhite ? 'black' : 'white'] ?? null;
        $openingUrl = is_string($data['eco'] ?? null) ? $data['eco'] : null;
        $pgn = (string) ($data['pgn'] ?? '');
        $rules = (string) ($data['rules'] ?? 'chess');
        if ($openingUrl === null && preg_match('/\[ECOUrl "([^"]+)"\]/', $pgn, $matches) === 1) {
            $openingUrl = $matches[1];
        }

        $openingName = 'Unbekannt';
        if ($openingUrl !== null) {
            $path = parse_url($openingUrl, PHP_URL_PATH);
            if (is_string($path) && basename($path) !== '') {
                $openingName = str_replace(['-', '_'], ' ', rawurldecode(basename($path)));
            }
        }

        try {
            $rawJson = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw ChessmasterException::api('Eine Chess.com-Partie konnte nicht gespeichert werden.', $exception);
        }

        $url = (string) ($data['url'] ?? '');
        if ($url === '') {
            throw ChessmasterException::api('Eine Chess.com-Partie enthält keine URL.');
        }

        $lossReason = null;
        $lossAnalysisVersion = null;
        if ($score === 0.0) {
            $lossReason = match ($playerResult) {
                'timeout' => LossReason::TooSlow,
                'abandoned' => LossReason::Abandoned,
                default => LossReason::Unknown,
            };
            if ($lossReason !== LossReason::Unknown || $rules !== 'chess' || $pgn === '') {
                $lossAnalysisVersion = LossReason::ANALYSIS_VERSION;
            }
        }

        return new self(
            url: $url,
            playerUsername: $username,
            opponent: (string) ($opponent['username'] ?? 'Unbekannt'),
            playerColor: $isWhite ? 'white' : 'black',
            playerRating: isset($player['rating']) ? (int) $player['rating'] : null,
            opponentRating: isset($opponent['rating']) ? (int) $opponent['rating'] : null,
            playerResult: $playerResult,
            opponentResult: $opponentResult,
            lossReason: $lossReason,
            lossAnalysisVersion: $lossAnalysisVersion,
            score: $score,
            playedAt: (int) ($data['end_time'] ?? 0),
            timeClass: (string) ($data['time_class'] ?? 'unknown'),
            timeControl: (string) ($data['time_control'] ?? ''),
            rules: $rules,
            rated: (bool) ($data['rated'] ?? false),
            playerAccuracy: is_numeric($playerAccuracy) ? (float) $playerAccuracy : null,
            opponentAccuracy: is_numeric($opponentAccuracy) ? (float) $opponentAccuracy : null,
            openingName: $openingName,
            openingUrl: $openingUrl,
            pgn: $pgn,
            rawJson: $rawJson
        );
    }

    /**
     * Restore typed game data without decoding the archived JSON payload.
     *
     * @param array<string, mixed> $row
     */
    public static function fromDatabase(array $row): self
    {
        return new self(
            url: (string) $row['url'],
            playerUsername: (string) $row['player_username'],
            opponent: (string) $row['opponent'],
            playerColor: (string) $row['player_color'],
            playerRating: $row['player_rating'] !== null ? (int) $row['player_rating'] : null,
            opponentRating: $row['opponent_rating'] !== null ? (int) $row['opponent_rating'] : null,
            playerResult: (string) $row['player_result'],
            opponentResult: (string) $row['opponent_result'],
            lossReason:
                LossReason::tryFrom((string) ($row['loss_reason'] ?? '')) ??
                ((float) $row['score'] === 0.0 ? LossReason::Unknown : null),
            lossAnalysisVersion:
                ($row['loss_analysis_version'] ?? null) !== null ? (int) $row['loss_analysis_version'] : null,
            score: (float) $row['score'],
            playedAt: (int) $row['played_at'],
            timeClass: (string) $row['time_class'],
            timeControl: (string) $row['time_control'],
            rules: (string) $row['rules'],
            rated: (bool) $row['rated'],
            playerAccuracy: $row['player_accuracy'] !== null ? (float) $row['player_accuracy'] : null,
            opponentAccuracy: $row['opponent_accuracy'] !== null ? (float) $row['opponent_accuracy'] : null,
            openingName: (string) $row['opening_name'],
            openingUrl: $row['opening_url'] !== null ? (string) $row['opening_url'] : null,
            pgn: (string) ($row['pgn'] ?? ''),
            rawJson: (string) ($row['raw_json'] ?? '')
        );
    }
}
