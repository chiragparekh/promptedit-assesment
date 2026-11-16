<?php

namespace App\Actions;

use App\Facades\PixelForge;

class CreateGeneration
{
    /**
     * @param  array<int, string>  $referenceImages
     * @return array<string, mixed>
     */
    public function handle(
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = [],
    ): array {
        return PixelForge::createGeneration(
            prompt: $prompt,
            size: $size,
            quality: $quality,
            referenceImages: $referenceImages,
        );
    }
}
