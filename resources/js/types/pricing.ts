import type { GenerationQuality, GenerationSize } from '@/types/generation';

/**
 * Pixelforge's published rates and token rules, as returned by GET /v1/pricing.
 * Rates are US dollars per 1M tokens.
 */
export type PricingConfig = {
    object: 'pricing';
    currency: string;
    unit: string;
    rates: {
        input_text_tokens: string;
        input_image_tokens: string;
        output_tokens: string;
        reasoning_tokens: string;
    };
    tokens: {
        characters_per_text_token: number;
        text_token_variance: number;
        per_reference_image: number;
        output: Record<GenerationQuality, Record<GenerationSize, number>>;
        reasoning: Record<GenerationQuality, { min: number; max: number }>;
    };
};

/** An exact USD amount as a decimal string, e.g. "0.072343750". */
export type CostRange = {
    min: string;
    estimate: string;
    max: string;
};

/**
 * The server's estimate for one generation, from POST /generations/estimate.
 * All money is formatted by the server; the client only displays it.
 */
export type GenerationEstimate = {
    currency: string;
    rates: PricingConfig['rates'];
    tokens: {
        input_text: number;
        input_image: number;
        output: number;
        reasoning: { min: number; estimate: number; max: number };
    };
    provider_cost: CostRange;
    price: CostRange;
};
