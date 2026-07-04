<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;

function invokeReverbValidation(): void
{
    $provider = new AppServiceProvider(app());
    $method = new ReflectionMethod($provider, 'validateProductionSecuritySettings');
    $method->invoke($provider);
}

it('throws in production when Reverb credentials are the default values', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('reverb.apps.apps.0.key', 'openflare-key');
    config()->set('reverb.apps.apps.0.secret', 'openflare-secret');

    expect(fn () => invokeReverbValidation())->toThrow(RuntimeException::class);
});

it('throws in production when Reverb credentials are empty', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('reverb.apps.apps.0.key', '');
    config()->set('reverb.apps.apps.0.secret', '');

    expect(fn () => invokeReverbValidation())->toThrow(RuntimeException::class);
});

it('does not throw in production when Reverb credentials are real values', function () {
    app()->detectEnvironment(fn () => 'production');
    config()->set('reverb.apps.apps.0.key', 'a-real-generated-key');
    config()->set('reverb.apps.apps.0.secret', 'a-real-generated-secret');

    expect(fn () => invokeReverbValidation())->not->toThrow(RuntimeException::class);
});

it('does not throw in local environment even with default Reverb credentials', function () {
    app()->detectEnvironment(fn () => 'local');
    config()->set('reverb.apps.apps.0.key', 'openflare-key');
    config()->set('reverb.apps.apps.0.secret', 'openflare-secret');

    expect(fn () => invokeReverbValidation())->not->toThrow(RuntimeException::class);
});
