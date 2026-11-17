<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider_event_id The Pixelforge event ID, prefixed evt_.
 * @property string $type The event type, such as generation.succeeded or ping.
 * @property array<string, mixed> $payload The event body exactly as delivered.
 * @property array<string, string>|null $headers Selected delivery headers.
 * @property Carbon|null $processed_at
 * @property array<string, mixed>|null $exception
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'exception' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
