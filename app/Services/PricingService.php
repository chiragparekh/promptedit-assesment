<?php

namespace App\Services;

use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    private const CACHE_KEY = 'pixelforge.pricing';

    /**
     * Pixelforge's published rates and token rules, as returned by GET /v1/pricing.
     *
     * Cached for an hour: rates rarely change, and every dashboard load needs them.
     * A failed lookup is remembered for a minute so a provider outage doesn't turn
     * into a retry storm on every request. Callers get null and can simply hide the
     * estimate; billing never depends on this payload.
     *
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        try {
            $pricing = PixelForge::getPricing();
        } catch (PixelForgeException) {
            Cache::put(self::CACHE_KEY, [], now()->addMinute());

            return null;
        }

        Cache::put(self::CACHE_KEY, $pricing, now()->addHour());

        return $pricing;
    }
}
