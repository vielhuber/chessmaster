<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

use RuntimeException;
use Throwable;

final class ChessmasterException extends RuntimeException
{
    /**
     * Explain a missing or unusable application setting.
     */
    public static function configuration(string $message): self
    {
        return new self($message);
    }

    /**
     * Preserve context when the remote archive cannot be consumed.
     */
    public static function api(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    /**
     * Hide storage internals behind an actionable application error.
     */
    public static function storage(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
