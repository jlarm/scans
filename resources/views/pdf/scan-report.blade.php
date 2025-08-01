<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Scan Report - {{ $scan->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #374151;
        }
        
        .header {
            background: #1f2937;
            color: white;
            padding: 30px 20px;
            margin: -15mm -20mm 30px -20mm;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header .subtitle {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .container {
            padding: 0 10px;
        }
        
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .section.large {
            page-break-inside: auto;
            break-inside: auto;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
        }
        
        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        
        .info-value {
            color: #1f2937;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-critical {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .badge-high {
            background: #fef3f2;
            color: #ea580c;
            border: 1px solid #fed7aa;
        }
        
        .badge-medium {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fed7aa;
        }
        
        .badge-low {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .badge-passed {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .badge-failed {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: auto;
        }
        
        .table thead tr {
            page-break-after: avoid;
            break-after: avoid;
        }
        
        .table th,
        .table td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 11px;
        }
        
        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .risk-score {
            font-weight: 700;
        }
        
        .risk-score.high {
            color: #dc2626;
        }
        
        .risk-score.medium {
            color: #d97706;
        }
        
        .risk-score.low {
            color: #16a34a;
        }
        
        .target-section {
            margin-bottom: 15px;
            page-break-inside: avoid;
            break-inside: avoid;
            max-height: 400px;
            overflow: hidden;
        }
        
        .target-section.allow-break {
            page-break-inside: auto;
            break-inside: auto;
            max-height: none;
        }
        
        .target-title {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 6px;
        }
        
        .check-type {
            margin-bottom: 15px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .check-type.compact {
            margin-bottom: 10px;
        }
        
        .check-type-title {
            font-size: 14px;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 10px;
        }
        
        .check-item {
            padding: 8px;
            border-left: 3px solid #e5e7eb;
            margin-bottom: 4px;
            background: #f9fafb;
            page-break-inside: avoid;
            break-inside: avoid;
            font-size: 11px;
        }
        
        .check-item.failed {
            border-left-color: #dc2626;
            background: #fef2f2;
        }
        
        .check-item.passed {
            border-left-color: #16a34a;
            background: #f0fdf4;
        }
        
        .check-name {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .check-message {
            color: #6b7280;
            font-size: 11px;
        }
        
        .footer-info {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 11px;
            color: #6b7280;
            text-align: center;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @page {
            margin: 15mm 20mm;
            size: A4 portrait;
        }
        
        @media print {
            .section {
                page-break-inside: avoid;
            }
            
            .table {
                page-break-inside: auto;
            }
            
            .table thead {
                display: table-header-group;
            }
            
            .table tbody tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Security Scan Report</h1>
        <div class="subtitle">{{ $scan->name }} - {{ $scan->company->name }}</div>
    </div>

    <div class="container">
        <!-- Executive Summary -->
        <div class="section">
            <h2 class="section-title">Executive Summary</h2>
            
            <div class="info-grid">
                <div class="info-label">Scan Name:</div>
                <div class="info-value">{{ $scan->name }}</div>
                
                <div class="info-label">Company:</div>
                <div class="info-value">{{ $scan->company->name }}</div>
                
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="badge badge-{{ $scan->status === 'completed' ? 'passed' : 'failed' }}">
                        {{ ucfirst($scan->status) }}
                    </span>
                </div>
                
                <div class="info-label">Risk Grade:</div>
                <div class="info-value">
                    @if($scan->risk_grade)
                        <span class="badge badge-{{ strtolower($scan->risk_grade) === 'a' ? 'passed' : (in_array(strtolower($scan->risk_grade), ['d', 'f']) ? 'failed' : 'medium') }}">
                            {{ $scan->risk_grade }}
                        </span>
                    @else
                        <span class="info-value">Not calculated</span>
                    @endif
                </div>
                
                <div class="info-label">Started:</div>
                <div class="info-value">{{ $scan->started_at ? $scan->started_at->format('M j, Y g:i A') : 'N/A' }}</div>
                
                <div class="info-label">Completed:</div>
                <div class="info-value">{{ $scan->completed_at ? $scan->completed_at->format('M j, Y g:i A') : 'N/A' }}</div>
                
                @if($analytics['overview']['scan_duration'])
                <div class="info-label">Duration:</div>
                <div class="info-value">{{ gmdate('H:i:s', $analytics['overview']['scan_duration']) }}</div>
                @endif
                
                <div class="info-label">Generated:</div>
                <div class="info-value">{{ $generated_at->format('M j, Y g:i A') }}</div>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="section">
            <h2 class="section-title">Scan Overview</h2>
            
            <div class="grid grid-4">
                <div class="card">
                    <div class="card-title">Total Checks</div>
                    <div class="card-value">{{ number_format($analytics['overview']['total_checks']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Passed</div>
                    <div class="card-value" style="color: #16a34a;">{{ number_format($analytics['overview']['passed_checks']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Failed</div>
                    <div class="card-value" style="color: #dc2626;">{{ number_format($analytics['overview']['failed_checks']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Targets</div>
                    <div class="card-value">{{ number_format($analytics['overview']['targets_scanned']) }}</div>
                </div>
            </div>
        </div>

        <!-- Severity Breakdown -->
        <div class="section">
            <h2 class="section-title">Security Issues by Severity</h2>
            
            <div class="grid grid-4">
                <div class="card">
                    <div class="card-title">Critical</div>
                    <div class="card-value" style="color: #dc2626;">{{ number_format($analytics['severity_breakdown']['critical']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">High</div>
                    <div class="card-value" style="color: #ea580c;">{{ number_format($analytics['severity_breakdown']['high']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Medium</div>
                    <div class="card-value" style="color: #d97706;">{{ number_format($analytics['severity_breakdown']['medium']) }}</div>
                </div>
                
                <div class="card">
                    <div class="card-title">Low</div>
                    <div class="card-value" style="color: #16a34a;">{{ number_format($analytics['severity_breakdown']['low']) }}</div>
                </div>
            </div>
        </div>

        <!-- Check Types Summary -->
        @if(count($analytics['check_types']) > 0)
        <div class="section large">
            <h2 class="section-title">Scan Results by Check Type</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Check Type</th>
                        <th>Total</th>
                        <th>Passed</th>
                        <th>Failed</th>
                        <th>High Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['check_types'] as $checkType)
                    <tr>
                        <td>{{ ucwords(str_replace('_', ' ', $checkType['type'])) }}</td>
                        <td>{{ number_format($checkType['total']) }}</td>
                        <td style="color: #16a34a;">{{ number_format($checkType['passed']) }}</td>
                        <td style="color: #dc2626;">{{ number_format($checkType['failed']) }}</td>
                        <td style="color: #ea580c;">{{ number_format($checkType['high_risk']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Target Risk Summary -->
        @if(count($analytics['targets']) > 0)
        <div class="section large">
            <h2 class="section-title">Target Risk Assessment</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Target</th>
                        <th>Type</th>
                        <th>Total Checks</th>
                        <th>Passed</th>
                        <th>Failed</th>
                        <th>Risk Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['targets'] as $target)
                    <tr>
                        <td>{{ $target['target'] }}</td>
                        <td>{{ ucfirst($target['target_type'] ?? 'Unknown') }}</td>
                        <td>{{ number_format($target['total_checks']) }}</td>
                        <td style="color: #16a34a;">{{ number_format($target['passed']) }}</td>
                        <td style="color: #dc2626;">{{ number_format($target['failed']) }}</td>
                        <td>
                            <span class="risk-score {{ $target['risk_score'] >= 70 ? 'high' : ($target['risk_score'] >= 30 ? 'medium' : 'low') }}">
                                {{ $target['risk_score'] }}/100
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Vulnerabilities -->
        @if(count($vulnerabilities) > 0)
        <div class="section large" style="page-break-before: always;">
            <h2 class="section-title">Critical Vulnerabilities</h2>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>CVE</th>
                        <th>Target</th>
                        <th>Severity</th>
                        <th>Score</th>
                        <th>Description</th>
                        <th>Patch Available</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(collect($vulnerabilities)->take(20) as $vuln)
                    <tr>
                        <td>{{ $vuln['cve'] }}</td>
                        <td>{{ $vuln['target'] }}</td>
                        <td>
                            <span class="badge badge-{{ $vuln['severity'] }}">
                                {{ ucfirst($vuln['severity']) }}
                            </span>
                        </td>
                        <td>{{ $vuln['score'] ?? 'N/A' }}</td>
                        <td style="max-width: 200px; word-wrap: break-word;">{{ Str::limit($vuln['description'], 100) }}</td>
                        <td>
                            <span class="badge badge-{{ $vuln['patch_available'] ? 'passed' : 'failed' }}">
                                {{ $vuln['patch_available'] ? 'Yes' : 'No' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            @if(count($vulnerabilities) > 20)
            <p style="text-align: center; color: #6b7280; font-style: italic;">
                Showing top 20 vulnerabilities. {{ count($vulnerabilities) - 20 }} additional vulnerabilities found.
            </p>
            @endif
        </div>
        @endif

        <!-- Detailed Results -->
        @if(count($resultsByTarget) > 0)
        <div class="section large" style="page-break-before: always;">
            <h2 class="section-title">Detailed Scan Results</h2>
            
            @foreach(collect($resultsByTarget)->take(3) as $targetData)
            <div class="target-section allow-break">
                <div class="target-title">
                    {{ $targetData['target'] }} ({{ ucfirst($targetData['target_type'] ?? 'unknown') }})
                </div>
                
                @foreach($targetData['results'] as $checkTypeData)
                <div class="check-type compact">
                    <div class="check-type-title">{{ ucwords(str_replace('_', ' ', $checkTypeData['check_type'])) }}</div>
                    
                    @foreach(collect($checkTypeData['checks'])->take(5) as $check)
                    <div class="check-item {{ $check['passed'] ? 'passed' : 'failed' }}">
                        <div class="check-name">
                            {{ $check['check_name'] }}
                            @if($check['severity'])
                                <span class="badge badge-{{ $check['severity'] }}">{{ ucfirst($check['severity']) }}</span>
                            @endif
                        </div>
                        @if($check['message'])
                        <div class="check-message">{{ Str::limit($check['message'], 150) }}</div>
                        @endif
                    </div>
                    @endforeach
                    
                    @if(count($checkTypeData['checks']) > 5)
                    <p style="text-align: center; color: #6b7280; font-style: italic; margin-top: 5px; font-size: 10px;">
                        ... and {{ count($checkTypeData['checks']) - 5 }} more checks
                    </p>
                    @endif
                </div>
                @endforeach
            </div>
            @endforeach
            
            @if(count($resultsByTarget) > 3)
            <p style="text-align: center; color: #6b7280; font-style: italic; margin-top: 20px;">
                Showing results for top 3 targets. {{ count($resultsByTarget) - 3 }} additional targets scanned.
            </p>
            @endif
        </div>
        @endif

        <!-- Footer -->
        <div class="footer-info">
            <p>This report was generated automatically by the Security Scanning System on {{ $generated_at->format('F j, Y \a\t g:i A') }}.</p>
            <p>For questions or assistance, please contact your security team.</p>
        </div>
    </div>
</body>
</html>