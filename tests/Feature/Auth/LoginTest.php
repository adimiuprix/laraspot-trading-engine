<?php

use App\Models\User;

test('home screen can be rendered', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('login route redirects to home', function () {
    $response = $this->get('/login');

    $response->assertRedirect('/');
});

test('users can authenticate using the login form', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('exchange'));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('home page redirects authenticated users to exchange', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/exchange');
});

test('exchange requires authentication', function () {
    $response = $this->get('/exchange');

    $response->assertRedirect('/');
});
