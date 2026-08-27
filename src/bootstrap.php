<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'vielhuber\\chessmaster\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
