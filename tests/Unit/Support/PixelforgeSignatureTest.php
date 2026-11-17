<?php

use App\Support\PixelforgeSignature;

test('accepts a valid signature', function () {
    $body = '{"id":"evt_1","type":"ping"}';
    $now = 1762265009;
    $header = 't='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, 'whsec_test_secret');

    expect((new PixelforgeSignature('whsec_test_secret'))->isValid($header, $body, $now))->toBeTrue();
});

test('rejects a body that changed after signing', function () {
    $now = 1762265009;
    $header = 't='.$now.',v1='.hash_hmac('sha256', $now.'.{"id":"evt_1"}', 'whsec_test_secret');

    expect((new PixelforgeSignature('whsec_test_secret'))->isValid($header, '{"id":"evt_2"}', $now))->toBeFalse();
});

test('rejects a signature made with another secret', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;
    $header = 't='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, 'whsec_other_secret');

    expect((new PixelforgeSignature('whsec_test_secret'))->isValid($header, $body, $now))->toBeFalse();
});

test('rejects a timestamp older than the tolerance', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;

    expect((new PixelforgeSignature('whsec_test_secret', 300))->isValid(pixelforgeSignatureHeader($body, $now - 301, 'whsec_test_secret'), $body, $now))->toBeFalse();
});

test('rejects a timestamp newer than the tolerance', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;

    expect((new PixelforgeSignature('whsec_test_secret', 300))->isValid(pixelforgeSignatureHeader($body, $now + 301, 'whsec_test_secret'), $body, $now))->toBeFalse();
});

test('accepts a timestamp exactly on the tolerance edge', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;

    expect((new PixelforgeSignature('whsec_test_secret', 300))->isValid(pixelforgeSignatureHeader($body, $now - 300, 'whsec_test_secret'), $body, $now))->toBeTrue();
});

test('parses signature parts in any order and ignores extra parts', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;
    $signature = hash_hmac('sha256', $now.'.'.$body, 'whsec_test_secret');
    $header = "v0=ignored,v1={$signature},t={$now}";

    expect((new PixelforgeSignature('whsec_test_secret'))->isValid($header, $body, $now))->toBeTrue();
});

test('rejects a malformed signature header', function (string $header) {
    expect((new PixelforgeSignature('whsec_test_secret'))->isValid($header, '{"id":"evt_1"}', 1762265009))->toBeFalse();
})->with([
    'empty version' => ['t=1762265009,v1='],
    'non numeric timestamp' => ['t=abc,v1=deadbeef'],
    'missing timestamp' => ['v1=deadbeef'],
    'missing version' => ['t=1762265009'],
    'no parts' => ['garbage'],
    'empty header' => [''],
]);

test('fails closed when the secret is empty', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;
    $header = 't='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, '');

    expect((new PixelforgeSignature(''))->isValid($header, $body, $now))->toBeFalse();
});

test('signs the timestamp exactly as it was sent', function () {
    $body = '{"id":"evt_1"}';
    $now = 1762265009;
    $timestamp = '0'.$now;
    $paddedHeader = "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_secret');
    $castHeader = "t={$timestamp},v1=".hash_hmac('sha256', ((int) $timestamp).'.'.$body, 'whsec_test_secret');

    $signature = new PixelforgeSignature('whsec_test_secret');

    expect($signature->isValid($paddedHeader, $body, $now))->toBeTrue()
        ->and($signature->isValid($castHeader, $body, $now))->toBeFalse();
});

test('reads the secret and tolerance from config', function () {
    config([
        'services.pixelforge.webhook_secret' => 'whsec_from_config',
        'services.pixelforge.webhook_tolerance' => 10,
    ]);

    $body = '{"id":"evt_1"}';
    $now = 1762265009;
    $signature = PixelforgeSignature::fromConfig();

    expect($signature->isValid(pixelforgeSignatureHeader($body, $now, 'whsec_from_config'), $body, $now))->toBeTrue()
        ->and($signature->isValid(pixelforgeSignatureHeader($body, $now - 11, 'whsec_from_config'), $body, $now))->toBeFalse();
});
