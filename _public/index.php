<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use vielhuber\chessmaster\Application;
use vielhuber\chessmaster\Page;

echo new Application(dirname(__DIR__))->render(Page::History);
