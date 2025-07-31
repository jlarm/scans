<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScanResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'scan_id',
        'target',
        'target_type',
        'check_type',
        'check_name',
        'passed',
        'severity',
        'risk_level',
        'message',
        'description',
        'check_data',
        'recommendations',
        'vulnerabilities',
        'scanned_at',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    // Scope for filtering by severity
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    // Scope for filtering by check type
    public function scopeByCheckType($query, string $checkType)
    {
        return $query->where('check_type', $checkType);
    }

    // Scope for failed checks
    public function scopeFailed($query)
    {
        return $query->where('passed', false);
    }

    // Scope for passed checks
    public function scopePassed($query)
    {
        return $query->where('passed', true);
    }

    // Scope for high risk items
    public function scopeHighRisk($query)
    {
        return $query->whereIn('severity', ['high', 'critical'])
            ->orWhere('risk_level', 'high');
    }

    // Scope for vulnerabilities
    public function scopeWithVulnerabilities($query)
    {
        return $query->whereNotNull('vulnerabilities')
            ->whereJsonLength('vulnerabilities', '>', 0);
    }

    // Helper method to check if result has vulnerabilities
    public function hasVulnerabilities(): bool
    {
        return ! empty($this->vulnerabilities);
    }

    // Helper method to get vulnerability count
    public function getVulnerabilityCount(): int
    {
        return is_array($this->vulnerabilities) ? count($this->vulnerabilities) : 0;
    }

    // Helper method to check if result is high risk
    public function isHighRisk(): bool
    {
        return in_array($this->severity, ['high', 'critical']) ||
               $this->risk_level === 'high' ||
               $this->hasVulnerabilities();
    }

    // Helper method to get formatted risk level
    public function getRiskLevel(): string
    {
        if ($this->severity) {
            return $this->severity;
        }

        if ($this->risk_level) {
            return $this->risk_level;
        }

        if ($this->hasVulnerabilities()) {
            return 'high';
        }

        return $this->passed ? 'low' : 'medium';
    }

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'passed' => 'bool',
            'check_data' => 'array',
            'recommendations' => 'array',
            'vulnerabilities' => 'array',
            'scanned_at' => 'datetime',
        ];
    }
}
