<?php

use App\Jobs\CheckMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach(fn () => CheckMonitor::$resolveHostIpsOverride = null)
    ->in('Feature');
