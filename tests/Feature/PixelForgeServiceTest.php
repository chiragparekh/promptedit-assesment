<?php

use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use App\Services\PixelForgeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.timeout' => 30,
    ]);
});

test('creates a generation with the expected payload and headers', function () {
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
            'object' => 'generation',
            'status' => 'queued',
        ], 202),
    ]);

    $generation = app(PixelForgeService::class)->createGeneration(
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1536x1024',
        quality: 'high',
        referenceImages: ['https://example.com/style.png'],
        metadata: ['order_id' => '1042'],
    );

    expect($generation['id'])->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation['status'])->toBe('queued');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://pixelforge.test/v1/generations'
            && $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && $request->hasHeader('Idempotency-Key')
            && $request['prompt'] === 'A lighthouse on a cliff at dawn, oil painting'
            && $request['size'] === '1536x1024'
            && $request['quality'] === 'high'
            && $request['reference_images'] === ['https://example.com/style.png']
            && $request['metadata'] === ['order_id' => '1042'];
    });
});

test('omits optional fields that were not provided', function () {
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response(['id' => 'gen_123'], 202),
    ]);

    app(PixelForgeService::class)->createGeneration('A simple prompt');

    Http::assertSent(function (Request $request): bool {
        return $request['prompt'] === 'A simple prompt'
            && $request['size'] === '1024x1024'
            && $request['quality'] === 'medium'
            && ! isset($request['reference_images'])
            && ! isset($request['metadata']);
    });
});

test('uses the supplied idempotency key', function () {
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response(['id' => 'gen_123'], 202),
    ]);

    app(PixelForgeService::class)->createGeneration('A prompt', idempotencyKey: 'order-1042');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', 'order-1042'));
});

test('retrieves a generation by id', function () {
    Http::fake([
        'pixelforge.test/v1/generations/gen_123' => Http::response([
            'id' => 'gen_123',
            'status' => 'succeeded',
        ]),
    ]);

    $generation = app(PixelForgeService::class)->getGeneration('gen_123');

    expect($generation['status'])->toBe('succeeded');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://pixelforge.test/v1/generations/gen_123');
});

test('lists generations with query parameters', function () {
    Http::fake([
        'pixelforge.test/v1/generations*' => Http::response([
            'object' => 'list',
            'data' => [],
            'has_more' => false,
        ]),
    ]);

    $list = app(PixelForgeService::class)->listGenerations(limit: 2, status: 'succeeded', cursor: 'abc');

    expect($list['object'])->toBe('list');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://pixelforge.test/v1/generations?')
            && str_contains($request->url(), 'limit=2')
            && str_contains($request->url(), 'status=succeeded')
            && str_contains($request->url(), 'cursor=abc');
    });
});

test('fetches pricing without authentication', function () {
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response([
            'object' => 'pricing',
            'currency' => 'usd',
        ]),
    ]);

    $pricing = app(PixelForgeService::class)->getPricing();

    expect($pricing['currency'])->toBe('usd');

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
});

test('fetches usage for a date range', function () {
    Http::fake([
        'pixelforge.test/v1/usage*' => Http::response([
            'object' => 'usage',
            'data' => [],
        ]),
    ]);

    app(PixelForgeService::class)->getUsage(
        CarbonImmutable::parse('2026-11-01 23:30', 'America/New_York'),
        CarbonImmutable::parse('2026-11-02'),
    );

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && str_contains($request->url(), 'start_date=2026-11-02')
            && str_contains($request->url(), 'end_date=2026-11-02');
    });
});

test('throws a parsed exception for api errors', function () {
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'validation_failed',
                'message' => 'The selected size is invalid.',
                'param' => 'size',
                'errors' => ['size' => ['The size must be one of: 1024x1024, 1536x1024, 1024x1536.']],
            ],
        ], 422),
    ]);

    try {
        app(PixelForgeService::class)->createGeneration('A prompt', size: 'bogus');
        throw new LogicException('Expected a PixelForgeException to be thrown.');
    } catch (PixelForgeException $exception) {
        expect($exception->status)->toBe(422)
            ->and($exception->errorCode)->toBe('validation_failed')
            ->and($exception->errorType)->toBe('invalid_request_error')
            ->and($exception->param)->toBe('size')
            ->and($exception->errors['size'])->toHaveCount(1)
            ->and($exception->isRetryable())->toBeFalse();
    }
});

test('retries rate limited requests respecting retry after', function () {
    Http::fake([
        'pixelforge.test/v1/generations/gen_123' => Http::sequence()
            ->push(['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Too many requests.']], 429, ['Retry-After' => '0'])
            ->push(['id' => 'gen_123', 'status' => 'processing'], 200),
    ]);

    $generation = app(PixelForgeService::class)->getGeneration('gen_123');

    expect($generation['status'])->toBe('processing');
});

test('gives up after the maximum number of attempts', function () {
    Http::fake([
        'pixelforge.test/v1/generations/gen_123' => Http::response([
            'error' => ['code' => 'overloaded', 'message' => 'PixelForge is temporarily overloaded.'],
        ], 503, ['Retry-After' => '0']),
    ]);

    expect(fn () => app(PixelForgeService::class, ['maxAttempts' => 2])->getGeneration('gen_123'))
        ->toThrow(PixelForgeException::class);

    Http::assertSentCount(2);
});

test('is registered as a singleton', function () {
    expect(app(PixelForgeService::class))->toBe(app(PixelForgeService::class));
});

test('facade proxies to the service singleton', function () {
    Http::fake([
        'pixelforge.test/v1/pricing' => Http::response(['object' => 'pricing', 'currency' => 'usd']),
    ]);

    $pricing = PixelForge::getPricing();

    expect($pricing['currency'])->toBe('usd')
        ->and(PixelForge::getFacadeRoot())->toBe(app(PixelForgeService::class));
});

test('throws when the api key is not configured', function () {
    config(['services.pixelforge.api_key' => null]);

    expect(fn () => app(PixelForgeService::class)->getGeneration('gen_123'))
        ->toThrow(PixelForgeException::class, 'A PixelForge API key is not configured.');
});
