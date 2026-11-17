<script setup lang="ts">
import { ImagePlus, Loader2, Plus, Sparkles, X } from '@lucide/vue';
import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import GenerationEstimateController from '@/actions/App/Http/Controllers/GenerationEstimateController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatUsd } from '@/lib/pricing';
import type { GenerationEstimate } from '@/types/pricing';
import type {
    GenerationQuality,
    GenerationRequest,
    GenerationSize,
} from '@/types/generation';

const PROMPT_LIMIT = 4000;
const REFERENCE_LIMIT = 4;
const ESTIMATE_DEBOUNCE_MS = 600;

type ReferenceRow = {
    id: number;
    url: string;
    touched: boolean;
    previewFailed: boolean;
};

type EstimatePayload = {
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images: string[];
};

const emit = defineEmits<{
    submit: [request: GenerationRequest];
}>();

const props = withDefaults(
    defineProps<{
        processing?: boolean;
        errors?: Record<string, string | undefined>;
    }>(),
    {
        processing: false,
        errors: () => ({}),
    },
);

const prompt = ref('');
const size = ref<GenerationSize>('1024x1024');
const quality = ref<GenerationQuality>('medium');
const references = ref<ReferenceRow[]>([]);

let nextRowId = 1;

const sizeOptions = [
    { value: '1024x1024', label: 'Square — 1024 × 1024' },
    { value: '1536x1024', label: 'Landscape — 1536 × 1024' },
    { value: '1024x1536', label: 'Portrait — 1024 × 1536' },
] as const;

const qualityOptions = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
] as const;

const isHttpUrl = (value: string): boolean => {
    try {
        const url = new URL(value);

        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
};

const promptLength = computed(() => prompt.value.length);

const referenceUrls = computed(() =>
    references.value.map((row) => row.url.trim()).filter(Boolean),
);

/**
 * Only complete, valid http(s) URLs are sent to the provider, so only those are
 * billed: each costs 765 input image tokens.
 */
const billableReferenceUrls = computed(() =>
    references.value
        .map((row) => row.url.trim())
        .filter((url) => isHttpUrl(url)),
);

const estimate = ref<GenerationEstimate | null>(null);

const estimatePayload = (): EstimatePayload => ({
    prompt: prompt.value.trim(),
    size: size.value,
    quality: quality.value,
    reference_images: billableReferenceUrls.value,
});

const estimateRequest = useHttp<EstimatePayload, GenerationEstimate>(
    'post',
    () => GenerationEstimateController.url(),
    estimatePayload,
);

// useHttp snapshots its data once at setup, so rebuild the payload from the live
// form state on every request instead of sending the values it was created with.
estimateRequest.transform(estimatePayload);

let estimateTimeout: number | undefined;

const refreshEstimate = () => {
    // Drop any request still in flight so fast typing can't stack them up.
    estimateRequest.cancel();

    estimateRequest
        .post(GenerationEstimateController.url(), {
            onSuccess: (response) => {
                estimate.value = response;
            },
            onHttpException: () => {
                estimate.value = null;
            },
            onNetworkError: () => {
                estimate.value = null;
            },
        })
        .catch(() => {});
};

watch([prompt, size, quality, billableReferenceUrls], () => {
    window.clearTimeout(estimateTimeout);
    estimateTimeout = window.setTimeout(refreshEstimate, ESTIMATE_DEBOUNCE_MS);
});

const referenceError = (row: ReferenceRow): string | undefined => {
    if (row.url.trim() === '') {
        return undefined;
    }

    return isHttpUrl(row.url.trim())
        ? undefined
        : 'Enter a valid http(s) image URL.';
};

const hasReferenceError = computed(() =>
    references.value.some((row) => referenceError(row) !== undefined),
);

const canSubmit = computed(
    () =>
        prompt.value.trim() !== '' &&
        promptLength.value <= PROMPT_LIMIT &&
        !hasReferenceError.value,
);

const addReference = () => {
    if (references.value.length >= REFERENCE_LIMIT) {
        return;
    }

    references.value.push({
        id: nextRowId++,
        url: '',
        touched: false,
        previewFailed: false,
    });
};

const removeReference = (id: number) => {
    references.value = references.value.filter((row) => row.id !== id);
};

/**
 * Clear what the user typed once a generation was accepted. Size and quality stay,
 * since those are usually a preference rather than a per-image choice.
 */
const reset = () => {
    prompt.value = '';
    references.value = [];
    estimate.value = null;
};

defineExpose({ reset });

const submit = () => {
    if (!canSubmit.value || props.processing) {
        references.value.forEach((row) => {
            row.touched = true;
        });

        return;
    }

    emit('submit', {
        prompt: prompt.value.trim(),
        size: size.value,
        quality: quality.value,
        reference_images: referenceUrls.value,
    });
};

const handleShortcut = (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        submit();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleShortcut);
    refreshEstimate();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleShortcut);
    window.clearTimeout(estimateTimeout);
    estimateRequest.cancel();
});
</script>

