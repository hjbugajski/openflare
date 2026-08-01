<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the branded error page for a guest hitting a missing url', function () {
    $this->get('/definitely-not-a-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', shouldExist: false)
            ->where('status', 404)
        );
});

it('renders the branded error page for an authenticated user hitting a missing url', function () {
    $this->actingAs(User::factory()->create())
        ->get('/definitely-not-a-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', shouldExist: false)
            ->where('status', 404)
        );
});

it('renders the branded error page when a monitor belongs to someone else', function () {
    $monitor = Monitor::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('monitors.show', $monitor))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', shouldExist: false)
            ->where('status', 403)
        );
});

it('leaves json clients with a json error payload', function () {
    $this->getJson('/definitely-not-a-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
