<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Models\User;
use App\Services\PricingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\ScrollMetadata;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $cursor = $request->query('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $generationsError = null;

        try {
            $list = PixelForge::listGenerations(cursor: $cursor);
        } catch (PixelForgeException $exception) {
            $list = ['data' => [], 'next_cursor' => null];
            $generationsError = $exception->getMessage();
        }

        $nextCursor = is_string($list['next_cursor'] ?? null) ? $list['next_cursor'] : null;

        /** @var User $user */
        $user = $request->user();

        $list['data'] = $this->withCosts($user, is_array($list['data'] ?? null) ? $list['data'] : []);

        return Inertia::render('Dashboard', [
            'generations' => Inertia::scroll($list, metadata: new ScrollMetadata(
                pageName: 'cursor',
                previousPage: null,
                nextPage: $nextCursor,
                currentPage: $cursor,
            )),
            'generationsError' => $generationsError,
        ]);
    }

    /**
     * Attach the estimated and actual costs we recorded to the provider's list items.
     *
     * @param  array<int, array<string, mixed>>  $generations
     * @return array<int, array<string, mixed>>
     */
    private function withCosts(User $user, array $generations): array
    {
        $providerIds = array_values(array_filter(
            array_map(static fn (array $generation): mixed => $generation['id'] ?? null, $generations),
            static fn (mixed $id): bool => is_string($id),
        ));

        $costs = Generation::query()
            ->where('user_id', $user->id)
            ->whereIn('provider_id', $providerIds)
            ->get(['provider_id', 'estimated_price_nano', 'actual_price_nano'])
            ->keyBy('provider_id');

        return array_map(static function (array $generation) use ($costs): array {
            $cost = $costs->get($generation['id'] ?? '');

            return $generation + [
                'estimated_cost' => $cost?->estimated_price_nano !== null
                    ? PricingCalculator::formatUsd($cost->estimated_price_nano)
                    : null,
                'actual_cost' => $cost?->actual_price_nano !== null
                    ? PricingCalculator::formatUsd($cost->actual_price_nano)
                    : null,
            ];
        }, $generations);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $input = $request->safe();

        /** @var User $user */
        $user = $request->user();

        try {
            $createGeneration->handle(
                user: $user,
                prompt: $input->string('prompt')->toString(),
                size: $input->string('size')->toString(),
                quality: $input->string('quality')->toString(),
                referenceImages: array_map(
                    static fn (mixed $url): string => (string) $url,
                    array_values($input->array('reference_images')),
                ),
            );
        } catch (PixelForgeException $exception) {
            if ($exception->errors !== []) {
                throw ValidationException::withMessages($exception->errors);
            }

            if ($exception->errorCode === 'content_policy_violation') {
                throw ValidationException::withMessages(['prompt' => [$exception->getMessage()]]);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation queued.')]);

        return to_route('dashboard');
    }
}
