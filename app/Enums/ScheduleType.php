<?php

namespace App\Enums;

enum ScheduleType: string
{
    case NOW = 'now';
    case ONE_TIME = 'one_time';
    case RECURRING = 'recurring';
}
