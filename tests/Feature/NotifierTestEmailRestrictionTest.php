<?php

declare(strict_types=1);

use App\Mail\TestNotification;
use App\Models\Notifier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Mail::fake();

    $this->user = User::factory()->create(['email' => 'owner@example.com']);
});

function postTestEmail(string $email): TestResponse
{
    return test()->actingAs(test()->user)->postJson(route('notifiers.test'), [
        'type' => 'email',
        'config' => ['email' => $email],
    ]);
}

it('allows a test email to the authenticated user own address', function () {
    postTestEmail('owner@example.com')->assertOk();

    Mail::assertSent(TestNotification::class, fn ($mail) => $mail->hasTo('owner@example.com'));
});

it('matches the account address case-insensitively', function () {
    postTestEmail('Owner@Example.COM')->assertOk();

    Mail::assertSent(TestNotification::class);
});

it('allows a test email to an address saved on one of the user notifiers', function () {
    Notifier::factory()->email()->create([
        'user_id' => $this->user->uuid,
        'config' => ['email' => 'oncall@example.com'],
    ]);

    postTestEmail('oncall@example.com')->assertOk();

    Mail::assertSent(TestNotification::class, fn ($mail) => $mail->hasTo('oncall@example.com'));
});

it('rejects a test email to an arbitrary third-party address', function () {
    postTestEmail('stranger@example.org')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('config.email');

    Mail::assertNothingSent();
});

it('rejects an address saved only on another user notifier', function () {
    $other = User::factory()->create();

    Notifier::factory()->email()->create([
        'user_id' => $other->uuid,
        'config' => ['email' => 'stranger@example.org'],
    ]);

    postTestEmail('stranger@example.org')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('config.email');

    Mail::assertNothingSent();
});

it('rejects rather than errors when one of the user notifier configs is unreadable', function () {
    $notifier = Notifier::factory()->email()->create([
        'user_id' => $this->user->uuid,
        'config' => ['email' => 'oncall@example.com'],
    ]);

    DB::table('notifiers')->where('id', $notifier->id)->update(['config' => 'not-valid-ciphertext']);

    postTestEmail('oncall@example.com')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('config.email');

    postTestEmail('owner@example.com')->assertOk();
});
