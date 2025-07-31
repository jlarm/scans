<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_results', function (Blueprint $table): void {
            $table->id();
            $table->uuid();
            $table->foreignId('scan_id')->constrained('scans')->onDelete('cascade');
            $table->string('target'); // URL or IP address that was scanned
            $table->string('target_type'); // 'url' or 'ip'
            $table->string('check_type'); // 'security_header', 'ssl_certificate', 'port_scan', etc.
            $table->string('check_name')->nullable(); // Name of the specific check
            $table->boolean('passed'); // Whether the check passed
            $table->string('severity', 20)->nullable(); // 'low', 'medium', 'high', 'critical'
            $table->string('risk_level', 20)->nullable(); // Alternative to severity for some checks
            $table->text('message')->nullable(); // Human-readable message
            $table->text('description')->nullable(); // Detailed description
            $table->json('check_data')->nullable(); // All the raw check data
            $table->json('recommendations')->nullable(); // Array of recommendations
            $table->json('vulnerabilities')->nullable(); // Array of vulnerabilities found
            $table->timestamp('scanned_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['scan_id', 'target']);
            $table->index(['check_type', 'passed']);
            $table->index(['severity']);
            $table->index(['scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_results');
    }
};
