<?php

namespace App\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class PixelForgeException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $param = null,
        public readonly array $errors = [],
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status ?? 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        /** @var array{type?: string, code?: string, message?: string, param?: string, errors?: array<string, array<int, string>>} $error */
        $error = $response->json('error', []);

        return new self(
            $error['message'] ?? "PixelForge request failed with status {$response->status()}.",
            $response->status(),
            $error['code'] ?? null,
            $error['type'] ?? null,
            $error['param'] ?? null,
            $error['errors'] ?? [],
            self::retryAfterSeconds($response),
        );
    }

    public static function connection(ConnectionException $exception): self
    {
        return new self(
            'Unable to reach PixelForge: '.$exception->getMessage(),
            previous: $exception,
        );
    }

    public static function missingApiKey(): self
    {
        return new self('A PixelForge API key is not configured. Set PIXELFORGE_API_KEY.');
    }

    public function isRetryable(): bool
    {
        return in_array($this->status, [429, 503], true);
    }

    public static function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }
}
