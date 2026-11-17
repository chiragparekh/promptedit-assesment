<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.timeout' => 30,
    ]);
});

test('guests cannot request an estimate', function () {
    Http::preventStrayRequests();

    $this->postJson(route('generations.estimate'), [
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
    ])->assertUnauthorized();

    Http::assertNothingSent();
});

test('returns the estimate for the selected settings', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(pixelForgePricing()),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [
            'prompt' => str_repeat('a', 100),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => ['https://example.com/style.png'],
        ]);

    $response->assertOk()
        ->assertJsonPath('currency', 'usd')
        ->assertJsonPath('tokens.input_image', 765)
        ->assertJsonPath('provider_cost.estimate', '0.065525000')
        ->assertJsonPath('price.estimate', '0.081906250')
        ->assertJsonPath('price.min', '0.067818750')
        ->assertJsonPath('price.max', '0.095993750');

    Http::assertSentCount(1);
});

test('rejects invalid settings without calling the provider', function () {
    Http::preventStrayRequests();

    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [
            'prompt' => 'A lighthouse',
            'size' => '2048x2048',
            'quality' => 'medium',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['size']);

    Http::assertNothingSent();
});

test('reports when pricing cannot be loaded', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_api_key',
                'message' => 'The API key is invalid.',
            ],
        ], 401),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('generations.estimate'), [
            'prompt' => 'A lighthouse',
            'size' => '1024x1024',
            'quality' => 'medium',
        ])
        ->assertStatus(503)
        ->assertJsonPath('error.code', 'pricing_unavailable');
});
