<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ChevronLeft, MessageSquare, Send, User, Files, Sparkles } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { documents as documentsRoute } from '@/routes';
import chatRoutes from '@/routes/chat';
import { type BreadcrumbItem } from '@/types';

type Message = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    createdAt: string;
};

type Session = {
    id: number;
    title: string;
    createdAt: string;
};

const props = defineProps<{
    files: {
        id: number;
        name: string;
    }[];
    session: {
        id: number;
        title: string;
        messages: Message[];
    } | null;
    recentSessions: Session[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Documents', href: documentsRoute().url },
    { title: 'Cross-Document Chat' },
];

const messageInput = ref('');
const messages = ref<Message[]>(props.session?.messages ?? []);
const currentSessionId = ref<number | null>(props.session?.id ?? null);
const isLoading = ref(false);
const thinkingMessage = ref('');
const streamingMessage = ref<Message | null>(null);
const scrollContainer = ref<HTMLElement | null>(null);

function scrollToBottom() {
    nextTick(() => {
        if (scrollContainer.value) {
            scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
        }
    });
}

onMounted(() => {
    scrollToBottom();
});

watch(() => props.session?.messages, (newMessages) => {
    if (newMessages) {
        messages.value = [...newMessages];
        scrollToBottom();
    }
}, { deep: true });

async function getCookie(name: string): Promise<string> {
    if (typeof document === 'undefined') return '';
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

async function send() {
    if (!messageInput.value.trim() || isLoading.value) return;

    const userMessage = messageInput.value.trim();
    messageInput.value = '';

    // Add user message to UI
    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: userMessage,
        createdAt: new Date().toISOString(),
    });

    scrollToBottom();
    isLoading.value = true;
    thinkingMessage.value = 'Initializing...';
    streamingMessage.value = null;

    try {
        const response = await fetch('/chat/multi/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-XSRF-TOKEN': await getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                message: userMessage,
                session_id: currentSessionId.value,
                file_ids: props.files.map((f) => f.id),
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to connect to stream');
        }

        const reader = response.body?.getReader();
        const decoder = new TextDecoder();

        if (!reader) throw new Error('No reader available');

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (!line.trim() || !line.startsWith('data: ')) continue;

                try {
                    const data = JSON.parse(line.substring(6));

                    if (data.thinking) {
                        thinkingMessage.value = data.thinking;
                    }

                    if (data.chunk) {
                        thinkingMessage.value = ''; // Stop showing thinking once content starts
                        if (!streamingMessage.value) {
                            streamingMessage.value = {
                                id: Date.now(),
                                role: 'assistant',
                                content: '',
                                createdAt: new Date().toISOString(),
                            };
                        }
                        streamingMessage.value.content += data.chunk;
                        scrollToBottom();
                    }

                    if (data.done) {
                        if (streamingMessage.value) {
                            messages.value.push({ ...streamingMessage.value });
                            streamingMessage.value = null;
                        }
                        if (!currentSessionId.value && data.session_id) {
                            currentSessionId.value = data.session_id;
                        }
                    }

                    if (data.error) {
                        throw new Error(data.error);
                    }
                } catch (parseError) {
                    console.error('Error parsing stream chunk:', parseError, line);
                }
            }
        }
    } catch (e) {
        console.error(e);
        messages.value.push({
            id: Date.now() + 1,
            role: 'assistant',
            content: 'Sorry, I encountered an error processing your request.',
            createdAt: new Date().toISOString(),
        });
        scrollToBottom();
    } finally {
        isLoading.value = false;
        thinkingMessage.value = '';
    }
}
</script>

