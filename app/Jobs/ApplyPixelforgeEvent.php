<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplyPixelforgeEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $webhookEventId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = WebhookEvent::query()->lockForUpdate()->find($this->webhookEventId);

            if ($event === null || $event->processed_at !== null) {
                return;
            }

            $this->apply($event);
        });
    }

    public function failed(?Throwable $exception): void
    {
        WebhookEvent::query()->whereKey($this->webhookEventId)->update([
            'exception' => [
                'class' => $exception !== null ? $exception::class : null,
                'message' => $exception?->getMessage(),
            ],
        ]);
    }

    private function apply(WebhookEvent $event): void
    {
        if (! str_starts_with($event->type, 'generation.')) {
            $this->markProcessed($event);

            return;
        }

        $object = $event->payload['data']['object'] ?? null;

        if (! is_array($object)) {
            $event->update(['exception' => [
                'code' => 'invalid_payload',
                'message' => 'The generation event carried no object.',
            ]]);

            return;
        }

        $generation = $this->findGeneration($object);

        if ($generation === null) {
            $event->update(['exception' => [
                'code' => 'generation_not_found',
                'message' => 'No local generation matches this event.',
            ]]);

            Log::warning('Pixelforge webhook has no matching generation.', [
                'event_id' => $event->provider_event_id,
                'provider_id' => $object['id'] ?? null,
            ]);

            return;
        }

        $incoming = GenerationStatus::tryFrom((string) ($object['status'] ?? ''));

        if ($incoming === null) {
            $event->update(['exception' => [
                'code' => 'unknown_status',
                'message' => 'The event carried no recognised generation status.',
            ]]);

            return;
        }

        if ($generation->status->canTransitionTo($incoming)) {
            $generation->update(Generation::providerAttributes($object));
        }

        $this->markProcessed($event);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function findGeneration(array $object): ?Generation
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $localId = $metadata['generation_id'] ?? null;

        $generation = is_string($localId)
            ? Generation::query()->lockForUpdate()->find($localId)
            : null;

        if ($generation !== null) {
            return $generation;
        }

        $providerId = $object['id'] ?? null;

        return is_string($providerId)
            ? Generation::query()->lockForUpdate()->where('provider_id', $providerId)->first()
            : null;
    }

    private function markProcessed(WebhookEvent $event): void
    {
        $event->update(['processed_at' => now(), 'exception' => null]);
    }
}
