<?php

declare(strict_types=1);

namespace App\Enums;

enum ScanStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
