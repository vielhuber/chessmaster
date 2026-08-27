<?php
declare(strict_types=1);

namespace vielhuber\chessmaster;

enum Page: string
{
    case History = 'history';
    case Statistics = 'statistics';
    case Openings = 'openings';
    case Blunders = 'blunders';
}
