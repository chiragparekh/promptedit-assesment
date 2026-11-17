<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.timeout' => 30,
    ]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users see the latest generations from the provider', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations*' => Http::response([
            'object' => 'list',
            'data' => [
                [
                    'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
                    'object' => 'generation',
                    'status' => 'succeeded',
                    'prompt' => 'A brass lighthouse at dawn, oil painting',
                    'size' => '1024x1024',
                    'quality' => 'medium',
                    'reference_images' => [],
                    'metadata' => [],
                    'image_url' => 'https://pixelforge.test/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png',
                    'image_url_expires_at' => '2026-11-05T14:03:29Z',
                    'usage' => null,
                    'rates' => [],
                    'error' => null,
                    'created_at' => '2026-11-04T14:03:11Z',
                    'started_at' => '2026-11-04T14:03:13Z',
                    'completed_at' => '2026-11-04T14:03:29Z',
                ],
            ],
            'has_more' => true,
            'next_cursor' => 'cursor-next',
        ]),
    ]);

    $user = User::factory()->create();

    Generation::create([
        'user_id' => $user->id,
        'provider_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'status' => 'succeeded',
        'prompt' => 'A brass lighthouse at dawn, oil painting',
        'size' => '1024x1024',
        'quality' => 'medium',
        'estimated_price_nano' => 72_343_750,
        'actual_price_nano' => 381_887_500,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('generationsError', null)
            ->has('generations.data', 1)
            ->where('generations.data.0.id', 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
            ->where('generations.data.0.status', 'succeeded')
            ->where('generations.data.0.prompt', 'A brass lighthouse at dawn, oil painting')
            ->where('generations.data.0.image_url', 'https://pixelforge.test/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png')
            ->where('generations.data.0.estimated_cost', '0.072343750')
            ->where('generations.data.0.actual_cost', '0.381887500')
        );

    $page = $response->viewData('page');

    expect($page['scrollProps']['generations'])->toMatchArray([
        'pageName' => 'cursor',
        'previousPage' => null,
        'nextPage' => 'cursor-next',
        'currentPage' => null,
    ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://pixelforge.test/v1/generations?limit=20'
        && $request->hasHeader('Authorization', 'Bearer pf_test_key'));
});

test('loads the next cursor page from the provider', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations*' => Http::response([
            'object' => 'list',
            'data' => [],
            'has_more' => false,
            'next_cursor' => null,
        ]),
    ]);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard', [
        'cursor' => 'cursor-next',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('generations.data', 0)
        );

    $page = $response->viewData('page');

    expect($page['scrollProps']['generations'])->toMatchArray([
        'pageName' => 'cursor',
        'previousPage' => null,
        'nextPage' => null,
        'currentPage' => 'cursor-next',
    ]);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'limit=20')
        && str_contains($request->url(), 'cursor=cursor-next'));
});

test('shows an error when the provider list cannot be loaded', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations*' => Http::response([
            'error' => [
                'type' => 'authentication_error',
                'code' => 'invalid_api_key',
                'message' => 'The API key is invalid.',
            ],
        ], 401),
    ]);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('generations.data', 0)
            ->where('generationsError', 'The API key is invalid.')
        );
});
