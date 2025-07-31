<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduleType: string
{
    case NOW = 'now';
    case ONE_TIME = 'one_time';
    case RECURRING = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::NOW => 'Now',
            self::ONE_TIME => 'One Time',
            self::RECURRING => 'Recurring',
        };
    }
}
