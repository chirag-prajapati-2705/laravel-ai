<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { generateImage, imageGenerator } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { computed, onBeforeUnmount, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Image Generator',
        href: imageGenerator().url,
    },
];

const prompt = ref('');
const imageFile = ref<File | null>(null);
const localPreviewUrl = ref('');
const inputImageUrl = ref('');
const generatedImageUrl = ref('');
const loading = ref(false);
const error = ref('');

const displayInputUrl = computed(() => inputImageUrl.value || localPreviewUrl.value);

function getCookie(name: string): string {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    imageFile.value = file;
    inputImageUrl.value = '';

    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
        localPreviewUrl.value = '';
    }

    if (file) {
        localPreviewUrl.value = URL.createObjectURL(file);
    }
}

async function submit() {
    if (!imageFile.value || !prompt.value.trim()) return;
    loading.value = true;
    error.value = '';
    generatedImageUrl.value = '';

    const formData = new FormData();
    formData.append('image', imageFile.value);
    formData.append('prompt', prompt.value.trim());

    try {
        const res = await fetch(generateImage().url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: formData,
        });

        const data = await res.json().catch(async () => {
            const text = await res.text().catch(() => '');
            return text ? { message: text } : null;
        });

        if (!res.ok) {
            const message =
                data?.message
                ?? (Array.isArray(data?.errors?.prompt) ? data.errors.prompt[0] : '')
                ?? (Array.isArray(data?.errors?.image) ? data.errors.image[0] : '')
                ?? `Server error (${res.status})`;
            throw new Error(message);
        }

        generatedImageUrl.value = data?.generated_url ?? '';
        inputImageUrl.value = data?.input_image_url ?? '';
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to generate image';
    } finally {
        loading.value = false;
    }
}

onBeforeUnmount(() => {
    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
    }
});
</script>

<template>
    <Head title="Image Generator" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-4xl w-full mx-auto space-y-6">

                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Image Generator</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Upload an image and describe what you want to generate.
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="image-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Reference image
                            </label>
                            <input
                                id="image-file"
                                type="file"
                                accept="image/*"
                                @change="onFileChange"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 outline-none transition"
                            />
                        </div>
                        <div>
                            <label for="prompt" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Prompt
                            </label>
                            <input
                                id="prompt"
                                v-model="prompt"
                                type="text"
                                placeholder="A cinematic portrait with warm lighting"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 outline-none transition"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end">
                        <button
                            type="submit"
                            :disabled="loading || !prompt.trim() || !imageFile"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 text-sm font-medium shadow-sm transition"
                        >
                            <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ loading ? 'Generating...' : 'Generate Image' }}</span>
                        </button>
                    </div>
                </form>

                <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                    {{ error }}
                </div>

                <div v-if="displayInputUrl || generatedImageUrl" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4 space-y-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Reference</h2>
                        <div class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-900">
                            <img
                                v-if="displayInputUrl"
                                :src="displayInputUrl"
                                alt="Reference image"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                Upload an image to preview it here.
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4 space-y-3">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Generated</h2>
                        <div class="aspect-[4/3] w-full overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-900">
                            <img
                                v-if="generatedImageUrl"
                                :src="generatedImageUrl"
                                alt="Generated image"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                Generated image will appear here.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
