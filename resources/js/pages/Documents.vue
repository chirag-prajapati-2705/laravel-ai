<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpFromLine,
    CircleCheck,
    Clock3,
    FileText,
    HardDrive,
    LoaderCircle,
    MessageSquare,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { documents, knowledgeBase, knowledgeBaseUpload } from '@/routes';
import { destroy as destroyFile } from '@/routes/knowledge-base';
import { show as showChat } from '@/routes/chat';
import { type BreadcrumbItem } from '@/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

type KnowledgeBaseFile = {
    id: number;
    name: string;
    path: string;
    size: number;
    status: string;
    uploadedAt: string;
};

const props = defineProps<{
    files: KnowledgeBaseFile[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: documents().url,
    },
];

const activeFilter = ref<string>('all');
const searchQuery = ref<string>('');

const fileStats = computed(() => {
    const totals = props.files.reduce(
        (accumulator, file) => {
            accumulator.totalSize += file.size;

            if (file.status === 'completed') {
                accumulator.ready += 1;
            }

            if (file.status === 'processing') {
                accumulator.processing += 1;
            }

            if (file.status === 'failed') {
                accumulator.failed += 1;
            }

            return accumulator;
        },
        {
            totalSize: 0,
            ready: 0,
            processing: 0,
            failed: 0,
        },
    );

    return {
        total: props.files.length,
        ready: totals.ready,
        processing: totals.processing,
        failed: totals.failed,
        totalSize: totals.totalSize,
    };
});

const filteredFiles = computed(() => {
    let result = props.files;

    if (activeFilter.value !== 'all') {
        const statusMap: Record<string, string> = {
            ready: 'completed',
            processing: 'processing',
            failed: 'failed',
        };

        result = result.filter((file) => file.status === statusMap[activeFilter.value]);
    }

    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((file) => file.name.toLowerCase().includes(query) || file.path.toLowerCase().includes(query));
    }

    return result;
});

const filters = [
    { key: 'all', label: 'All' },
    { key: 'ready', label: 'Ready' },
    { key: 'processing', label: 'Processing' },
    { key: 'failed', label: 'Failed' },
];

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

function formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusMeta(status: string): {
    badgeClass: string;
    icon: typeof CircleCheck;
    label: string;
    dotClass: string;
} {
    if (status === 'completed') {
        return {
            badgeClass: 'glow-badge-green',
            icon: CircleCheck,
            label: 'Ready',
            dotClass: 'bg-emerald-500',
        };
    }

    if (status === 'processing') {
        return {
            badgeClass: 'glow-badge-amber',
            icon: LoaderCircle,
            label: 'Processing',
            dotClass: 'bg-amber-500',
        };
    }

    if (status === 'failed') {
        return {
            badgeClass: 'glow-badge-red',
            icon: AlertTriangle,
            label: 'Failed',
            dotClass: 'bg-red-500',
        };
    }

    return {
        badgeClass: 'border-border/30 bg-muted/20 text-muted-foreground',
        icon: Clock3,
        label: status,
        dotClass: 'bg-muted-foreground',
    };
}

function deleteFile(id: number): void {
    if (confirm('Are you sure you want to delete this file? This will also delete all associated chat history.')) {
        router.delete(destroyFile(id).url);
    }
}

const isUploadModalOpen = ref(false);
const uploadForm = useForm<{ file: File | null }>({
    file: null,
});

const hasFile = computed(() => Boolean(uploadForm.file));

const filePreview = computed(() => {
    if (!uploadForm.file) {
        return {
            detail: 'Maximum size 10 MB',
            name: 'Drop your PDF here or browse files',
        };
    }

    return {
        detail: `${formatBytes(uploadForm.file.size)} ready for ingestion`,
        name: uploadForm.file.name,
    };
});

function onFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
}

