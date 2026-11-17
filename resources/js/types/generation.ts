export type GenerationSize = '1024x1024' | '1536x1024' | '1024x1536';

export type GenerationQuality = 'low' | 'medium' | 'high';

export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type GenerationRequest = {
    /** What to generate. At most 4,000 characters. */
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    /** Optional http(s) image URLs to guide the style, at most 4. */
    reference_images: string[];
};

export type GenerationUsage = {
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
    total_tokens: number;
};

/** A row from GET /generations/status, used to refresh in-flight cards. */
export type GenerationSyncRow = {
    provider_id: string;
    status: GenerationStatus;
    image_url: string | null;
    image_url_expires_at: string | null;
    error: { code: string; message: string } | null;
    estimated_cost: string | null;
    actual_cost: string | null;
    completed_at: string | null;
};

export type GenerationRecord = {
    id: string;
    object: string;
    status: GenerationStatus;
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images: string[];
    metadata: Record<string, string>;
    image_url: string | null;
    image_url_expires_at: string | null;
    usage: GenerationUsage | null;
    /** What we quoted the user, as an exact USD string, once the request is accepted. */
    estimated_cost: string | null;
    /** What we charged, once the generation finished. "0.000000000" for a failure. */
    actual_cost: string | null;
    error: { code: string; message: string } | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
};
