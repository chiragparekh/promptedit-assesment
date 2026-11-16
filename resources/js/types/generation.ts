export type GenerationSize = '1024x1024' | '1536x1024' | '1024x1536';

export type GenerationQuality = 'low' | 'medium' | 'high';

export type GenerationRequest = {
    /** What to generate. At most 4,000 characters. */
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    /** Optional http(s) image URLs to guide the style, at most 4. */
    reference_images: string[];
};
