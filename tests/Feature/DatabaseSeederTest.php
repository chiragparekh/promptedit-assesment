<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it seeds the demo user', function () {
    $this->seed();

    $user = User::where('email', 'demo@acme.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Demo User')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

test('it can be run more than once without creating duplicates', function () {
    $this->seed();
    $this->seed();

    expect(User::where('email', 'demo@acme.com')->count())->toBe(1);
});
