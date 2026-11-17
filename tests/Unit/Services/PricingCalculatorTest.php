<?php

use App\Services\PricingCalculator;

test('estimates a generation from the published token rules', function () {
    $estimate = app(PricingCalculator::class)->estimate(
        pricing: pixelForgePricing(),
        prompt: str_repeat('a', 100),
        size: '1024x1024',
        quality: 'medium',
    );

    expect($estimate['tokens'])->toMatchArray([
        'input_text' => 25,
        'input_image' => 0,
        'output' => 1100,
        'reasoning' => ['min' => 200, 'estimate' => 1100, 'max' => 2000],
    ])
        ->and($estimate['provider_cost'])->toBe([
            'min' => '0.046605000',
            'estimate' => '0.057875000',
            'max' => '0.069145000',
        ])
        ->and($estimate['price'])->toBe([
            'min' => '0.058256250',
            'estimate' => '0.072343750',
            'max' => '0.086431250',
        ])
        ->and($estimate['currency'])->toBe('usd');
});

test('bills each reference image as input image tokens', function () {
    $estimate = app(PricingCalculator::class)->estimate(
        pricing: pixelForgePricing(),
        prompt: '',
        size: '1536x1024',
        quality: 'high',
        referenceImages: ['https://example.com/one.png', 'https://example.com/two.png'],
    );

    expect($estimate['tokens']['input_image'])->toBe(1530)
        ->and($estimate['tokens']['output'])->toBe(6450)
        ->and($estimate['tokens']['reasoning'])->toBe(['min' => 1000, 'estimate' => 4500, 'max' => 8000])
        ->and($estimate['provider_cost']['min'])->toBe('0.285800000')
        ->and($estimate['provider_cost']['max'])->toBe('0.373300000')
        ->and($estimate['price']['min'])->toBe('0.357250000');
});

test('counts prompt characters as unicode code points', function () {
    $estimate = app(PricingCalculator::class)->estimate(
        pricing: pixelForgePricing(),
        prompt: str_repeat('🖼', 8),
        size: '1024x1024',
        quality: 'low',
    );

    expect($estimate['tokens']['input_text'])->toBe(2);
});

test('matches the maximum cost example in the provider docs', function () {
    $estimate = app(PricingCalculator::class)->estimate(
        pricing: pixelForgePricing(),
        prompt: str_repeat('a', 400),
        size: '1536x1024',
        quality: 'high',
        referenceImages: ['https://example.com/a.png', 'https://example.com/b.png'],
    );

    expect($estimate['provider_cost']['max'])->toBe('0.373875000');
});

test('refuses to guess when the pricing payload is incomplete', function () {
    expect(fn () => app(PricingCalculator::class)->estimate(
        pricing: ['rates' => [], 'tokens' => []],
        prompt: 'A lighthouse',
        size: '1024x1024',
        quality: 'medium',
    ))->toThrow(InvalidArgumentException::class);
});

test('refuses to guess when the rates payload is not a list of rates', function () {
    $pricing = pixelForgePricing();
    $pricing['rates'] = 'n/a';

    expect(fn () => app(PricingCalculator::class)->estimate(
        pricing: $pricing,
        prompt: 'A lighthouse',
        size: '1024x1024',
        quality: 'medium',
    ))->toThrow(InvalidArgumentException::class, 'missing its rates');
});

test('refuses to guess when a single rate is missing', function () {
    $pricing = pixelForgePricing();
    $pricing['rates'] = ['input_text_tokens' => '5.00'];

    expect(fn () => app(PricingCalculator::class)->estimate(
        pricing: $pricing,
        prompt: 'A lighthouse',
        size: '1024x1024',
        quality: 'medium',
    ))->toThrow(InvalidArgumentException::class, 'missing the input_image_tokens rate');
});

test('computes the actual cost from the usage and the frozen rates', function () {
    $cost = app(PricingCalculator::class)->actualNano(
        usage: [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ],
        rates: [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
    );

    expect($cost)->toBe([
        'provider' => 305_510_000,
        'price' => 381_887_500,
    ]);
});

test('returns no actual cost when the usage or rates are incomplete', function () {
    $calculator = app(PricingCalculator::class);

    $usage = [
        'input_text_tokens' => 12,
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
    ];

    $rates = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    expect($calculator->actualNano(['input_text_tokens' => 12], $rates))->toBeNull()
        ->and($calculator->actualNano($usage, ['input_text_tokens' => '5.00']))->toBeNull();
});
