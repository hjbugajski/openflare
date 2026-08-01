<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password link uses APP_URL host even when request Host header is spoofed', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->withHeaders([
        'Host' => 'evil.example.com',
        'X-Forwarded-Host' => 'evil.example.com',
    ])->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);
        $expectedHost = parse_url(config('app.url'), PHP_URL_HOST);

        expect($mail->actionUrl)->toContain($expectedHost)
            ->and($mail->actionUrl)->not->toContain('evil.example.com');

        return true;
    });
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Xk9#mP2$vL5@nQ8w',
            'password_confirmation' => 'Xk9#mP2$vL5@nQ8w',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('reset link requests are rate limited per email and ip', function () {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertStatus(302);
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertTooManyRequests();
});

test('a reset link request for another email from the same ip is a separate bucket', function () {
    Notification::fake();

    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        $this->post(route('password.email'), ['email' => $user->email]);
    }

    // Per-IP flooding across many addresses is out of scope: each address still
    // has to get past its own hourly bucket.
    $this->post(route('password.email'), ['email' => $other->email])
        ->assertStatus(302);
});

test('reset link requests from rotating source ips are rate limited by email', function () {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.$i])
            ->post(route('password.email'), ['email' => $user->email])
            ->assertStatus(302);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.6'])
        ->post(route('password.email'), ['email' => $user->email])
        ->assertTooManyRequests();
});

test('password reset submissions are rate limited', function () {
    $user = User::factory()->create();

    $payload = [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'Xk9#mP2$vL5@nQ8w',
        'password_confirmation' => 'Xk9#mP2$vL5@nQ8w',
    ];

    foreach (range(1, 5) as $ignored) {
        $this->post(route('password.update'), $payload)->assertStatus(302);
    }

    $this->post(route('password.update'), $payload)->assertTooManyRequests();
});

test('reset link requests do not consume the reset submission budget', function () {
    Notification::fake();

    $user = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        $this->post(route('password.email'), ['email' => $user->email]);
    }

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'Xk9#mP2$vL5@nQ8w',
        'password_confirmation' => 'Xk9#mP2$vL5@nQ8w',
    ])->assertStatus(302);
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
