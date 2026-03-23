<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { summary } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Youtube Summary',
        href: summary().url,
    },
];

const url = ref('');
const loading = ref(false);
const error = ref('');
const summaryText = ref('');
const takeaways = ref<string[]>([]);
const copied = ref(false);

/**
 * Read the XSRF-TOKEN cookie that Laravel always sets.
 * We need to send it back as X-XSRF-TOKEN so Laravel's
 * VerifyCsrfToken middleware accepts the request.
 * (Alternatively the route is exempted in bootstrap/app.php)
 */
function getCookie(name: string): string {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchSummary() {
    if (!url.value.trim()) return;
    loading.value = true;
    error.value = '';
    summaryText.value = '';
    takeaways.value = [];

    try {
        const res = await fetch('/video-summary', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({ url: url.value.trim() }),
        });

        const data = await res.json().catch(async () => {
            const text = await res.text().catch(() => '');
            return text ? { message: text } : null;
        });

        if (!res.ok) {
            const message =
                data?.message
                ?? (Array.isArray(data?.errors?.url) ? data.errors.url[0] : '')
                ?? `Server error (${res.status})`;
            throw new Error(message);
        }

        if (typeof data === 'string') {
            summaryText.value = data;
        } else {
            summaryText.value = data.summary ?? data.text ?? '';
            takeaways.value = Array.isArray(data.takeaways)
                ? data.takeaways
                : data.takeaways
                    ? [String(data.takeaways)]
                    : [];

            // If API returns a paragraph with appended takeaways, try to split them
            if (!takeaways.value.length && typeof data.summary === 'string') {
                const parts = data.summary.split(/\n{2,}/).map((s: string) => s.trim()).filter(Boolean);
                if (parts.length > 1) {
                    summaryText.value = parts[0];
                    takeaways.value = parts.slice(1);
                }
            }
        }
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to fetch summary';
    } finally {
        loading.value = false;
    }
}

async function copySummary() {
    const parts: string[] = [];
    if (summaryText.value.trim()) {
        parts.push(summaryText.value.trim());
    }
    if (takeaways.value.length) {
        parts.push('Key Takeaways:');
        parts.push(...takeaways.value.map((t) => `- ${t}`));
    }

    if (!parts.length) return;

    try {
        await navigator.clipboard.writeText(parts.join('\n'));
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        copied.value = false;
        error.value = 'Failed to copy summary';
    }
}

function clearSummary() {
    url.value = '';
    summaryText.value = '';
    takeaways.value = [];
    error.value = '';
    copied.value = false;
}
</script>

<template>
    <Head title="Youtube Summary" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-2xl w-full mx-auto space-y-6">

                <!-- Page heading -->
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Youtube Video Summary</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Paste a YouTube URL below and get an AI-generated summary.
                    </p>
                </div>

                <!-- Input + Button -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="youtube-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            YouTube URL
                        </label>
                        <input
                            id="youtube-url"
                            v-model="url"
                            @keyup.enter="fetchSummary"
                            type="url"
                            placeholder="https://www.youtube.com/watch?v=..."
                            class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 outline-none transition"
                        />
                    </div>
                    <button
                        @click="fetchSummary"
                        :disabled="loading || !url.trim()"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 text-sm font-medium shadow-sm transition"
                    >
                        <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ loading ? 'Loading…' : 'Get Summary' }}</span>
                    </button>
                </div>

                <!-- Error -->
                <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                    {{ error }}
                </div>

                <!-- Result -->
                <div v-if="summaryText || takeaways.length" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Summary</h2>
                        <div class="flex items-center gap-2">
                            <button
                                @click="copySummary"
                                class="inline-flex items-center gap-2 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                            >
                                {{ copied ? 'Copied' : 'Copy summary' }}
                            </button>
                            <button
                                @click="clearSummary"
                                class="inline-flex items-center gap-2 rounded-md border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-red-100 dark:hover:bg-red-900/30 transition"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                    <p v-if="summaryText" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                        {{ summaryText }}
                    </p>

                    <div v-if="takeaways.length">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Key Takeaways</h3>
                        <ul class="list-disc list-inside space-y-1">
                            <li v-for="(t, i) in takeaways" :key="i" class="text-sm text-gray-700 dark:text-gray-300">
                                {{ t }}
                            </li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
