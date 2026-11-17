<?php

namespace App\Services;

use InvalidArgumentException;

class PricingCalculator
{
    private const NANODOLLARS_PER_USD = 1_000_000_000;

    private const RATE_KEYS = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    /**
     * Estimate what a generation will cost before it runs, as exact USD strings.
     *
     * Output tokens and reference image tokens are exact. Text tokens are estimated
     * from the prompt length (the real count lands within ±15%), and reasoning tokens
     * are only known once the generation finishes, so both stay ranges.
     *
     * @param  array<string, mixed>  $pricing  The GET /v1/pricing payload.
     * @param  array<int, string>  $referenceImages
     * @return array<string, mixed>
     */
    public function estimate(
        array $pricing,
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = [],
    ): array {
        $estimate = $this->estimateNano($pricing, $prompt, $size, $quality, $referenceImages);

        return [
            'currency' => $pricing['currency'] ?? 'usd',
            'rates' => data_get($pricing, 'rates'),
            'tokens' => $estimate['tokens'],
            'provider_cost' => array_map(self::formatUsd(...), $estimate['provider_cost']),
            'price' => array_map(self::formatUsd(...), $estimate['price']),
        ];
    }

    /**
     * The same estimate in integer nanodollars, for persisting exact amounts.
     *
     * @param  array<string, mixed>  $pricing  The GET /v1/pricing payload.
     * @param  array<int, string>  $referenceImages
     * @return array{
     *     tokens: array{input_text: int, input_image: int, output: int, reasoning: array{min: int, estimate: int, max: int}},
     *     provider_cost: array{min: int, estimate: int, max: int},
     *     price: array{min: int, estimate: int, max: int}
     * }
     */
    public function estimateNano(
        array $pricing,
        string $prompt,
        string $size,
        string $quality,
        array $referenceImages = [],
    ): array {
        $rates = data_get($pricing, 'rates');
        $output = data_get($pricing, "tokens.output.{$quality}.{$size}");
        $reasoning = data_get($pricing, "tokens.reasoning.{$quality}");

        if (! is_array($rates)) {
            throw new InvalidArgumentException('The pricing payload is missing its rates.');
        }

        if (! is_numeric($output) || ! is_array($reasoning)) {
            throw new InvalidArgumentException('The pricing payload has no token rules for this size and quality.');
        }

        foreach (self::RATE_KEYS as $key) {
            if (! is_numeric($rates[$key] ?? null)) {
                throw new InvalidArgumentException("The pricing payload is missing the {$key} rate.");
            }
        }

        $charactersPerToken = max(1, (int) data_get($pricing, 'tokens.characters_per_text_token', 4));
        $variance = (float) data_get($pricing, 'tokens.text_token_variance', 0);
        $perReferenceImage = (int) data_get($pricing, 'tokens.per_reference_image', 0);

        $characters = mb_strlen($prompt);
        $inputText = (int) ceil($characters / $charactersPerToken);
        $inputTextMin = (int) floor($inputText * (1 - $variance));
        $inputTextMax = (int) ceil($inputText * (1 + $variance));
        $inputImage = count($referenceImages) * $perReferenceImage;
        $outputTokens = (int) $output;
        $reasoningMin = (int) ($reasoning['min'] ?? 0);
        $reasoningMax = (int) ($reasoning['max'] ?? 0);
        $reasoningEstimate = intdiv($reasoningMin + $reasoningMax, 2);

        $providerCostFor = fn (int $textTokens, int $reasoningTokens): int => $this->tokensCost($textTokens, $rates['input_text_tokens'])
            + $this->tokensCost($inputImage, $rates['input_image_tokens'])
            + $this->tokensCost($outputTokens, $rates['output_tokens'])
            + $this->tokensCost($reasoningTokens, $rates['reasoning_tokens']);

        $providerCost = [
            'min' => $providerCostFor($inputTextMin, $reasoningMin),
            'estimate' => $providerCostFor($inputText, $reasoningEstimate),
            'max' => $providerCostFor($inputTextMax, $reasoningMax),
        ];

        return [
            'tokens' => [
                'input_text' => $inputText,
                'input_image' => $inputImage,
                'output' => $outputTokens,
                'reasoning' => [
                    'min' => $reasoningMin,
                    'estimate' => $reasoningEstimate,
                    'max' => $reasoningMax,
                ],
            ],
            'provider_cost' => $providerCost,
            'price' => [
                'min' => $this->withMarkup($providerCost['min']),
                'estimate' => $this->withMarkup($providerCost['estimate']),
                'max' => $this->withMarkup($providerCost['max']),
            ],
        ];
    }

    /**
     * What a finished generation actually costs, from the usage and the rates the
     * provider locked in when it was created. Returns null if either is incomplete,
     * so callers can leave the cost unset rather than record a wrong amount.
     *
     * @param  array<string, mixed>  $usage
     * @param  array<string, mixed>  $rates
     * @return array{provider: int, price: int}|null
     */
    public function actualNano(array $usage, array $rates): ?array
    {
        $providerCost = 0;

        foreach (self::RATE_KEYS as $key) {
            if (! isset($usage[$key]) || ! is_numeric($usage[$key]) || ! is_numeric($rates[$key] ?? null)) {
                return null;
            }

            $providerCost += $this->tokensCost((int) $usage[$key], $rates[$key]);
        }

        return [
            'provider' => $providerCost,
            'price' => $this->withMarkup($providerCost),
        ];
    }

    /**
     * Format nanodollars as an exact USD string: 72_343_750 → "0.072343750".
     */
    public static function formatUsd(int $nanodollars): string
    {
        return sprintf(
            '%d.%09d',
            intdiv($nanodollars, self::NANODOLLARS_PER_USD),
            $nanodollars % self::NANODOLLARS_PER_USD,
        );
    }

    /**
     * Cost of a token count in nanodollars. A rate of $5.00 per 1M tokens is
     * $0.000005 per token, or 5,000 nanodollars: the rate times 1,000.
     */
    private function tokensCost(int $tokens, int|float|string $ratePerMillion): int
    {
        return (int) round($tokens * (float) $ratePerMillion * 1_000);
    }

    /**
     * Apply our 25% markup, rounded to the nearest nanodollar (integer math, no floats).
     */
    private function withMarkup(int $nanodollars): int
    {
        return intdiv($nanodollars * 5 + 2, 4);
    }
}
