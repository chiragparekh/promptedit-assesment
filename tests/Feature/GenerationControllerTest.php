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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelForgeGeneration(array $overrides = []): array
{
    return array_replace([
        'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => 'queued',
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/style.png'],
        'metadata' => [],
        'image_url' => null,
        'image_url_expires_at' => null,
        'usage' => null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}

test('guests are redirected to the login page', function () {
    Http::preventStrayRequests();

    $response = $this->post(route('generations.store'), [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
    ]);

    $response->assertRedirect(route('login'));
    Http::assertNothingSent();
    expect(Generation::count())->toBe(0);
});

test('creates the generation with the provider without storing it', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response(pixelForgeGeneration(), 202),
    ]);

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/style.png'],
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Generation::count())->toBe(0);

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://pixelforge.test/v1/generations'
            && $request->hasHeader('Idempotency-Key')
            && $request['prompt'] === 'A lighthouse on a cliff at dawn, oil painting'
            && $request['size'] === '1536x1024'
            && $request['quality'] === 'high'
            && $request['reference_images'] === ['https://example.com/style.png'];
    });
});

test('shows the new generation in the listing after the redirect', function () {
    Http::preventStrayRequests();
    $generation = pixelForgeGeneration();

    Http::fake([
        'pixelforge.test/v1/generations*' => Http::sequence()
            ->push($generation, 202)
            ->push([
                'object' => 'list',
                'data' => [$generation],
                'has_more' => false,
                'next_cursor' => null,
            ]),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->followingRedirects()
        ->post(route('generations.store'), [
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1536x1024',
            'quality' => 'high',
        ]);

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('generations.data', 1)
            ->where('generations.data.0.id', $generation['id'])
            ->where('generations.data.0.prompt', $generation['prompt'])
        );
});

test('requires prompt, size and quality', function () {
    Http::preventStrayRequests();

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), []);

    $response->assertSessionHasErrors([
        'prompt' => 'The prompt field is required.',
        'size' => 'The size field is required.',
        'quality' => 'The quality field is required.',
    ]);
    Http::assertNothingSent();
    expect(Generation::count())->toBe(0);
});

test('allows reference images to be omitted', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response(pixelForgeGeneration([
            'prompt' => 'A simple prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
        ]), 202),
    ]);

    $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => 'A simple prompt',
        'size' => '1024x1024',
        'quality' => 'medium',
    ])->assertRedirect(route('dashboard'));

    Http::assertSent(fn (Request $request): bool => ! isset($request['reference_images']));

    expect(Generation::count())->toBe(0);
});

test('rejects invalid generation input without calling the provider', function () {
    Http::preventStrayRequests();

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => '',
        'size' => '2048x2048',
        'quality' => 'ultra',
        'reference_images' => ['not-a-url'],
    ]);

    $response->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images.0']);
    Http::assertNothingSent();
    expect(Generation::count())->toBe(0);
});

test('rejects more than four reference images', function () {
    Http::preventStrayRequests();

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [
            'https://example.com/one.png',
            'https://example.com/two.png',
            'https://example.com/three.png',
            'https://example.com/four.png',
            'https://example.com/five.png',
        ],
    ]);

    $response->assertSessionHasErrors('reference_images');
    Http::assertNothingSent();
    expect(Generation::count())->toBe(0);
});

test('surfaces provider validation errors on the form', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'validation_failed',
                'message' => 'The selected size is invalid.',
                'param' => 'size',
                'errors' => ['size' => ['The size must be one of: 1024x1024, 1536x1024, 1024x1536.']],
            ],
        ], 422),
    ]);

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);

    $response->assertSessionHasErrors([
        'size' => 'The size must be one of: 1024x1024, 1536x1024, 1024x1536.',
    ]);
    expect(Generation::count())->toBe(0);
});

test('surfaces a content policy rejection on the prompt', function () {
    Http::preventStrayRequests();
    Http::fake([
        'pixelforge.test/v1/generations' => Http::response([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'content_policy_violation',
                'message' => 'The prompt was rejected.',
            ],
        ], 400),
    ]);

    $response = $this->actingAs(User::factory()->create())->post(route('generations.store'), [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1024x1024',
        'quality' => 'medium',
    ]);

    $response->assertSessionHasErrors(['prompt' => 'The prompt was rejected.']);
    expect(Generation::count())->toBe(0);
});
