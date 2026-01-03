<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
        });

        // Password reset tokens
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Cache
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // Jobs
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Monitors
        Schema::create('monitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('method')->default('GET');
            $table->unsignedInteger('interval')->default(300);
            $table->unsignedInteger('timeout')->default(30);
            $table->unsignedSmallInteger('expected_status_code')->default(200);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_check_at']);
        });

        // Monitor checks
        Schema::create('monitor_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['monitor_id', 'checked_at']);
            $table->index('checked_at');
        });

        // Incidents
        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('cause')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'started_at']);
        });

        // Daily uptime rollups
        Schema::create('daily_uptime_rollups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('monitor_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('successful_checks')->default(0);
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->unsignedInteger('min_response_time_ms')->nullable();
            $table->unsignedInteger('max_response_time_ms')->nullable();
            $table->timestamps();

            $table->unique(['monitor_id', 'date']);
            $table->index('date');
        });

        // Notifiers
        Schema::create('notifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('apply_to_all')->default(false);
            $table->timestamps();
        });

        // Monitor-notifier pivot
        Schema::create('monitor_notifier', function (Blueprint $table) {
            $table->foreignUuid('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('notifier_id')->constrained()->cascadeOnDelete();

            $table->primary(['monitor_id', 'notifier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_notifier');
        Schema::dropIfExists('notifiers');
        Schema::dropIfExists('daily_uptime_rollups');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('monitor_checks');
        Schema::dropIfExists('monitors');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
