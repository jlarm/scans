<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Scan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
});

it('can create a scan with immediate scheduling', function () {
    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'Test Security Scan',
        'description' => 'A comprehensive security scan',
        'urls' => ['https://example.com', 'https://test.com'],
        'ip_addresses' => ['192.168.1.1', '10.0.0.1'],
        'send_notification' => true,
        'notification_email' => 'test@example.com',
        'schedule_type' => 'immediate',
        'schedule_time' => '09:00',
        'frequency' => 'weekly',
        'day_of_week' => 1,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Scan created successfully!');

    $this->assertDatabaseHas('scans', [
        'company_id' => $this->company->id,
        'name' => 'Test Security Scan',
        'description' => 'A comprehensive security scan',
        'send_notification' => true,
        'notification_email' => 'test@example.com',
        'schedule_type' => 'immediate',
    ]);

    $scan = Scan::where('name', 'Test Security Scan')->first();
    expect($scan->urls)->toBe(['https://example.com', 'https://test.com']);
    expect($scan->ip_addresses)->toBe(['192.168.1.1', '10.0.0.1']);
    expect($scan->uuid)->not()->toBeNull();
    expect($scan->scheduled_at)->not()->toBeNull();
});

it('can create a scan with once scheduling', function () {
    $futureDate = Carbon::tomorrow()->format('Y-m-d');

    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'One Time Scan',
        'description' => 'A one-time scan',
        'schedule_type' => 'once',
        'scheduled_date' => $futureDate,
        'schedule_time' => '14:30',
        'send_notification' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('scans', [
        'company_id' => $this->company->id,
        'name' => 'One Time Scan',
        'schedule_type' => 'once',
        'send_notification' => false,
    ]);

    $scan = Scan::where('name', 'One Time Scan')->first();
    expect($scan->scheduled_at->format('Y-m-d H:i'))->toBe($futureDate.' 14:30');
});

it('can create a scan with recurring scheduling', function () {
    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'Weekly Recurring Scan',
        'schedule_type' => 'recurring',
        'frequency' => 'weekly',
        'day_of_week' => 3, // Wednesday
        'schedule_time' => '10:00',
        'send_notification' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('scans', [
        'company_id' => $this->company->id,
        'name' => 'Weekly Recurring Scan',
        'schedule_type' => 'recurring',
        'frequency' => 'weekly',
        'day_of_week' => 3,
    ]);

    $scan = Scan::where('name', 'Weekly Recurring Scan')->first();
    expect($scan->cron_expression)->toBe('00 10 * * 3');
});

it('can create a scan with a new company', function () {
    $scanData = [
        'company_name' => 'New Test Company',
        'name' => 'Scan for New Company',
        'schedule_type' => 'immediate',
        'send_notification' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));

    // Check that the company was created
    $this->assertDatabaseHas('companies', [
        'name' => 'New Test Company',
    ]);

    // Check that the scan was created with the new company
    $company = Company::where('name', 'New Test Company')->first();
    $this->assertDatabaseHas('scans', [
        'company_id' => $company->id,
        'name' => 'Scan for New Company',
    ]);
});

it('filters out empty urls and ip addresses', function () {
    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'Test Scan with Empty Values',
        'urls' => ['https://example.com', '', 'https://test.com', ''],
        'ip_addresses' => ['192.168.1.1', '', '10.0.0.1'],
        'schedule_type' => 'immediate',
        'send_notification' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));

    $scan = Scan::where('name', 'Test Scan with Empty Values')->first();
    expect($scan->urls)->toBe(['https://example.com', 'https://test.com']);
    expect($scan->ip_addresses)->toBe(['192.168.1.1', '10.0.0.1']);
});

it('sets urls and ip_addresses to null when all values are empty', function () {
    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'Test Scan with All Empty',
        'urls' => ['', ''],
        'ip_addresses' => ['', ''],
        'schedule_type' => 'immediate',
        'send_notification' => false,
    ];

    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('dashboard'));

    $scan = Scan::where('name', 'Test Scan with All Empty')->first();
    expect($scan->urls)->toBeNull();
    expect($scan->ip_addresses)->toBeNull();
});

it('requires authentication', function () {
    $scanData = [
        'company_id' => $this->company->id,
        'name' => 'Unauthorized Scan',
        'schedule_type' => 'immediate',
    ];

    $response = $this->post(route('scans.store'), $scanData);

    $response->assertRedirect(route('login'));
});

it('validates required fields', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), []);

    $response->assertSessionHasErrors(['name', 'schedule_type']);
});

it('validates company selection or creation', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'name' => 'Test Scan',
            'schedule_type' => 'immediate',
            // Missing both company_id and company_name
        ]);

    $response->assertSessionHasErrors(['company_id']);
});

it('validates email when notifications are enabled', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'company_id' => $this->company->id,
            'name' => 'Test Scan',
            'schedule_type' => 'immediate',
            'send_notification' => true,
            // Missing notification_email
        ]);

    $response->assertSessionHasErrors(['notification_email']);
});

it('validates url format', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'company_id' => $this->company->id,
            'name' => 'Test Scan',
            'schedule_type' => 'immediate',
            'urls' => ['not-a-valid-url'],
            'send_notification' => false,
        ]);

    $response->assertSessionHasErrors(['urls.0']);
});

it('validates ip address format', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'company_id' => $this->company->id,
            'name' => 'Test Scan',
            'schedule_type' => 'immediate',
            'ip_addresses' => ['not-a-valid-ip'],
            'send_notification' => false,
        ]);

    $response->assertSessionHasErrors(['ip_addresses.0']);
});

it('validates recurring schedule fields', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'company_id' => $this->company->id,
            'name' => 'Test Scan',
            'schedule_type' => 'recurring',
            // Missing frequency
            'send_notification' => false,
        ]);

    $response->assertSessionHasErrors(['frequency']);
});

it('validates day of week for weekly frequency', function () {
    $response = $this->actingAs($this->user)
        ->post(route('scans.store'), [
            'company_id' => $this->company->id,
            'name' => 'Test Scan',
            'schedule_type' => 'recurring',
            'frequency' => 'weekly',
            // Missing day_of_week
            'send_notification' => false,
        ]);

    $response->assertSessionHasErrors(['day_of_week']);
});
