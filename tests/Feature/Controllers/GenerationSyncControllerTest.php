<?php

use App\Models\Generation;
use App\Models\User;

/**
 * @param  array<string, mixed>  $overrides
 */
function syncableGeneration(User $user, array $overrides = []): Generation
{
    return Generation::create(array_replace([
        'user_id' => $user->id,
        'provider_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'status' => 'succeeded',
        'prompt' => 'A lighthouse on a cliff at dawn',
        'size' => '1024x1024',
        'quality' => 'medium',
        'image_url' => 'https://pixelforge.test/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png',
        'estimated_price_nano' => 72_343_750,
        'actual_price_nano' => 64_643_750,
    ], $overrides));
}

test('guests cannot read generation status', function () {
    $this->getJson(route('generations.status', ['ids' => ['gen_1']]))
        ->assertUnauthorized();
});

test('returns the current state of the requested generations', function () {
    $user = User::factory()->create();
    syncableGeneration($user);

    $this->actingAs($user)
        ->getJson(route('generations.status', [
            'ids' => ['gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K'],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.provider_id', 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->assertJsonPath('data.0.status', 'succeeded')
        ->assertJsonPath('data.0.image_url', 'https://pixelforge.test/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png')
        ->assertJsonPath('data.0.estimated_cost', '0.072343750')
        ->assertJsonPath('data.0.actual_cost', '0.064643750');
});

test('never returns another users generation', function () {
    syncableGeneration(User::factory()->create(), ['provider_id' => 'gen_other']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('generations.status', ['ids' => ['gen_other']]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('returns nothing when no ids are given', function () {
    syncableGeneration(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->getJson(route('generations.status'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
