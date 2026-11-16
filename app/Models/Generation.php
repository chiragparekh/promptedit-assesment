<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $provider_id The third-party generation ID, prefixed gen_.
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
