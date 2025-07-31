<?php

declare(strict_types=1);

use App\Models\Company; 
use App\Models\Scan;
use App\Models\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->scan = Scan::factory()->create(['company_id' => $this->company->id]);
});

test('can create scan result', function () {
    $scanResult = ScanResult::factory()->create([
        'scan_id' => $this->scan->id,
        'target' => 'https://example.com',
        'target_type' => 'url',
        'check_type' => 'security_header',
        'check_name' => 'X-Frame-Options',
        'passed' => true,
    ]);

    expect($scanResult->scan_id)->toBe($this->scan->id);
    expect($scanResult->target)->toBe('https://example.com');
    expect($scanResult->passed)->toBeTrue();
});

test('scan result belongs to a scan', function () {
    $scanResult = ScanResult::factory()->create(['scan_id' => $this->scan->id]);

    expect($scanResult->scan)->toBeInstanceOf(Scan::class);
    expect($scanResult->scan->id)->toBe($this->scan->id);
});

test('scan has many results', function () {
    $results = ScanResult::factory()->count(3)->create(['scan_id' => $this->scan->id]);

    $this->scan->refresh();
    
    expect($this->scan->results)->toHaveCount(3);
    expect($this->scan->results->first())->toBeInstanceOf(ScanResult::class);
});

test('can filter scan results by severity', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'high']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'low']); 
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'high']);

    $highSeverityResults = ScanResult::bySeverity('high')->get();
    
    expect($highSeverityResults)->toHaveCount(2);
    expect($highSeverityResults->first()->severity)->toBe('high');
});

test('can filter scan results by check type', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'check_type' => 'security_header']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'check_type' => 'port_scan']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'check_type' => 'security_header']);

    $headerResults = ScanResult::byCheckType('security_header')->get();
    
    expect($headerResults)->toHaveCount(2);
    expect($headerResults->first()->check_type)->toBe('security_header');
});

test('can filter failed scan results', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => true]);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => false]);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => false]);

    $failedResults = ScanResult::failed()->get();
    
    expect($failedResults)->toHaveCount(2);
    expect($failedResults->first()->passed)->toBeFalse();
});

test('can filter passed scan results', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => true]);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => false]);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'passed' => true]);

    $passedResults = ScanResult::passed()->get();
    
    expect($passedResults)->toHaveCount(2);
    expect($passedResults->first()->passed)->toBeTrue();
});

test('can filter high risk results', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'low']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'high']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'critical']);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'risk_level' => 'high']);

    $highRiskResults = ScanResult::highRisk()->get();
    
    expect($highRiskResults)->toHaveCount(3);
});

test('can filter results with vulnerabilities', function () {
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'vulnerabilities' => null]);
    ScanResult::factory()->create(['scan_id' => $this->scan->id, 'vulnerabilities' => []]);
    ScanResult::factory()->withVulnerabilities()->create(['scan_id' => $this->scan->id]);
    ScanResult::factory()->withVulnerabilities()->create(['scan_id' => $this->scan->id]);

    $vulnResults = ScanResult::withVulnerabilities()->get();
    
    expect($vulnResults)->toHaveCount(2);
});

test('hasVulnerabilities method works correctly', function () {
    $withVuln = ScanResult::factory()->withVulnerabilities()->create(['scan_id' => $this->scan->id]);
    $withoutVuln = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'vulnerabilities' => null]);

    expect($withVuln->hasVulnerabilities())->toBeTrue();
    expect($withoutVuln->hasVulnerabilities())->toBeFalse();
});

test('getVulnerabilityCount method works correctly', function () {
    $vulnerabilities = [
        ['cve' => 'CVE-2021-44228', 'severity' => 'critical'],
        ['cve' => 'CVE-2021-41773', 'severity' => 'high']
    ];
    
    $withVuln = ScanResult::factory()->create([
        'scan_id' => $this->scan->id,
        'vulnerabilities' => $vulnerabilities
    ]);
    $withoutVuln = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'vulnerabilities' => null]);

    expect($withVuln->getVulnerabilityCount())->toBe(2);
    expect($withoutVuln->getVulnerabilityCount())->toBe(0);
});

test('isHighRisk method works correctly', function () {
    $highSeverity = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'high']);
    $criticalSeverity = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'critical']);
    $highRiskLevel = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'risk_level' => 'high']);
    $withVuln = ScanResult::factory()->withVulnerabilities()->create(['scan_id' => $this->scan->id]);
    $lowRisk = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'low', 'risk_level' => 'low', 'vulnerabilities' => null]);

    expect($highSeverity->isHighRisk())->toBeTrue();
    expect($criticalSeverity->isHighRisk())->toBeTrue();
    expect($highRiskLevel->isHighRisk())->toBeTrue();
    expect($withVuln->isHighRisk())->toBeTrue();
    expect($lowRisk->isHighRisk())->toBeFalse();
});

test('getRiskLevel method works correctly', function () {
    $withSeverity = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => 'high']);
    $withRiskLevel = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => null, 'risk_level' => 'medium']);
    $withVuln = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => null, 'risk_level' => null, 'vulnerabilities' => [['cve' => 'test']]]);
    $passed = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => null, 'risk_level' => null, 'vulnerabilities' => null, 'passed' => true]);
    $failed = ScanResult::factory()->create(['scan_id' => $this->scan->id, 'severity' => null, 'risk_level' => null, 'vulnerabilities' => null, 'passed' => false]);

    expect($withSeverity->getRiskLevel())->toBe('high');
    expect($withRiskLevel->getRiskLevel())->toBe('medium');
    expect($withVuln->getRiskLevel())->toBe('high');
    expect($passed->getRiskLevel())->toBe('low');
    expect($failed->getRiskLevel())->toBe('medium');
});

test('json fields are properly cast', function () {
    $checkData = ['type' => 'security_header', 'passed' => true];
    $recommendations = ['Enable HTTPS', 'Set proper headers'];
    $vulnerabilities = [['cve' => 'CVE-2021-44228']];

    $result = ScanResult::factory()->create([
        'scan_id' => $this->scan->id,
        'check_data' => $checkData,
        'recommendations' => $recommendations,
        'vulnerabilities' => $vulnerabilities,
    ]);

    expect($result->check_data)->toBeArray();
    expect($result->recommendations)->toBeArray();
    expect($result->vulnerabilities)->toBeArray();
    expect($result->check_data['type'])->toBe('security_header');
    expect($result->recommendations[0])->toBe('Enable HTTPS');
    expect($result->vulnerabilities[0]['cve'])->toBe('CVE-2021-44228');
});