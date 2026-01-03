<?php

declare(strict_types=1);

namespace App;

enum MonitorStatus: string
{
    case Up = 'up';
    case Down = 'down';
}
