<?php

namespace App\Http\Requests;

use App\Models\Generation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::in(Generation::SIZES)],
            'quality' => ['required', 'string', Rule::in(Generation::QUALITIES)],
            'reference_images' => ['sometimes', 'array', 'max:4'],
            'reference_images.*' => ['string', 'url:http,https'],
        ];
    }
}
