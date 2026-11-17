<?php

use App\Enums\GenerationStatus;
use App\Jobs\ApplyPixelforgeEvent;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 */
function localGeneration(array $overrides = []): Generation
{
    return Generation::create(array_replace([
        'user_id' => User::factory()->create()->id,
        'status' => 'queued',
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1024x1024',
        'quality' => 'medium',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function providerGenerationObject(Generation $generation, array $overrides = []): array
{
    return array_replace([
        'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => 'processing',
        'prompt' => $generation->prompt,
        'size' => $generation->size,
        'quality' => $generation->quality,
        'reference_images' => [],
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => '2026-11-04T14:03:13Z',
        'completed_at' => null,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $objectOverrides
 */
function receivedWebhookEvent(Generation $generation, string $type, array $objectOverrides = []): WebhookEvent
{
    $payload = [
        'id' => 'evt_'.Str::lower(Str::random(10)),
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => providerGenerationObject($generation, $objectOverrides)],
    ];

    return WebhookEvent::create([
        'provider_event_id' => $payload['id'],
        'type' => $payload['type'],
        'payload' => $payload,
    ]);
}

test('applies a processing event', function () {
    $generation = localGeneration();
    $event = receivedWebhookEvent($generation, 'generation.processing');

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->started_at)->not->toBeNull()
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

test('applies a succeeded event with usage and rates', function () {
    $generation = localGeneration();
    $event = receivedWebhookEvent($generation, 'generation.succeeded', [
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png',
        'image_url_expires_at' => '2026-11-05T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ],
        'completed_at' => '2026-11-04T14:03:29Z',
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->output_tokens)->toBe(6450)
        ->and($generation->total_tokens)->toBe(10411)
        ->and($generation->output_tokens_rate_cents)->toBe(4000)
        ->and($generation->image_url)->not->toBeNull()
        ->and($generation->completed_at)->not->toBeNull()
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

test('applies a failed event', function () {
    $generation = localGeneration();
    $event = receivedWebhookEvent($generation, 'generation.failed', [
        'status' => 'failed',
        'error' => ['code' => 'generation_failed', 'message' => 'The model could not draw this.'],
        'completed_at' => '2026-11-04T14:03:29Z',
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->error['code'])->toBe('generation_failed')
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');
});

test('ignores an earlier event that arrives after a final state', function () {
    $generation = localGeneration([
        'status' => 'succeeded',
        'provider_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'total_tokens' => 10411,
    ]);

    $event = receivedWebhookEvent($generation, 'generation.processing', ['usage' => null]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->total_tokens)->toBe(10411)
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

test('does not apply a second succeeded event twice', function () {
    $generation = localGeneration([
        'status' => 'succeeded',
        'provider_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'total_tokens' => 10411,
    ]);

    $event = receivedWebhookEvent($generation, 'generation.succeeded', [
        'status' => 'succeeded',
        'usage' => ['total_tokens' => 1],
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    expect($generation->refresh()->total_tokens)->toBe(10411);
});

test('matches a generation through metadata before the provider id is known', function () {
    $generation = localGeneration();

    expect($generation->provider_id)->toBeNull();

    $event = receivedWebhookEvent($generation, 'generation.succeeded', [
        'status' => 'succeeded',
        'usage' => ['total_tokens' => 10411],
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $generation->refresh();

    expect($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->status)->toBe(GenerationStatus::Succeeded);
});

test('matches a generation through the provider id when metadata is absent', function () {
    $generation = localGeneration(['provider_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K']);

    $event = receivedWebhookEvent($generation, 'generation.processing', ['metadata' => []]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Processing);
});

test('records an unknown generation and leaves the event unprocessed', function () {
    $payload = [
        'id' => 'evt_orphan',
        'object' => 'event',
        'type' => 'generation.processing',
        'data' => ['object' => [
            'id' => 'gen_missing',
            'status' => 'processing',
            'metadata' => ['generation_id' => (string) Str::uuid()],
        ]],
    ];

    $event = WebhookEvent::create([
        'provider_event_id' => $payload['id'],
        'type' => $payload['type'],
        'payload' => $payload,
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $event->refresh();

    expect($event->processed_at)->toBeNull()
        ->and($event->exception['code'])->toBe('generation_not_found');
});

test('marks a ping event processed without touching a generation', function () {
    $payload = [
        'id' => 'evt_ping',
        'object' => 'event',
        'type' => 'ping',
        'data' => ['object' => ['object' => 'ping', 'message' => 'Hello from Pixelforge.']],
    ];

    $event = WebhookEvent::create([
        'provider_event_id' => $payload['id'],
        'type' => $payload['type'],
        'payload' => $payload,
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    expect($event->refresh()->processed_at)->not->toBeNull()
        ->and($event->exception)->toBeNull();
});

test('marks an unknown event type processed without matching a generation', function () {
    $payload = [
        'id' => 'evt_account',
        'object' => 'event',
        'type' => 'account.updated',
        'data' => ['object' => ['object' => 'account', 'name' => 'Local']],
    ];

    $event = WebhookEvent::create([
        'provider_event_id' => $payload['id'],
        'type' => $payload['type'],
        'payload' => $payload,
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    expect($event->refresh()->processed_at)->not->toBeNull()
        ->and($event->exception)->toBeNull();
});

test('records a generation event with no object and leaves it unprocessed', function () {
    $payload = [
        'id' => 'evt_broken',
        'object' => 'event',
        'type' => 'generation.succeeded',
        'data' => [],
    ];

    $event = WebhookEvent::create([
        'provider_event_id' => $payload['id'],
        'type' => $payload['type'],
        'payload' => $payload,
    ]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $event->refresh();

    expect($event->processed_at)->toBeNull()
        ->and($event->exception['code'])->toBe('invalid_payload');
});

test('skips an event that was already processed', function () {
    $generation = localGeneration();
    $event = receivedWebhookEvent($generation, 'generation.processing');
    $event->update(['processed_at' => now()]);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});

test('records an unrecognised status and leaves the event unprocessed', function () {
    $generation = localGeneration();
    $event = receivedWebhookEvent($generation, 'generation.processing', ['status' => 'bogus']);

    ApplyPixelforgeEvent::dispatchSync($event->id);

    $event->refresh();

    expect($event->processed_at)->toBeNull()
        ->and($event->exception['code'])->toBe('unknown_status')
        ->and($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});
