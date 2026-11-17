<?php

use App\Actions\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Exceptions\PixelForgeException;
use App\Models\Generation;
use App\Models\User;
use App\Services\PixelForgeService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.timeout' => 30,
    ]);
});

test('creates the local row before calling the provider', function () {
    Http::preventStrayRequests();
    Http::fake(function () {
        $generation = Generation::sole();

        expect($generation->provider_id)->toBeNull()
            ->and($generation->status)->toBe(GenerationStatus::Queued)
            ->and($generation->metadata)->toBeNull();

        return Http::response(pixelForgeGeneration(), 202);
    });

    app(CreateGeneration::class)->handle(
        user: User::factory()->create(),
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1024x1024',
        quality: 'medium',
    );

    expect(Generation::count())->toBe(1);
});

test('stores the provider snapshot and links the request to the local row', function () {
    Http::preventStrayRequests();
    $providerGeneration = pixelForgeGeneration();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response($providerGeneration, 202),
    ]);

    $user = User::factory()->create();

    $generation = app(CreateGeneration::class)->handle(
        user: $user,
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1536x1024',
        quality: 'high',
        referenceImages: ['https://example.com/style.png'],
    );

    expect($generation->user_id)->toBe($user->id)
        ->and($generation->provider_id)->toBe($providerGeneration['id'])
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->prompt)->toBe('A lighthouse on a cliff at dawn, oil painting')
        ->and($generation->size)->toBe('1536x1024')
        ->and($generation->quality)->toBe('high')
        ->and($generation->reference_images)->toBe(['https://example.com/style.png'])
        ->and($generation->input_text_tokens_rate_cents)->toBe(500)
        ->and($generation->output_tokens_rate_cents)->toBe(4000)
        ->and($generation->accepted_at->toDateTimeString())->toBe('2026-11-04 14:03:11');

    Http::assertSent(function (Request $request) use ($generation): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://pixelforge.test/v1/generations'
            && $request->hasHeader('Idempotency-Key', $generation->id)
            && $request['metadata'] === ['generation_id' => $generation->id];
    });
});

test('keeps the local row when the provider is unreachable', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection timed out.'));

    $this->app->instance(PixelForgeService::class, new PixelForgeService(
        apiKey: 'pf_test_key',
        baseUrl: 'https://pixelforge.test/v1',
        timeout: 30,
        maxAttempts: 1,
    ));

    expect(fn () => app(CreateGeneration::class)->handle(
        user: User::factory()->create(),
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1024x1024',
        quality: 'medium',
    ))->toThrow(PixelForgeException::class);

    $generation = Generation::sole();

    expect($generation->provider_id)->toBeNull()
        ->and($generation->status)->toBe(GenerationStatus::Queued);
});

test('deletes the local row when the provider rejects the request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'validation_failed',
                'message' => 'The selected size is invalid.',
            ],
        ], 422),
    ]);

    expect(fn () => app(CreateGeneration::class)->handle(
        user: User::factory()->create(),
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '2048x2048',
        quality: 'medium',
    ))->toThrow(PixelForgeException::class);

    expect(Generation::count())->toBe(0);
});

test('does not overwrite a state the webhook already advanced', function () {
    Http::preventStrayRequests();
    $providerGeneration = pixelForgeGeneration();

    Http::fake(function () use ($providerGeneration) {
        Generation::sole()->update([
            'provider_id' => $providerGeneration['id'],
            'status' => 'processing',
            'started_at' => '2026-11-04T14:03:13Z',
        ]);

        return Http::response($providerGeneration, 202);
    });

    $generation = app(CreateGeneration::class)->handle(
        user: User::factory()->create(),
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1024x1024',
        quality: 'medium',
    );

    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->started_at)->not->toBeNull()
        ->and($generation->completed_at)->toBeNull();
});

test('fills the provider id when the webhook advanced the status without it', function () {
    Http::preventStrayRequests();
    $providerGeneration = pixelForgeGeneration();

    Http::fake(function () use ($providerGeneration) {
        Generation::sole()->update([
            'status' => 'processing',
            'started_at' => '2026-11-04T14:03:13Z',
        ]);

        return Http::response($providerGeneration, 202);
    });

    $generation = app(CreateGeneration::class)->handle(
        user: User::factory()->create(),
        prompt: 'A lighthouse on a cliff at dawn, oil painting',
        size: '1024x1024',
        quality: 'medium',
    );

    expect($generation->provider_id)->toBe($providerGeneration['id'])
        ->and($generation->status)->toBe(GenerationStatus::Processing);
});
