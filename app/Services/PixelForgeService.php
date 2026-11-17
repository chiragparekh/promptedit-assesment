<?php

namespace App\Services;

use App\Exceptions\PixelForgeException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PixelForgeService
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
        private readonly int $maxAttempts = 3,
    ) {}

    /**
     * @param  array<int, string>  $referenceImages
     * @param  array<string, string>  $metadata
     * @return array<string, mixed>
     */
    public function createGeneration(
        string $prompt,
        string $size = '1024x1024',
        string $quality = 'medium',
        array $referenceImages = [],
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): array {
        $payload = array_filter([
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages === [] ? null : array_values($referenceImages),
            'metadata' => $metadata === [] ? null : $metadata,
        ], fn (mixed $value): bool => $value !== null);

        return $this->request('post', '/generations', $payload, [
            'Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGeneration(string $id): array
    {
        return $this->request('get', '/generations/'.rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function listGenerations(
        int $limit = 20,
        ?string $status = null,
        ?string $cursor = null,
    ): array {
        $query = array_filter([
            'limit' => $limit,
            'status' => $status,
            'cursor' => $cursor,
        ], fn (mixed $value): bool => $value !== null);

        return $this->request('get', '/generations', $query);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPricing(): array
    {
        return $this->request('get', '/pricing', authenticated: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(?CarbonImmutable $startDate = null, ?CarbonImmutable $endDate = null): array
    {
        $query = array_filter([
            'start_date' => $startDate?->utc()->toDateString(),
            'end_date' => $endDate?->utc()->toDateString(),
        ], fn (mixed $value): bool => $value !== null);

        return $this->request('get', '/usage', $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws PixelForgeException
     */
    private function request(
        string $method,
        string $path,
        array $payload = [],
        array $headers = [],
        bool $authenticated = true,
    ): array {
        $lastException = null;

        for ($attempt = 1; $attempt <= max(1, $this->maxAttempts); $attempt++) {
            try {
                $response = $this->send($method, $path, $payload, $headers, $authenticated);
            } catch (ConnectionException $exception) {
                $lastException = PixelForgeException::connection($exception);

                if ($attempt < $this->maxAttempts) {
                    sleep($attempt);

                    continue;
                }

                throw $lastException;
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $exception = PixelForgeException::fromResponse($response);

            if (! $exception->isRetryable() || $attempt >= $this->maxAttempts) {
                throw $exception;
            }

            $lastException = $exception;
            sleep($exception->retryAfter ?? $attempt);
        }

        throw $lastException;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     *
     * @throws ConnectionException
     */
    private function send(
        string $method,
        string $path,
        array $payload,
        array $headers,
        bool $authenticated,
    ): Response {
        $client = $this->client($authenticated)->withHeaders($headers);

        return $method === 'post'
            ? $client->post($path, $payload)
            : $client->get($path, $payload);
    }

    private function client(bool $authenticated): PendingRequest
    {
        $client = Http::baseUrl($this->resolveBaseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout($this->resolveTimeout());

        return $authenticated ? $client->withToken($this->resolveApiKey()) : $client;
    }

    /**
     * @throws PixelForgeException
     */
    private function resolveApiKey(): string
    {
        $apiKey = $this->apiKey ?? config('services.pixelforge.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw PixelForgeException::missingApiKey();
        }

        return $apiKey;
    }

    private function resolveBaseUrl(): string
    {
        $baseUrl = $this->baseUrl ?? config('services.pixelforge.base_url', 'https://pixelforge.promptedit.dev/v1');

        return rtrim((string) $baseUrl, '/');
    }

    private function resolveTimeout(): int
    {
        return $this->timeout ?? (int) config('services.pixelforge.timeout', 30);
    }
}
