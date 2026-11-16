<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $provider_id The third-party generation ID, prefixed gen_.
 * @property string $status One of queued, processing, succeeded, failed.
 * @property string $prompt At most 4,000 characters.
 * @property string $size One of 1024x1024, 1536x1024, 1024x1536.
 * @property string $quality One of low, medium, high.
 * @property array<int, string>|null $reference_images At most 4 image URLs.
 * @property array<string, string>|null $metadata Up to 20 keys, values up to 500 characters.
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $total_tokens
 * @property int|null $input_text_tokens_rate_cents Cents per 1M input text tokens.
 * @property int|null $input_image_tokens_rate_cents Cents per 1M input image tokens.
 * @property int|null $output_tokens_rate_cents Cents per 1M output tokens.
 * @property int|null $reasoning_tokens_rate_cents Cents per 1M reasoning tokens.
 * @property array{code: string, message: string}|null $error
 * @property Carbon|null $accepted_at When the provider accepted the request.
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Generation extends Model
{
    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Map a Pixelforge generation object onto this model's attributes.
     *
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    public static function providerAttributes(array $object): array
    {
        $usage = is_array($object['usage'] ?? null) ? $object['usage'] : [];
        $rates = is_array($object['rates'] ?? null) ? $object['rates'] : [];

        return [
            'provider_id' => $object['id'] ?? null,
            'status' => $object['status'] ?? 'queued',
            'prompt' => $object['prompt'] ?? null,
            'size' => $object['size'] ?? null,
            'quality' => $object['quality'] ?? null,
            'reference_images' => $object['reference_images'] ?? [],
            'metadata' => $object['metadata'] ?? [],
            'image_url' => $object['image_url'] ?? null,
            'image_url_expires_at' => $object['image_url_expires_at'] ?? null,
            'input_text_tokens' => $usage['input_text_tokens'] ?? null,
            'input_image_tokens' => $usage['input_image_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'reasoning_tokens' => $usage['reasoning_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'input_text_tokens_rate_cents' => self::rateCents($rates, 'input_text_tokens'),
            'input_image_tokens_rate_cents' => self::rateCents($rates, 'input_image_tokens'),
            'output_tokens_rate_cents' => self::rateCents($rates, 'output_tokens'),
            'reasoning_tokens_rate_cents' => self::rateCents($rates, 'reasoning_tokens'),
            'error' => $object['error'] ?? null,
            'accepted_at' => $object['created_at'] ?? null,
            'started_at' => $object['started_at'] ?? null,
            'completed_at' => $object['completed_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rates
     */
    private static function rateCents(array $rates, string $key): ?int
    {
        if (! isset($rates[$key]) || ! is_numeric($rates[$key])) {
            return null;
        }

        return (int) round(((float) $rates[$key]) * 100);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'metadata' => 'array',
            'error' => 'array',
            'image_url_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'input_text_tokens' => 'integer',
            'input_image_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'input_text_tokens_rate_cents' => 'integer',
            'input_image_tokens_rate_cents' => 'integer',
            'output_tokens_rate_cents' => 'integer',
            'reasoning_tokens_rate_cents' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