<template>
    <form
        class="flex min-h-0 flex-col overflow-hidden rounded-xl border bg-card"
        @submit.prevent="submit"
    >
        <header
            class="flex shrink-0 items-start justify-between gap-3 border-b px-4 py-3"
        >
            <div class="space-y-0.5">
                <h2 class="text-sm font-semibold">Image generation</h2>
                <p class="text-xs text-muted-foreground">
                    Turn a prompt into a new image.
                </p>
            </div>
            <Badge variant="secondary">Image</Badge>
        </header>

        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 py-4">
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <Label for="generation-prompt">Prompt</Label>
                    <span
                        class="text-xs text-muted-foreground tabular-nums"
                        :class="{
                            'text-amber-600 dark:text-amber-400':
                                promptLength > PROMPT_LIMIT - 200,
                        }"
                    >
                        {{ promptLength.toLocaleString() }} / 4,000
                    </span>
                </div>
                <Textarea
                    id="generation-prompt"
                    v-model="prompt"
                    :maxlength="PROMPT_LIMIT"
                    class="field-sizing-fixed h-32 resize-none"
                    placeholder="Describe the shot — subject, surface, light, lens."
                />
                <p class="text-xs text-muted-foreground">
                    Be specific about the subject, composition, and lighting.
                </p>
                <InputError :message="errors.prompt" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2">
                        <Label>Reference images</Label>
                        <Badge
                            variant="secondary"
                            class="px-1.5 py-0 text-[10px]"
                        >
                            Optional
                        </Badge>
                    </span>
                    <span class="text-xs text-muted-foreground tabular-nums">
                        {{ references.length }}/{{ REFERENCE_LIMIT }}
                    </span>
                </div>

                <div v-if="references.length" class="space-y-2">
                    <div
                        v-for="(row, index) in references"
                        :key="row.id"
                        class="space-y-1"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted"
                            >
                                <img
                                    v-if="
                                        isHttpUrl(row.url.trim()) &&
                                        !row.previewFailed
                                    "
                                    :src="row.url.trim()"
                                    alt=""
                                    class="size-full object-cover"
                                    @error="row.previewFailed = true"
                                />
                                <ImagePlus
                                    v-else
                                    class="size-4 text-muted-foreground"
                                />
                            </div>
                            <Input
                                v-model="row.url"
                                type="url"
                                inputmode="url"
                                spellcheck="false"
                                :aria-label="`Reference image URL ${index + 1}`"
                                placeholder="Paste an image URL"
                                @input="row.previewFailed = false"
                                @blur="row.touched = true"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0 text-muted-foreground hover:text-foreground"
                                aria-label="Remove reference image"
                                @click="removeReference(row.id)"
                            >
                                <X />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                errors[`reference_images.${index}`] ??
                                (row.touched ? referenceError(row) : undefined)
                            "
                        />
                    </div>
                </div>

                <Button
                    v-if="references.length < REFERENCE_LIMIT"
                    type="button"
                    variant="outline"
                    class="w-full border-dashed text-muted-foreground hover:text-foreground"
                    @click="addReference"
                >
                    <Plus />
                    Add reference image
                </Button>
            </div>

            <div class="space-y-2">
                <Label for="generation-size">Size</Label>
                <Select v-model="size">
                    <SelectTrigger id="generation-size" class="w-full">
                        <SelectValue placeholder="Select a size" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in sizeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.size" />
            </div>

            <div class="space-y-2">
                <Label for="generation-quality">Quality</Label>
                <Select v-model="quality">
                    <SelectTrigger id="generation-quality" class="w-full">
                        <SelectValue placeholder="Select a quality" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in qualityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    Higher quality takes longer and uses more tokens.
                </p>
                <InputError :message="errors.quality" />
            </div>
        </div>

        <div
            class="flex shrink-0 items-center justify-between gap-3 border-t px-4 py-3"
        >
            <div class="min-w-0">
                <p
                    v-if="estimateRequest.processing"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Estimating…
                </p>
                <p
                    v-else-if="estimate"
                    class="text-sm font-medium tabular-nums"
                >
                    Estimated {{ formatUsd(estimate.price.estimate) }}
                </p>
                <p v-else class="text-[11px] text-muted-foreground">
                    Estimate unavailable
                </p>
            </div>
            <Button
                type="submit"
                :disabled="!canSubmit || processing"
                class="shrink-0"
            >
                <Loader2 v-if="processing" class="animate-spin" />
                <Sparkles v-else />
                {{ processing ? 'Generating…' : 'Generate' }}
            </Button>
        </div>
    </form>
</template>
