<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\User;
use App\Services\PricingCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationSyncController extends Controller
{
    /**
     * The current state of the given generations, for rows the dashboard is still
     * waiting on. Webhooks change these rows in the background; the page polls this
     * so it can swap a spinner for the finished image without a reload.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ids = array_values(array_filter(
            is_array($request->query('ids')) ? $request->query('ids') : [],
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));

        if ($ids === []) {
            return response()->json(['data' => []]);
        }

        $generations = $user->generations()
            ->whereIn('provider_id', array_slice($ids, 0, 100))
            ->get();

        return response()->json([
            'data' => $generations->map(static fn (Generation $generation): array => [
                'provider_id' => $generation->provider_id,
                'status' => $generation->status->value,
                'image_url' => $generation->image_url,
                'image_url_expires_at' => $generation->image_url_expires_at?->toIso8601String(),
                'error' => $generation->error,
                'estimated_cost' => $generation->estimated_price_nano !== null
                    ? PricingCalculator::formatUsd($generation->estimated_price_nano)
                    : null,
                'actual_cost' => $generation->actual_price_nano !== null
                    ? PricingCalculator::formatUsd($generation->actual_price_nano)
                    : null,
                'completed_at' => $generation->completed_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
