<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, History, MessageSquare, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { knowledgeBase } from '@/routes';
import { history as chatHistory, session as chatSession, multi as multiChat } from '@/routes/chat';
import { destroy as destroySession } from '@/routes/chat/session';
import { type BreadcrumbItem } from '@/types';

type Session = {
    id: number;
    title: string;
    fileName: string;
    fileId: number;
    messagesCount: number;
    createdAt: string;
    updatedAt: string;
};

defineProps<{
    sessions: Session[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chat History',
        href: chatHistory().url,
    },
];

function deleteSession(id: number) {
    if (confirm('Are you sure you want to delete this chat session?')) {
        router.delete(destroySession(id).url);
    }
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString([], { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<template>
    <Head title="Chat History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="w-full max-w-5xl mx-auto space-y-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Chat History</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Continue your previous conversations with indexed documents.
                    </p>
                </div>

                <div v-if="sessions.length === 0" class="flex flex-col items-center justify-center py-20 bg-white dark:bg-gray-800 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700">
                    <History class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No history yet</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm text-center">
                        Start a new conversation by selecting a document from the Knowledge Base.
                    </p>
                    <Link
                        :href="knowledgeBase().url"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition"
                    >
                        Go to Knowledge Base
                    </Link>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="session in sessions" 
                        :key="session.id"
                        class="group relative bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition overflow-hidden"
                    >
                        <Link 
                            :href="session.fileId ? chatSession({ file: session.fileId, session: session.id }).url : multiChat.session(session.id).url"
                            class="block p-5"
                        >
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="p-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                    <MessageSquare class="w-5 h-5" />
                                </div>
                                <span class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
                                    {{ session.messagesCount }} msgs
                                </span>
                            </div>
                            
                            <h3 class="font-bold text-gray-900 dark:text-white line-clamp-1 mb-1">{{ session.title }}</h3>
                            
                            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-4">
                                <BookOpen class="w-3.5 h-3.5" />
                                <span class="truncate">{{ session.fileName }}</span>
                            </div>

                            <div class="text-[10px] text-gray-400 dark:text-gray-500 font-medium uppercase tracking-wider">
                                Last active {{ formatDate(session.updatedAt) }}
                            </div>
                        </Link>

                        <button 
                            @click="deleteSession(session.id)"
                            class="absolute top-3 right-3 p-2 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 opacity-0 group-hover:opacity-100 transition"
                            title="Delete Session"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
