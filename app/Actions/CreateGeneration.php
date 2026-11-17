<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use App\Models\Generation;
use App\Models\User;

class CreateGeneration
{
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

            return $generation;
        }

        $generation->update(Generation::providerAttributes($payload));

        return $generation;
    }
}
