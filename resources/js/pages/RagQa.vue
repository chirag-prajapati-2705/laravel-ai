<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ragQa, ragQuestion } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'RAG Q&A',
        href: ragQa().url,
    },
];

const question = ref('');
const loading = ref(false);
const error = ref('');
const answer = ref('');
const sources = ref<string[]>([]);

function getCookie(name: string): string {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

async function askQuestion() {
    if (!question.value.trim()) return;
    loading.value = true;
    error.value = '';
    answer.value = '';
    sources.value = [];

    try {
        const res = await fetch(ragQuestion().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({ question: question.value.trim() }),
        });

        const data = await res.json().catch(async () => {
            const text = await res.text().catch(() => '');
            return text ? { message: text } : null;
        });

        if (!res.ok) {
            const message =
                data?.message
                ?? (Array.isArray(data?.errors?.question) ? data.errors.question[0] : '')
                ?? `Server error (${res.status})`;
            throw new Error(message);
        }

        answer.value = data?.answer ?? '';
        sources.value = Array.isArray(data?.sources) ? data.sources : [];
    } catch (e: any) {
        error.value = e?.message ?? 'Failed to fetch an answer';
    } finally {
        loading.value = false;
    }
}

function clearAnswer() {
    question.value = '';
    answer.value = '';
    sources.value = [];
    error.value = '';
}
</script>

<template>
    <Head title="RAG Q&A" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="max-w-3xl w-full mx-auto space-y-6">

                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RAG Q&A</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Ask questions about the text files in <span class="font-medium text-gray-700 dark:text-gray-300">storage/app/rag</span>.
                        Run <span class="font-medium text-gray-700 dark:text-gray-300">php artisan rag:ingest</span> after updating files.
                    </p>
                </div>

                <div class="space-y-3">
                    <label for="rag-question" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Question
                    </label>
                    <textarea
                        id="rag-question"
                        v-model="question"
                        rows="3"
                        placeholder="e.g. What does the onboarding guide say about PTO?"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 outline-none transition"
                    ></textarea>
                    <div class="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            @click="clearAnswer"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-4 py-2 text-sm font-medium shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            Clear
                        </button>
                        <button
                            type="button"
                            @click="askQuestion"
                            :disabled="loading || !question.trim()"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2 text-sm font-medium shadow-sm transition"
                        >
                            <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ loading ? 'Thinking...' : 'Ask' }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="error" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 px-4 py-3 text-sm text-red-700 dark:text-red-400">
                    {{ error }}
                </div>

                <div v-if="answer" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-5 space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Answer</h2>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                        {{ answer }}
                    </p>

                    <div v-if="sources.length">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Relevant chunks</h3>
                        <div class="space-y-2">
                            <div v-for="(source, index) in sources" :key="index" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-3 py-2 text-xs text-gray-700 dark:text-gray-300">
                                {{ source }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
