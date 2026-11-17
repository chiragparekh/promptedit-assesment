<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use App\Models\Generation;
use App\Models\User;
use App\Services\PricingCalculator;
use App\Services\PricingService;
use InvalidArgumentException;

class CreateGeneration
{
    public function __construct(
        private readonly PricingCalculator $calculator,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Store the generation locally before asking the provider for it, so a webhook
     * that arrives before the create response still has a record to update.
     *
     * @param  array<int, string>  $referenceImages
     *
     * @throws PixelForgeException
     */
    public function handle(
        User $user,
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = [],
    ): Generation {
        $generation = $user->generations()->create([
            'status' => 'queued',
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'reference_images' => $referenceImages,
        ]);

        try {
            $payload = PixelForge::createGeneration(
                prompt: $prompt,
                size: $size,
                quality: $quality,
                referenceImages: $referenceImages,
                metadata: ['generation_id' => $generation->id],
                idempotencyKey: $generation->id,
            );
        } catch (PixelForgeException $exception) {
            if ($exception->status !== null) {
                $generation->delete();
            }

            throw $exception;
        }

        $generation->refresh();

        if ($generation->status !== GenerationStatus::Queued) {
            if ($generation->provider_id === null && is_string($payload['id'] ?? null)) {
                $generation->update(['provider_id' => $payload['id']]);
            }
        } else {
            $generation->update(Generation::providerAttributes($payload));
        }

        $this->recordEstimate($generation, $payload);

        return $generation;
    }

    /**
     * Record what we quoted the user, priced at the rates the provider locked in.
     * A missing or malformed pricing payload must not stop the generation itself,
     * so the estimate is simply left unset.
     *
     * @param  array<string, mixed>  $payload  The provider's generation object.
     */
    private function recordEstimate(Generation $generation, array $payload): void
    {
        $pricing = $this->pricing->latest();
        $rates = data_get($payload, 'rates');

        if ($pricing === null || ! is_array($rates)) {
            return;
        }

        try {
            $estimate = $this->calculator->estimateNano(
                pricing: ['rates' => $rates, 'tokens' => data_get($pricing, 'tokens', [])],
                prompt: $generation->prompt,
                size: $generation->size,
                quality: $generation->quality,
                referenceImages: $generation->reference_images ?? [],
            );
        } catch (InvalidArgumentException) {
            return;
        }

        $generation->update([
            'estimated_provider_cost_nano' => $estimate['provider_cost']['estimate'],
            'estimated_price_nano' => $estimate['price']['estimate'],
        ]);
    }
}
