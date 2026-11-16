<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { toast } from 'vue-sonner';
import GenerationComposer from '@/components/GenerationComposer.vue';
import { dashboard } from '@/routes';
import type { GenerationRequest } from '@/types/generation';

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

const handleSubmit = (request: GenerationRequest) => {
    void request;

    toast.info('Generation request ready', {
        description: 'Connect the generations endpoint to submit it.',
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
            @submit="handleSubmit"
        />

        <section class="flex min-h-[420px] min-w-0 flex-1 flex-col lg:min-h-0">
            <div
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
