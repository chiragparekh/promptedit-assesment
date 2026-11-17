<?php

use App\Services\PricingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.timeout' => 30,
    ]);
});

test('fetches pricing from the provider once and caches it', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(pixelForgePricing()),
    ]);

    $pricing = app(PricingService::class);

    expect($pricing->latest()['rates']['output_tokens'])->toBe('40.00')
        ->and($pricing->latest()['tokens']['per_reference_image'])->toBe(765);

    Http::assertSentCount(1);
});

test('refetches pricing after the cache expires', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::sequence()
            ->push(pixelForgePricing())
            ->push(pixelForgePricing(['rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '45.00',
                'reasoning_tokens' => '12.50',
            ]])),
    ]);

    $pricing = app(PricingService::class);

    expect($pricing->latest()['rates']['output_tokens'])->toBe('40.00');

    $this->travel(61)->minutes();

    expect($pricing->latest()['rates']['output_tokens'])->toBe('45.00');

    Http::assertSentCount(2);
});

test('returns null and stops retrying when pricing cannot be fetched', function () {
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

    $pricing = app(PricingService::class);

    expect($pricing->latest())->toBeNull()
        ->and($pricing->latest())->toBeNull();

    Http::assertSentCount(1);
});