function submitUpload(): void {
    if (!uploadForm.file || uploadForm.processing) {
        return;
    }

    uploadForm.post(knowledgeBaseUpload().url, {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset('file');
            isUploadModalOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-y-auto p-6 lg:p-8">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
                <!-- Header -->
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-2">
                        <h1 class="font-heading text-3xl font-extrabold tracking-tight">Document Library</h1>
                        <div class="glow-line w-32"></div>
                        <p class="text-sm text-muted-foreground">
                            Manage, search and interact with your uploaded documents.
                        </p>
                    </div>

                    <Button
                        type="button"
                        class="gradient-button inline-flex items-center gap-2 self-start"
                        @click="isUploadModalOpen = true"
                    >
                        <ArrowUpFromLine class="h-4 w-4" />
                        Upload
                    </Button>
                </div>

                <!-- Stats Bar -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="glass-stat p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                                <FileText class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-2xl font-heading font-extrabold">{{ fileStats.total }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.26em] text-muted-foreground">Total</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-stat p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10">
                                <CircleCheck class="h-5 w-5 text-emerald-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-heading font-extrabold">{{ fileStats.ready }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.26em] text-muted-foreground">Ready</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-stat p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10">
                                <LoaderCircle class="h-5 w-5 text-amber-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-heading font-extrabold">{{ fileStats.processing }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.26em] text-muted-foreground">Processing</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass-stat p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10">
                                <HardDrive class="h-5 w-5 text-indigo-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-heading font-extrabold">{{ formatBytes(fileStats.totalSize) }}</p>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.26em] text-muted-foreground">Storage</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search & Filter Bar -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative max-w-sm flex-1">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search documents..."
                            class="w-full rounded-xl border border-border/50 bg-card/50 py-2.5 pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground/60 backdrop-blur-sm transition focus:border-primary/40 focus:outline-none focus:ring-1 focus:ring-primary/20"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-for="filter in filters"
                            :key="filter.key"
                            type="button"
                            class="filter-pill"
                            :class="activeFilter === filter.key ? 'filter-pill-active' : 'filter-pill-inactive'"
                            @click="activeFilter = filter.key"
                        >
                            {{ filter.label }}
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <section
                    v-if="files.length === 0"
                    class="glass-stat flex flex-col items-center justify-center border-2 border-dashed border-border/30 px-6 py-20 text-center"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <Sparkles class="h-8 w-8" />
                    </div>
                    <h2 class="mt-6 font-heading text-2xl font-bold">No documents yet</h2>
                    <p class="mt-3 max-w-lg text-sm leading-7 text-muted-foreground">
                        Upload your first PDF to build your document library and enable AI-powered chat.
                    </p>
                    <Button
                        type="button"
                        class="gradient-button mt-8 inline-flex items-center gap-2"
                        @click="isUploadModalOpen = true"
                    >
                        <Plus class="h-4 w-4" />
                        Upload document
                    </Button>
                </section>

                <!-- No Results After Filter -->
                <section
                    v-else-if="filteredFiles.length === 0"
                    class="glass-stat flex flex-col items-center justify-center px-6 py-16 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted/30 text-muted-foreground">
                        <Search class="h-7 w-7" />
                    </div>
                    <h3 class="mt-5 font-heading text-lg font-bold">No matching documents</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Try adjusting your search or filter criteria.</p>
                </section>

                <!-- Document Table -->
                <section v-else class="overflow-hidden rounded-2xl border border-border/40 bg-card/30 backdrop-blur-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-[880px] w-full border-collapse">
                            <thead>
                                <tr class="border-b border-border/30">
                                    <th class="px-6 py-4 text-left text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                        Document
                                    </th>
                                    <th class="px-5 py-4 text-left text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                        Size
                                    </th>
                                    <th class="px-5 py-4 text-left text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                        Status
                                    </th>
                                    <th class="px-5 py-4 text-left text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                        Uploaded
                                    </th>
                                    <th class="px-6 py-4 text-right text-[10px] font-semibold uppercase tracking-[0.28em] text-muted-foreground">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="file in filteredFiles"
                                    :key="file.id"
                                    class="group row-glow border-b border-border/20 transition-all duration-300 last:border-b-0"
                                >
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-4">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-all duration-300 group-hover:bg-primary/15 group-hover:shadow-[0_0_16px_rgba(59,130,246,0.15)]">
                                                <FileText class="h-5 w-5" />
                                            </div>

                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-foreground">{{ file.name }}</p>
                                                <p class="mt-0.5 truncate text-xs text-muted-foreground/60">{{ file.path }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-muted-foreground">{{ formatBytes(file.size) }}</td>
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.24em]"
                                            :class="statusMeta(file.status).badgeClass"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta(file.status).dotClass"></span>
                                            <component :is="statusMeta(file.status).icon" class="h-3.5 w-3.5" />
                                            {{ statusMeta(file.status).label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-muted-foreground">{{ formatDate(file.uploadedAt) }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                v-if="file.status === 'completed'"
                                                :href="showChat(file.id).url"
                                                class="inline-flex items-center gap-2 rounded-lg border border-primary/15 bg-primary/5 px-3 py-1.5 text-xs font-semibold text-primary transition-all duration-200 hover:bg-primary/10 hover:shadow-[0_0_12px_rgba(59,130,246,0.15)]"
                                            >
                                                <MessageSquare class="h-3.5 w-3.5" />
                                                Chat
                                            </Link>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-border/30 p-2 text-muted-foreground transition-all duration-200 hover:border-red-500/20 hover:bg-red-500/5 hover:text-red-400 hover:shadow-[0_0_10px_rgba(239,68,68,0.1)]"
                                                title="Delete document"
                                                @click="deleteFile(file.id)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Upload Modal -->
        <Dialog v-model:open="isUploadModalOpen">
            <DialogContent class="max-w-xl p-0 overflow-hidden rounded-[2rem] border-none shadow-2xl">
                <div class="relative p-8">
                    <DialogHeader class="mb-6">
                        <DialogTitle class="font-heading text-2xl font-bold">Upload Document</DialogTitle>
                        <DialogDescription class="text-sm text-muted-foreground">
                            Select a PDF document to add to your library.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitUpload" class="space-y-6">
                        <label
                            for="modal-kb-file"
                            class="group flex min-h-[240px] cursor-pointer flex-col items-center justify-center rounded-[1.5rem] border-2 border-dashed border-border/60 bg-muted/10 px-6 py-8 text-center transition-all duration-300 hover:border-primary/40 hover:bg-primary/[0.02]"
                        >
                            <input
                                id="modal-kb-file"
                                type="file"
                                accept="application/pdf"
                                class="sr-only"
                                @change="onFileChange"
                            />

                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/5 text-primary transition-all duration-300 group-hover:scale-110 group-hover:bg-primary/10">
                                <Upload class="h-8 w-8" />
                            </div>

                            <div class="mt-6 space-y-1">
                                <h3 class="font-heading text-lg font-bold text-foreground truncate max-w-[300px]">
                                    {{ filePreview.name }}
                                </h3>
                                <p class="text-xs font-medium text-muted-foreground">
                                    {{ filePreview.detail }}
                                </p>
                            </div>

                            <div
                                v-if="uploadForm.file"
                                class="mt-6 w-full max-w-sm rounded-xl border border-primary/20 bg-primary/[0.03] px-4 py-3 text-left"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-background text-primary shadow-sm ring-1 ring-primary/20">
                                        <FileText class="h-4 w-4" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold text-foreground">{{ uploadForm.file.name }}</p>
                                        <p class="mt-0.5 text-[10px] font-medium text-muted-foreground/80">Ready for ingestion</p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <InputError class="text-center" :message="uploadForm.errors.file" />

                        <DialogFooter class="sm:justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-1.5 animate-pulse rounded-full bg-primary/40"></div>
                                <p class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">
                                    Max: 10 MB (PDF)
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="rounded-xl px-6"
                                    @click="isUploadModalOpen = false"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    :disabled="uploadForm.processing || !hasFile"
                                    class="gradient-button inline-flex min-w-[140px] items-center justify-center gap-2 px-6"
                                >
                                    <Sparkles v-if="!uploadForm.processing" class="h-3.5 w-3.5" />
                                    <LoaderCircle v-else class="h-3.5 w-3.5 animate-spin" />
                                    <span>{{ uploadForm.processing ? 'Ingesting...' : 'Ingest PDF' }}</span>
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