<template>
    <Head title="Cross-Document Chat" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 overflow-hidden">
            <!-- Sidebar: Recent Multi-Doc Sessions -->
            <div class="hidden lg:flex w-72 flex-col border-r border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Multi-Doc History</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Recent cross-doc sessions</p>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <Link
                        :href="chatRoutes.multi().url"
                        class="flex items-center gap-2 w-full p-3 rounded-lg text-sm transition font-medium"
                        :class="!currentSessionId ? 'bg-white dark:bg-gray-800 shadow-sm text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        <MessageSquare class="w-4 h-4" />
                        New Cross-Doc Chat
                    </Link>
                    <div class="border-t border-gray-200 dark:border-gray-800 my-2"></div>
                    <div v-if="recentSessions.length === 0" class="p-4 text-xs text-center text-gray-500">
                        No previous multi-doc sessions
                    </div>
                    <Link
                        v-for="s in recentSessions"
                        :key="s.id"
                        :href="chatRoutes.multi.session(s.id).url"
                        class="flex flex-col gap-1 w-full p-3 rounded-lg text-left transition group"
                        :class="currentSessionId === s.id ? 'bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700' : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        <span class="text-sm font-medium truncate" :class="currentSessionId === s.id ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-400'">
                            {{ s.title }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500">
                            {{ new Date(s.createdAt).toLocaleDateString() }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="flex-1 flex flex-col min-w-0 bg-white dark:bg-gray-950 px-3">
                <!-- Chat Header -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <Link :href="documentsRoute().url" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg lg:hidden">
                                <ChevronLeft class="w-5 h-5" />
                            </Link>
                            <div class="flex items-center gap-2">
                                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                                    <Files class="w-5 h-5" />
                                </div>
                                <h1 class="text-sm font-bold text-gray-900 dark:text-white truncate">Cross-Document Chat</h1>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold uppercase tracking-wider ring-1 ring-inset ring-indigo-500/20">
                                {{ files.length }} Sources
                            </span>
                        </div>
                    </div>
                    
                    <!-- Source Pills -->
                    <div class="flex flex-wrap gap-2">
                        <div v-for="file in files" :key="file.id" class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 max-w-[200px]">
                            <BookOpen class="w-3 h-3 text-gray-400" />
                            <span class="text-[10px] font-medium text-gray-600 dark:text-gray-400 truncate">{{ file.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div 
                    ref="scrollContainer"
                    class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6"
                >
                    <div v-if="messages.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-4 py-12">
                        <div class="w-20 h-20 rounded-3xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center">
                            <Sparkles class="w-10 h-10 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="max-w-md mx-auto">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Multi-Source Intelligence</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                Ask questions that span across all {{ files.length }} selected documents. I can compare information, identify patterns, and synthesize answers from all sources simultaneously.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-lg mt-8">
                            <button 
                                @click="messageInput = 'Summarize the main differences between these documents.'; send();"
                                class="p-4 text-left rounded-2xl border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 text-xs font-medium transition group"
                            >
                                <span class="block text-indigo-600 dark:text-indigo-400 mb-1">Compare</span>
                                "Summarize the main differences between these documents."
                            </button>
                            <button 
                                @click="messageInput = 'Identify common themes across all these sources.'; send();"
                                class="p-4 text-left rounded-2xl border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 text-xs font-medium transition group"
                            >
                                <span class="block text-indigo-600 dark:text-indigo-400 mb-1">Synthesize</span>
                                "Identify common themes across all these sources."
                            </button>
                        </div>
                    </div>

                    <div v-for="m in messages" :key="m.id" class="flex gap-4" :class="m.role === 'user' ? 'flex-row-reverse' : ''">
                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center" :class="m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600' ">
                            <User v-if="m.role === 'user'" class="w-4 h-4" />
                            <Sparkles v-else class="w-4 h-4" />
                        </div>
                        <div class="flex flex-col max-w-[85%] sm:max-w-[75%] gap-1" :class="m.role === 'user' ? 'items-end' : ''">
                            <div 
                                class="rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap shadow-sm border"
                                :class="m.role === 'user' 
                                    ? 'bg-indigo-600 text-white border-indigo-500 rounded-tr-none' 
                                    : 'bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border-gray-100 dark:border-gray-800 rounded-tl-none' "
                            >
                                {{ m.content }}
                            </div>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-tight font-medium">
                                {{ new Date(m.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                        </div>
                    </div>

                    <!-- Streaming Message -->
                    <div v-if="streamingMessage" class="flex gap-4">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600">
                            <Sparkles class="w-4 h-4" />
                        </div>
                        <div class="flex flex-col max-w-[85%] sm:max-w-[75%] gap-1">
                            <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap shadow-sm border bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border-gray-100 dark:border-gray-800 rounded-tl-none">
                                {{ streamingMessage.content }}
                                <span class="inline-block w-1.5 h-4 bg-indigo-500 animate-pulse align-middle ml-0.5"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Thinking Indicator -->
                    <div v-if="isLoading && !streamingMessage" class="flex gap-4">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <Sparkles class="w-4 h-4 text-indigo-400 animate-pulse" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl rounded-tl-none px-4 py-3 flex gap-3 items-center min-w-[140px]">
                                <div class="flex gap-1">
                                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce"></span>
                                </div>
                                <span v-if="thinkingMessage" class="text-xs font-medium text-gray-500 dark:text-gray-400 animate-in fade-in slide-in-from-left-2 duration-300">
                                    {{ thinkingMessage }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="p-4 sm:p-6 border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
                    <form @submit.prevent="send" class="relative group">
                        <textarea
                            v-model="messageInput"
                            rows="1"
                            placeholder="Ask a question across all documents..."
                            class="block w-full rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white pl-4 pr-12 py-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition resize-none leading-relaxed"
                            @keydown.enter.prevent="send"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="isLoading || !messageInput.trim()"
                            class="absolute right-2.5 top-2 p-2.5 rounded-xl bg-indigo-600 text-white disabled:opacity-30 disabled:cursor-not-allowed hover:bg-indigo-700 transition shadow-sm"
                        >
                            <Send class="w-5 h-5" />
                        </button>
                    </form>
                    <div class="flex items-center justify-center gap-4 mt-3">
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium">
                            AI is drawing context from {{ files.length }} selected sources.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
