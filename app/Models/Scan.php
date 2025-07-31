<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    use HasFactory;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'send_notification' => 'bool',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
            'status' => ScanStatus::class,
            'schedule_type' => ScheduleType::class,
        ];
    }
}
