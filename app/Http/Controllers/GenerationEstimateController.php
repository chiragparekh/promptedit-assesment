<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateGenerationRequest;
use App\Services\PricingCalculator;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;

class GenerationEstimateController extends Controller
{
    /**
     * Estimate what a generation will cost, without creating one.
     */
    public function __invoke(
        EstimateGenerationRequest $request,
        PricingService $pricing,
        PricingCalculator $calculator,
    ): JsonResponse {
        $pricingPayload = $pricing->latest();

        if ($pricingPayload === null) {
            return response()->json([
                'error' => [
                    'code' => 'pricing_unavailable',
                    'message' => 'Pricing is unavailable right now. Try again shortly.',
                ],
            ], 503);
        }

        $input = $request->safe();

        return response()->json($calculator->estimate(
            pricing: $pricingPayload,
            prompt: $input->string('prompt')->toString(),
            size: $input->string('size')->toString(),
            quality: $input->string('quality')->toString(),
            referenceImages: array_map(
                static fn (mixed $url): string => (string) $url,
                array_values($input->array('reference_images')),
            ),
        ));
    }
}
