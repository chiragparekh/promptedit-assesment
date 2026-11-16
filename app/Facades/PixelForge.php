<?php

namespace App\Facades;

use App\Services\PixelForgeService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, mixed> createGeneration(string $prompt, string $size = '1024x1024', string $quality = 'medium', array<int, string> $referenceImages = [], array<string, string> $metadata = [], ?string $idempotencyKey = null)
 * @method static array<string, mixed> getGeneration(string $id)
 * @method static array<string, mixed> listGenerations(int $limit = 20, ?string $status = null, ?string $cursor = null)
 * @method static array<string, mixed> getPricing()
 * @method static array<string, mixed> getUsage(?\Carbon\CarbonImmutable $startDate = null, ?\Carbon\CarbonImmutable $endDate = null)
 *
 * @see PixelForgeService
 */
class PixelForge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PixelForgeService::class;
    }
}
