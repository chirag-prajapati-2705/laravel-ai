<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    FileText,
    Sparkles,
    Upload,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { knowledgeBase, knowledgeBaseUpload, documents as documentsRoute } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import InputError from '@/components/InputError.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Knowledge Base',
        href: knowledgeBase().url,
    },
];

const form = useForm<{ file: File | null }>({
    file: null,
});

const hasFile = computed(() => Boolean(form.file));

const filePreview = computed(() => {
    if (!form.file) {
        return {
            detail: 'Maximum size 10 MB',
            name: 'Drop your PDF here or browse files',
        };
    }

    return {
        detail: `${formatBytes(form.file.size)} ready for ingestion`,
        name: form.file.name,
    };
});

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kilobytes = bytes / 1024;

    if (kilobytes < 1024) {
        return `${kilobytes.toFixed(1)} KB`;
    }

    return `${(kilobytes / 1024).toFixed(1)} MB`;
}

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function submit(): void {
    if (!form.file || form.processing) {
        return;
    }

    form.post(knowledgeBaseUpload().url, {
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
        },
    });
}
</script>

<template>
    <Head title="Knowledge Base" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 overflow-y-auto p-6 lg:p-8">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">

                <div class="mx-auto w-full max-w-4xl">
                    <section class="minimal-card p-6 sm:p-10">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary">Upload panel</p>
                                <h2 class="font-heading text-2xl font-bold text-foreground">Select a document to ingest</h2>
                                <p class="text-sm leading-7 text-muted-foreground">
                                    Add a clean PDF source and the ingestion pipeline will convert it into a searchable knowledge asset.
                                </p>
                            </div>

                            <div class="rounded-full border border-border bg-muted/40 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                Local processing
                            </div>
                        </div>

                        <form class="mt-8 space-y-8" @submit.prevent="submit">
                            <label
                                for="kb-file"
                                class="group flex min-h-[360px] cursor-pointer flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-border/60 bg-muted/10 px-6 py-10 text-center transition-all duration-300 hover:border-primary/40 hover:bg-primary/[0.02] hover:shadow-[0_0_40px_-15px_rgba(59,130,246,0.1)]"
                            >
                                <input
                                    id="kb-file"
                                    type="file"
                                    accept="application/pdf"
                                    class="sr-only"
                                    @change="onFileChange"
                                />

                                <div class="flex h-20 w-20 items-center justify-center rounded-[1.5rem] bg-primary/5 text-primary transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/10">
                                    <Upload class="h-10 w-10" />
                                </div>

                                <div class="mt-8 space-y-2">
                                    <h3 class="font-heading text-2xl font-bold text-foreground transition-colors group-hover:text-primary">
                                        {{ filePreview.name }}
                                    </h3>
                                    <p class="text-sm font-medium text-muted-foreground">
                                        {{ filePreview.detail }}
                                    </p>
                                </div>

                                <div
                                    v-if="!form.file"
                                    class="mt-8 inline-flex items-center gap-2 rounded-full border border-border/60 bg-background px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.28em] text-muted-foreground/70 shadow-sm"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                    Supported format: PDF
                                </div>

                                <div
                                    v-else
                                    class="mt-8 w-full max-w-md rounded-2xl border border-primary/20 bg-primary/[0.03] px-5 py-4 text-left backdrop-blur-sm"
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-background text-primary shadow-sm ring-1 ring-primary/20">
                                            <FileText class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold text-foreground">{{ form.file.name }}</p>
                                            <p class="mt-1 text-xs font-medium text-muted-foreground/80">Ready for ingestion</p>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <InputError class="text-center" :message="form.errors.file" />

                            <div class="flex flex-col gap-6 pt-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-2 w-2 animate-pulse rounded-full bg-primary/40"></div>
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Max file size: 10 MB (PDF)
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing || !hasFile"
                                    class="pro-button inline-flex min-w-[240px] items-center justify-center gap-3 bg-primary py-4 text-primary-foreground shadow-xl shadow-primary/20 transition-all duration-300 hover:-translate-y-0.5 hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0"
                                >
                                    <svg
                                        v-if="form.processing"
                                        class="h-5 w-5 animate-spin"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    <Sparkles v-else class="h-4 w-4" />
                                    <span class="font-bold tracking-tight">{{ form.processing ? 'Ingesting PDF...' : 'Initialize ingestion' }}</span>
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
