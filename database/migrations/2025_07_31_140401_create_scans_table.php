<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('agent')->nullable();
            $table->boolean('send_notification')->default(false);
            $table->string('notification_email')->nullable();
            $table->string('schedule_type')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('cron_expression')->nullable();
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
