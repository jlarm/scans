<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleType: string
{
    case NOW = 'now';
    case ONE_TIME = 'one_time';
    case RECURRING = 'recurring';
}
