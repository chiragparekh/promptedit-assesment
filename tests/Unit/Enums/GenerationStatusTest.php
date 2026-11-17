<?php

use App\Enums\GenerationStatus;

test('ranks statuses in lifecycle order', function () {
    expect(GenerationStatus::Queued->rank())->toBe(0)
        ->and(GenerationStatus::Processing->rank())->toBe(1)
        ->and(GenerationStatus::Succeeded->rank())->toBe(2)
        ->and(GenerationStatus::Failed->rank())->toBe(2);
});

test('marks succeeded and failed as final', function () {
    expect(GenerationStatus::Queued->isFinal())->toBeFalse()
        ->and(GenerationStatus::Processing->isFinal())->toBeFalse()
        ->and(GenerationStatus::Succeeded->isFinal())->toBeTrue()
        ->and(GenerationStatus::Failed->isFinal())->toBeTrue();
});

test('moves a queued generation forward', function () {
    expect(GenerationStatus::Queued->canTransitionTo(GenerationStatus::Queued))->toBeTrue()
        ->and(GenerationStatus::Queued->canTransitionTo(GenerationStatus::Processing))->toBeTrue()
        ->and(GenerationStatus::Queued->canTransitionTo(GenerationStatus::Succeeded))->toBeTrue()
        ->and(GenerationStatus::Queued->canTransitionTo(GenerationStatus::Failed))->toBeTrue();
});

test('keeps a processing generation from moving back', function () {
    expect(GenerationStatus::Processing->canTransitionTo(GenerationStatus::Queued))->toBeFalse()
        ->and(GenerationStatus::Processing->canTransitionTo(GenerationStatus::Processing))->toBeTrue()
        ->and(GenerationStatus::Processing->canTransitionTo(GenerationStatus::Succeeded))->toBeTrue()
        ->and(GenerationStatus::Processing->canTransitionTo(GenerationStatus::Failed))->toBeTrue();
});

test('never changes a final status', function () {
    expect(GenerationStatus::Succeeded->canTransitionTo(GenerationStatus::Succeeded))->toBeFalse()
        ->and(GenerationStatus::Succeeded->canTransitionTo(GenerationStatus::Failed))->toBeFalse()
        ->and(GenerationStatus::Succeeded->canTransitionTo(GenerationStatus::Processing))->toBeFalse()
        ->and(GenerationStatus::Failed->canTransitionTo(GenerationStatus::Failed))->toBeFalse()
        ->and(GenerationStatus::Failed->canTransitionTo(GenerationStatus::Succeeded))->toBeFalse()
        ->and(GenerationStatus::Failed->canTransitionTo(GenerationStatus::Processing))->toBeFalse();
});
