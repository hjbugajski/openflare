<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\User;
use App\Policies\MonitorPolicy;

beforeEach(function () {
    $this->policy = new MonitorPolicy;
    $this->user = User::factory()->create();
});

describe('viewAny', function () {
    test('it allows any authenticated user to view monitors list', function () {
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });
});

describe('view', function () {
    test('it allows owner to view their monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->view($this->user, $monitor))->toBeTrue();
    });

    test('it denies non-owner from viewing monitor', function () {
        $otherUser = User::factory()->create();
        $monitor = Monitor::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->view($this->user, $monitor))->toBeFalse();
    });
});

describe('create', function () {
    test('it allows any authenticated user to create monitors', function () {
        expect($this->policy->create($this->user))->toBeTrue();
    });
});

describe('update', function () {
    test('it allows owner to update their monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->update($this->user, $monitor))->toBeTrue();
    });

    test('it denies non-owner from updating monitor', function () {
        $otherUser = User::factory()->create();
        $monitor = Monitor::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->update($this->user, $monitor))->toBeFalse();
    });
});

describe('delete', function () {
    test('it allows owner to delete their monitor', function () {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->delete($this->user, $monitor))->toBeTrue();
    });

    test('it denies non-owner from deleting monitor', function () {
        $otherUser = User::factory()->create();
        $monitor = Monitor::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->delete($this->user, $monitor))->toBeFalse();
    });
});
