<?php

declare(strict_types=1);

use App\Models\Notifier;
use App\Models\User;
use App\Policies\NotifierPolicy;

beforeEach(function () {
    $this->policy = new NotifierPolicy;
    $this->user = User::factory()->create();
});

describe('viewAny', function () {
    test('it allows any authenticated user to view notifiers list', function () {
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });
});

describe('view', function () {
    test('it allows owner to view their notifier', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->view($this->user, $notifier))->toBeTrue();
    });

    test('it denies non-owner from viewing notifier', function () {
        $otherUser = User::factory()->create();
        $notifier = Notifier::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->view($this->user, $notifier))->toBeFalse();
    });
});

describe('create', function () {
    test('it allows any authenticated user to create notifiers', function () {
        expect($this->policy->create($this->user))->toBeTrue();
    });
});

describe('update', function () {
    test('it allows owner to update their notifier', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->update($this->user, $notifier))->toBeTrue();
    });

    test('it denies non-owner from updating notifier', function () {
        $otherUser = User::factory()->create();
        $notifier = Notifier::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->update($this->user, $notifier))->toBeFalse();
    });
});

describe('delete', function () {
    test('it allows owner to delete their notifier', function () {
        $notifier = Notifier::factory()->create(['user_id' => $this->user->uuid]);

        expect($this->policy->delete($this->user, $notifier))->toBeTrue();
    });

    test('it denies non-owner from deleting notifier', function () {
        $otherUser = User::factory()->create();
        $notifier = Notifier::factory()->create(['user_id' => $otherUser->uuid]);

        expect($this->policy->delete($this->user, $notifier))->toBeFalse();
    });
});
