<?php

namespace App\Enums;

enum RiskLevel: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#10b981',
            self::MEDIUM => '#f59e0b',
            self::HIGH => '#ef4444',
            self::CRITICAL => '#991b1b',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::MEDIUM => 'Medium',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
        };
    }
}
