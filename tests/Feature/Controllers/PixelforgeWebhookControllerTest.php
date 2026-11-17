<?php

use App\Jobs\ApplyPixelforgeEvent;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.pixelforge.webhook_secret' => 'whsec_test_secret',
        'services.pixelforge.webhook_tolerance' => 300,
    ]);

    Queue::fake();
});

test('stores the event with its delivery headers', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()), [
        'Pixelforge-Event-Id' => 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A',
        'Pixelforge-Event-Type' => 'generation.succeeded',
        'Pixelforge-Delivery-Attempt' => '1',
    ])->assertNoContent();

    $event = WebhookEvent::sole();

    expect($event->provider_event_id)->toBe('evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A')
        ->and($event->type)->toBe('generation.succeeded')
        ->and($event->payload['data']['object']['id'])->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($event->headers['pixelforge-delivery-attempt'])->toBe('1')
        ->and($event->processed_at)->toBeNull();

    Queue::assertPushed(
        ApplyPixelforgeEvent::class,
        fn (ApplyPixelforgeEvent $job): bool => $job->webhookEventId === $event->id,
    );
});

test('uses the payload id over the delivery header id', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()), [
        'Pixelforge-Event-Id' => 'evt_from_header',
    ])->assertNoContent();

    expect(WebhookEvent::sole()->provider_event_id)->toBe('evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A');
});

test('stores an event without delivery headers', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()))->assertNoContent();

    expect(WebhookEvent::sole()->headers)->toBe([]);
});

test('stores a duplicate delivery once', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()))->assertNoContent();
    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time() + 1))->assertNoContent();

    expect(WebhookEvent::count())->toBe(1);

    Queue::assertPushedTimes(ApplyPixelforgeEvent::class, 1);
});

test('keeps the first delivery when a duplicate carries different data', function () {
    $first = pixelforgeEventBody(['type' => 'generation.processing']);
    $second = pixelforgeEventBody(['type' => 'generation.succeeded']);

    sendPixelforgeWebhook($this, $first, pixelforgeSignatureHeader($first, time()))->assertNoContent();
    sendPixelforgeWebhook($this, $second, pixelforgeSignatureHeader($second, time()))->assertNoContent();

    expect(WebhookEvent::sole()->type)->toBe('generation.processing');
});

test('rejects malformed events after signature verification', function (string $payload) {
    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()))
        ->assertBadRequest();

    expect(WebhookEvent::count())->toBe(0);

    Queue::assertNothingPushed();
})->with([
    'invalid json' => ['{"id":'],
    'missing id' => ['{"type":"ping"}'],
    'missing type' => ['{"id":"evt_123"}'],
    'non string type' => ['{"id":"evt_123","type":123}'],
    'empty object' => ['{}'],
]);

test('stores a ping event', function () {
    $payload = pixelforgeEventBody(['type' => 'ping', 'data' => []]);

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()))->assertNoContent();

    $event = WebhookEvent::sole();

    expect($event->type)->toBe('ping');

    Queue::assertPushed(
        ApplyPixelforgeEvent::class,
        fn (ApplyPixelforgeEvent $job): bool => $job->webhookEventId === $event->id,
    );
});
