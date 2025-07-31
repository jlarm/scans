<?php

declare(strict_types=1);

use App\Contracts\ScannerServiceInterface;
use App\Enums\ScanStatus;
use App\Jobs\ProcessScheduledScans;
use App\Models\Company;
use App\Models\Scan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
});

test('processes immediate scans that are due', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andReturn([
            'target' => 'https://example.com',
            'type' => 'url',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                [
                    'type' => 'security_header',
                    'name' => 'X-Frame-Options',
                    'passed' => true,
                ],
            ],
        ]);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
    expect($scan->started_at)->not()->toBeNull();
    expect($scan->completed_at)->not()->toBeNull();
    expect($scan->summary)->not()->toBeNull();
    expect($scan->risk_grade)->not()->toBeNull();

    // Check that scan results were stored
    expect($scan->results)->toHaveCount(1);
    $result = $scan->results->first();
    expect($result->target)->toBe('https://example.com');
    expect($result->target_type)->toBe('url');
    expect($result->check_type)->toBe('security_header');
});

test('processes one-time scans that are due', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'once',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'ip_addresses' => ['192.168.1.1'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('192.168.1.1', 'ip')
        ->once()
        ->andReturn([
            'target' => '192.168.1.1',
            'type' => 'ip',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                [
                    'type' => 'port_scan',
                    'port' => 80,
                    'status' => 'open',
                    'passed' => true,
                ],
            ],
        ]);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
});

test('processes recurring scans with valid cron expression', function () {
    // Create a scan that should run daily at the current time
    $currentTime = now();
    $cronExpression = sprintf('%d %d * * *', $currentTime->minute, $currentTime->hour);

    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'recurring',
        'cron_expression' => $cronExpression,
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
        'completed_at' => now()->subDay(), // Last run was yesterday
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andReturn([
            'target' => 'https://example.com',
            'type' => 'url',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                [
                    'type' => 'security_header',
                    'name' => 'X-Frame-Options',
                    'passed' => true,
                ],
            ],
        ]);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
});

test('skips scans that are not due yet', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'once',
        'scheduled_at' => now()->addHours(2),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldNotReceive('scan');

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::PENDING->value);
    expect($scan->started_at)->toBeNull();
});

test('skips already completed scans', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::COMPLETED->value,
        'urls' => ['https://example.com'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldNotReceive('scan');

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
});

test('handles scanner service exceptions gracefully', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andThrow(new Exception('Scanner service error'));

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::FAILED->value);
    expect($scan->completed_at)->not()->toBeNull();
    expect($scan->summary)->toHaveKey('error');
});

test('processes scans with both urls and ip addresses', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
        'ip_addresses' => ['192.168.1.1'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andReturn([
            'target' => 'https://example.com',
            'type' => 'url',
            'checks' => [['type' => 'test', 'passed' => true]],
        ]);

    $scannerService->shouldReceive('scan')
        ->with('192.168.1.1', 'ip')
        ->once()
        ->andReturn([
            'target' => '192.168.1.1',
            'type' => 'ip',
            'checks' => [['type' => 'test', 'passed' => false]],
        ]);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
    expect($scan->summary['total_targets'])->toBe(2);
});

test('calculates risk grade correctly', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
    ]);

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andReturn([
            'target' => 'https://example.com',
            'type' => 'url',
            'checks' => [
                [
                    'type' => 'security_header',
                    'passed' => false,
                    'severity' => 'high',
                ],
                [
                    'type' => 'vulnerability',
                    'passed' => false,
                    'vulnerabilities' => [
                        ['severity' => 'critical', 'cve' => 'CVE-2021-44228'],
                    ],
                ],
            ],
        ]);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();
    expect($scan->risk_grade)->toBe('F'); // Should be F due to high risk issues
});

test('artisan command dispatches job', function () {
    Queue::fake();

    $this->artisan('scans:process')
        ->expectsOutput('Processing scheduled scans...')
        ->expectsOutput('Scheduled scans job has been dispatched.')
        ->assertExitCode(0);

    Queue::assertPushed(ProcessScheduledScans::class);
});

test('stores detailed scan results correctly', function () {
    $scan = Scan::factory()->create([
        'company_id' => $this->company->id,
        'schedule_type' => 'immediate',
        'scheduled_at' => now()->subMinutes(5),
        'status' => ScanStatus::PENDING->value,
        'urls' => ['https://example.com'],
    ]);

    $mockResults = [
        'target' => 'https://example.com',
        'type' => 'url',
        'timestamp' => now()->toIso8601String(),
        'checks' => [
            [
                'type' => 'security_header',
                'name' => 'X-Frame-Options',
                'value' => null,
                'expected' => 'SAMEORIGIN',
                'passed' => false,
                'severity' => 'medium',
                'recommendations' => ['Add X-Frame-Options header'],
            ],
            [
                'type' => 'ssl_certificate',
                'name' => 'Certificate Validity',
                'valid_from' => '2023-01-01',
                'valid_to' => '2024-12-31',
                'passed' => true,
            ],
            [
                'type' => 'service_detection',
                'port' => 443,
                'service' => 'HTTPS',
                'version' => '2.4.41',
                'passed' => true,
                'vulnerabilities' => [
                    [
                        'cve' => 'CVE-2021-44228',
                        'severity' => 'critical',
                        'description' => 'Apache Log4j2 remote code execution',
                    ],
                ],
            ],
        ],
    ];

    $scannerService = Mockery::mock(ScannerServiceInterface::class);
    $scannerService->shouldReceive('scan')
        ->with('https://example.com', 'url')
        ->once()
        ->andReturn($mockResults);

    $this->app->instance(ScannerServiceInterface::class, $scannerService);

    $job = new ProcessScheduledScans();
    $job->handle($scannerService);

    $scan->refresh();

    // Verify scan completion
    expect($scan->status)->toBe(ScanStatus::COMPLETED->value);
    expect($scan->results)->toHaveCount(3);

    // Check first result (failed security header)
    $headerResult = $scan->results->where('check_type', 'security_header')->first();
    expect($headerResult)->not()->toBeNull();
    expect($headerResult->target)->toBe('https://example.com');
    expect($headerResult->target_type)->toBe('url');
    expect($headerResult->check_name)->toBe('X-Frame-Options');
    expect($headerResult->passed)->toBeFalse();
    expect($headerResult->severity)->toBe('medium');
    expect($headerResult->recommendations)->toHaveCount(1);
    expect($headerResult->recommendations[0])->toBe('Add X-Frame-Options header');

    // Check second result (passed SSL)
    $sslResult = $scan->results->where('check_type', 'ssl_certificate')->first();
    expect($sslResult)->not()->toBeNull();
    expect($sslResult->check_name)->toBe('Certificate Validity');
    expect($sslResult->passed)->toBeTrue();
    expect($sslResult->severity)->toBeNull();

    // Check third result (with vulnerabilities)
    $serviceResult = $scan->results->where('check_type', 'service_detection')->first();
    expect($serviceResult)->not()->toBeNull();
    expect($serviceResult->passed)->toBeTrue();
    expect($serviceResult->vulnerabilities)->toHaveCount(1);
    expect($serviceResult->vulnerabilities[0]['cve'])->toBe('CVE-2021-44228');
    expect($serviceResult->vulnerabilities[0]['severity'])->toBe('critical');

    // Verify check_data contains full original data
    expect($headerResult->check_data)->toHaveKey('type');
    expect($headerResult->check_data)->toHaveKey('expected');
    expect($headerResult->check_data['expected'])->toBe('SAMEORIGIN');
});
