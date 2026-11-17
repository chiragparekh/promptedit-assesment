<?php

use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.pixelforge.webhook_secret' => 'whsec_test_secret',
        'services.pixelforge.webhook_tolerance' => 300,
    ]);

    Queue::fake();
});

test('accepts a correctly signed event', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time()))
        ->assertNoContent();
});

test('accepts signature parts in any order', function () {
    $payload = pixelforgeEventBody();
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

    sendPixelforgeWebhook($this, $payload, "v1={$signature},t={$timestamp}")
        ->assertNoContent();
});

test('rejects a body that changed after signing', function () {
    $payload = pixelforgeEventBody();
    $signature = pixelforgeSignatureHeader($payload, time());

    sendPixelforgeWebhook($this, $payload.' ', $signature)->assertBadRequest();
});

test('rejects a signature made with another secret', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time(), 'whsec_other_secret'))
        ->assertBadRequest();
});

test('rejects a request without a signature header', function () {
    sendPixelforgeWebhook($this, pixelforgeEventBody(), null)->assertBadRequest();
});

test('rejects a stale timestamp', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time() - 301))
        ->assertBadRequest();
});

test('rejects a timestamp too far in the future', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time() + 301))
        ->assertBadRequest();
});

test('accepts a timestamp inside the tolerance', function () {
    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time() - 299))
        ->assertNoContent();
});

test('verifies the exact raw body that arrived', function () {
    $raw = "{\n  \"id\": \"evt_123\",\n  \"type\": \"ping\"\n}";

    sendPixelforgeWebhook($this, $raw, pixelforgeSignatureHeader($raw, time()))->assertNoContent();

    $compact = (string) json_encode(json_decode($raw, true), JSON_THROW_ON_ERROR);

    sendPixelforgeWebhook($this, $raw, pixelforgeSignatureHeader($compact, time()))->assertBadRequest();
});

test('rejects a malformed signature header', function (string $signature) {
    sendPixelforgeWebhook($this, pixelforgeEventBody(), $signature)->assertBadRequest();
})->with([
    'empty version' => ['t=1762265009,v1='],
    'non numeric timestamp' => ['t=abc,v1=deadbeef'],
    'missing timestamp' => ['v1=deadbeef'],
    'missing version' => ['t=1762265009'],
    'no parts' => ['garbage'],
]);

test('fails closed when no webhook secret is configured', function () {
    config(['services.pixelforge.webhook_secret' => '']);

    $payload = pixelforgeEventBody();

    sendPixelforgeWebhook($this, $payload, pixelforgeSignatureHeader($payload, time(), ''))
        ->assertBadRequest();
});
