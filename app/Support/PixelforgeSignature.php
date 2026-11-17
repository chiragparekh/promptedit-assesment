<?php

namespace App\Support;

class PixelforgeSignature
{
    public function __construct(
        private readonly string $secret,
        private readonly int $tolerance = 300,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            secret: (string) config('services.pixelforge.webhook_secret', ''),
            tolerance: (int) config('services.pixelforge.webhook_tolerance', 300),
        );
    }

    public function isValid(?string $header, string $payload, int $now): bool
    {
        if ($header === null || $header === '' || $this->secret === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[trim($key)] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (! ctype_digit($timestamp) || $signature === '') {
            return false;
        }

        if (abs($now - (int) $timestamp) > $this->tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);

        return hash_equals($expected, $signature);
    }
}
