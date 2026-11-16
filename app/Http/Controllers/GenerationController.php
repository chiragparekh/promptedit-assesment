<?php

namespace App\Http\Controllers;

use App\Actions\CreateGeneration;
use App\Exceptions\PixelForgeException;
use App\Facades\PixelForge;
use App\Http\Requests\StoreGenerationRequest;
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

    public function store(StoreGenerationRequest $request, CreateGeneration $createGeneration): RedirectResponse
    {
        $input = $request->safe();

        try {
            $createGeneration->handle(
                prompt: $input->string('prompt')->toString(),
                size: $input->string('size')->toString(),
                quality: $input->string('quality')->toString(),
                referenceImages: array_map(
                    static fn (mixed $url): string => (string) $url,
                    array_values($input->array('reference_images')),
                ),
            );
        } catch (PixelForgeException $exception) {
            return $this->handleProviderFailure($exception);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Generation queued.')]);

        return to_route('dashboard');
    }

    private function handleProviderFailure(PixelForgeException $exception): RedirectResponse
    {
        if ($exception->errors !== []) {
            throw ValidationException::withMessages($exception->errors);
        }

        if ($exception->errorCode === 'content_policy_violation') {
            throw ValidationException::withMessages(['prompt' => [$exception->getMessage()]]);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
