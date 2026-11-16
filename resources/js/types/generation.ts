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
    error: { code: string; message: string } | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
};
