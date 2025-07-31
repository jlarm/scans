<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScheduleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'description',
        'urls',
        'ip_addresses',
        'send_notification',
        'notification_email',
        'schedule_type',
        'scheduled_at',
        'cron_expression',
        'frequency',
        'day_of_week',
        'schedule_time',
        'status',
        'risk_grade',
        'summary',
        'started_at',
        'completed_at',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'urls' => 'array',
            'ip_addresses' => 'array',
            'send_notification' => 'bool',
            'scheduled_at' => 'datetime',
            'schedule_time' => 'datetime:H:i',
            'day_of_week' => 'int',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
