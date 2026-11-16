<script setup lang="ts">
import { Head, InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    Clock,
    Loader2,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import GenerationController from '@/actions/App/Http/Controllers/GenerationController';
import AlertError from '@/components/AlertError.vue';
import GenerationComposer from '@/components/GenerationComposer.vue';
import { useRelativeTime } from '@/composables/useRelativeTime';
import { dashboard } from '@/routes';
import type {
    GenerationRecord,
    GenerationRequest,
    GenerationStatus,
} from '@/types/generation';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    generations: { data: GenerationRecord[] };
    generationsError?: string | null;
}>();

const page = usePage();
const processing = ref(false);
const errors = computed(() => page.props.errors);
const { formatRelativeTime, formatDateTime } = useRelativeTime();

const statusLabels: Record<GenerationStatus, string> = {
    queued: 'Queued',
    processing: 'Processing',
    succeeded: 'Succeeded',
    failed: 'Failed',
};

const handleSubmit = (request: GenerationRequest) => {
    router.post(GenerationController.store.url(), request, {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex flex-1 flex-col gap-4 p-4 lg:h-[calc(100dvh-4rem)] lg:min-h-0 lg:flex-row lg:overflow-hidden"
    >
        <GenerationComposer
            class="w-full lg:w-[380px] lg:shrink-0"
            :processing="processing"
            :errors="errors"
            @submit="handleSubmit"
        />

        <section class="flex min-h-[420px] min-w-0 flex-1 flex-col lg:min-h-0">
            <header class="shrink-0 px-1 pb-3">
                <h2 class="text-sm font-semibold">Generations</h2>
            </header>

            <AlertError
                v-if="props.generationsError"
                class="mb-4"
                title="Could not load generations."
                :errors="[props.generationsError]"
            />

            <div
                v-if="props.generations.data.length"
                class="min-h-0 flex-1 overflow-y-auto"
            >
                <InfiniteScroll
                    data="generations"
                    items-element="#generation-grid"
                    only-next
                    :buffer="400"
                >
                    <div
                        id="generation-grid"
                        class="grid grid-cols-2 gap-x-3 gap-y-5 pb-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                    >
                        <article
                            v-for="generation in props.generations.data"
                            :key="generation.id"
                            class="space-y-2"
                        >
                            <a
                                v-if="generation.image_url"
                                :href="generation.image_url"
                                :aria-label="generation.prompt"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="relative block overflow-hidden rounded-lg bg-muted"
                            >
                                <img
                                    :src="generation.image_url"
                                    :alt="generation.prompt"
                                    loading="lazy"
                                    decoding="async"
                                    class="aspect-square w-full object-cover"
                                />
                                <span
                                    role="img"
                                    :title="statusLabels[generation.status]"
                                    :aria-label="
                                        statusLabels[generation.status]
                                    "
                                    class="absolute top-1.5 right-1.5 flex size-6 items-center justify-center rounded-full bg-background/90 shadow-sm backdrop-blur"
                                >
                                    <CircleCheck
                                        v-if="generation.status === 'succeeded'"
                                        class="size-4 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <CircleAlert
                                        v-else-if="
                                            generation.status === 'failed'
                                        "
                                        class="size-4 text-destructive"
                                    />
                                    <Loader2
                                        v-else-if="
                                            generation.status === 'processing'
                                        "
                                        class="size-4 animate-spin text-amber-600 dark:text-amber-400"
                                    />
                                    <Clock
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                </span>
                            </a>
                            <div
                                v-else
                                class="flex aspect-square items-center justify-center rounded-lg bg-muted/40"
                                :title="statusLabels[generation.status]"
                                :aria-label="statusLabels[generation.status]"
                                role="img"
                            >
                                <Loader2
                                    v-if="
                                        generation.status === 'processing' ||
                                        generation.status === 'queued'
                                    "
                                    class="size-6 animate-spin text-muted-foreground"
                                />
                                <CircleAlert
                                    v-else-if="generation.status === 'failed'"
                                    class="size-6 text-destructive"
                                />
                                <Clock
                                    v-else
                                    class="size-6 text-muted-foreground"
                                />
                            </div>

                            <div class="space-y-1">
                                <p
                                    class="truncate text-sm"
                                    :title="generation.prompt"
                                >
                                    {{ generation.prompt }}
                                </p>
                                <div
                                    class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span>{{ generation.size }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="capitalize">
                                        {{ generation.quality }}
                                    </span>
                                    <time
                                        :datetime="generation.created_at"
                                        :title="
                                            formatDateTime(
                                                generation.created_at,
                                            )
                                        "
                                        class="ml-auto whitespace-nowrap"
                                    >
                                        {{
                                            formatRelativeTime(
                                                generation.created_at,
                                            )
                                        }}
                                    </time>
                                </div>
                                <p
                                    v-if="generation.error"
                                    class="text-xs text-destructive"
                                >
                                    {{ generation.error.message }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <template #loading>
                        <div
                            class="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground"
                        >
                            <Loader2 class="size-4 animate-spin" />
                            Loading more…
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <div
                v-else-if="!props.generationsError"
                class="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center"
            >
                <div
                    class="mb-4 flex size-12 items-center justify-center rounded-full bg-muted"
                >
                    <Sparkles class="size-5 text-muted-foreground" />
                </div>
                <h3 class="text-sm font-medium">No generations yet</h3>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Describe an image in the composer and generate it. Every
                    result will appear here with its prompt and settings.
                </p>
            </div>
        </section>
    </div>
</template>
