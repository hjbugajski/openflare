<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

describe('registration when no users exist', function () {
    test('registration screen can be rendered', function () {
        $this->get(route('register'))->assertOk();
    });

    test('new users can register', function () {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    });

    test('login page shows registration link when no users exist', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('canRegister', true)
            );
    });

    test('registration is protected by cache lock to prevent race conditions', function () {
        // Acquire the registration lock to simulate a concurrent request
        $lock = Cache::lock('registration_check', 10);
        $lock->get();

        try {
            // Attempt registration while lock is held should fail with 429
            $response = $this->get(route('register'));
            $response->assertStatus(429);
        } finally {
            $lock->release();
        }

        // After lock is released, registration should work
        $this->get(route('register'))->assertOk();
    });
});

describe('registration when user already exists', function () {
    beforeEach(function () {
        User::factory()->create();
    });

    test('registration screen returns 404', function () {
        $this->get(route('register'))->assertNotFound();
    });

    test('registration POST returns 404', function () {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
    });

    test('login page hides registration link when user exists', function () {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/login')
                ->where('canRegister', false)
            );
    });
});
