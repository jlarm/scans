<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table): void {
            $table->id();
            $table->uuid();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('urls')->nullable();
            $table->json('ip_addresses')->nullable();
            $table->boolean('send_notification')->default(false);
            $table->string('notification_email')->nullable();
            $table->string('schedule_type')->default('immediate');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('cron_expression')->nullable();
            $table->string('frequency')->nullable(); // daily, weekly, monthly
            $table->tinyInteger('day_of_week')->nullable(); // 0=Sunday, 1=Monday, etc.
            $table->time('schedule_time')->nullable();
            $table->string('status')->default('pending');
            $table->char('risk_grade', 1)->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
