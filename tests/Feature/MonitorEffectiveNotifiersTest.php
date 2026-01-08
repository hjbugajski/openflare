<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\Notifier;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->monitor = Monitor::factory()->create(['user_id' => $this->user->uuid]);
});

describe('getEffectiveNotifiers', function () {
    it('returns explicitly attached notifiers', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => false,
        ]);
        $this->monitor->notifiers()->attach($notifier, ['is_excluded' => false]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(1)
            ->and((string) $effective->first()->id)->toBe((string) $notifier->id);
    });

    it('returns apply_to_all notifiers without explicit attachment', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => true,
        ]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(1)
            ->and((string) $effective->first()->id)->toBe((string) $notifier->id);
    });

    it('excludes notifiers with is_excluded pivot', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => true,
        ]);
        $this->monitor->notifiers()->attach($notifier, ['is_excluded' => true]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(0);
    });

    it('excludes inactive notifiers', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => false,
            'apply_to_all' => true,
        ]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(0);
    });

    it('excludes notifiers belonging to other users', function () {
        $otherUser = User::factory()->create();
        Notifier::factory()->create([
            'user_id' => $otherUser->uuid,
            'is_active' => true,
            'apply_to_all' => true,
        ]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(0);
    });

    it('combines explicit and apply_to_all notifiers', function () {
        $explicitNotifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => false,
        ]);
        $applyToAllNotifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => true,
        ]);
        $this->monitor->notifiers()->attach($explicitNotifier, ['is_excluded' => false]);

        $effective = $this->monitor->getEffectiveNotifiers();

        $effectiveIds = $effective->pluck('id')->map(fn ($id) => (string) $id)->toArray();

        expect($effective)->toHaveCount(2)
            ->and($effectiveIds)->toContain((string) $explicitNotifier->id, (string) $applyToAllNotifier->id);
    });

    it('does not duplicate apply_to_all notifier when also explicitly attached', function () {
        $notifier = Notifier::factory()->create([
            'user_id' => $this->user->uuid,
            'is_active' => true,
            'apply_to_all' => true,
        ]);
        $this->monitor->notifiers()->attach($notifier, ['is_excluded' => false]);

        $effective = $this->monitor->getEffectiveNotifiers();

        expect($effective)->toHaveCount(1);
    });
});
