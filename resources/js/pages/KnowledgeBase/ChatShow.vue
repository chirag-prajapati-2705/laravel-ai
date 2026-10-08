<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BookOpen, ChevronLeft, MessageSquare, Send, User } from 'lucide-vue-next';
import { nextTick, onMounted, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { knowledgeBase } from '@/routes';
import { session as chatSession, show as chatShow, send as sendMessageAction } from '@/routes/chat';
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
    file: {
        id: number;
        name: string;
        status: string;
        chunksCount: number;
    };
    session: {
        id: number;
        title: string;
        messages: Message[];
    } | null;
    sessions: Session[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Knowledge Base', href: knowledgeBase().url },
    { title: `Chat: ${props.file.name}` },
];

const messageInput = ref('');
const messages = ref<Message[]>(props.session?.messages ?? []);
const currentSessionId = ref<number | null>(props.session?.id ?? null);
const isLoading = ref(false);
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
    
    // Add temporary user message to UI
    messages.value.push({
        id: Date.now(),
        role: 'user',
        content: userMessage,
        createdAt: new Date().toISOString(),
    });
    
    scrollToBottom();
    isLoading.value = true;

    try {
        const response = await fetch(sendMessageAction(props.file.id).url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': await getCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                message: userMessage,
                session_id: currentSessionId.value,
            }),
        });

        if (!response.ok) {
            throw new Error('Failed to send message');
        }

        const data = await response.json();
        
        // Update session ID if it was a new session
        if (!currentSessionId.value) {
            currentSessionId.value = data.session_id;
        }

        // Add assistant message to UI
        messages.value.push(data.message);
        scrollToBottom();
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
    }
}
</script>

<template>
    <Head title="Chat with Document" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 overflow-hidden">
            <!-- Sidebar: Sessions History for this file -->
            <div class="hidden lg:flex w-72 flex-col border-r border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50">
                <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider">Chat History</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sessions for this file</p>
                </div>
                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <Link
                        :href="chatShow(file.id).url"
                        class="flex items-center gap-2 w-full p-3 rounded-lg text-sm transition font-medium"
                        :class="!currentSessionId ? 'bg-white dark:bg-gray-800 shadow-sm text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                    >
                        <MessageSquare class="w-4 h-4" />
                        New Chat
                    </Link>
                    <div class="border-t border-gray-200 dark:border-gray-800 my-2"></div>
                    <div v-if="sessions.length === 0" class="p-4 text-xs text-center text-gray-500">
                        No previous sessions
                    </div>
                    <Link
                        v-for="s in sessions"
                        :key="s.id"
                        :href="chatSession({ file: file.id, session: s.id }).url"
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
                <div class="p-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <Link :href="knowledgeBase().url" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg lg:hidden">
                            <ChevronLeft class="w-5 h-5" />
                        </Link>
                        <div class="flex flex-col min-w-0">
                            <h1 class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ file.name }}</h1>
                            <div class="flex items-center gap-2">
                                <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                                <span class="text-[10px] sm:text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-tight">
                                    {{ file.chunksCount }} Knowledge Chunks Indexed
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div 
                    ref="scrollContainer"
                    class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6"
                >
                    <div v-if="messages.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-4 py-12">
                        <div class="w-16 h-16 rounded-2xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                            <MessageSquare class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="max-w-md mx-auto">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Chat with Document</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Ask questions about the content of this PDF. The AI will answer based strictly on the document's contents.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-lg mt-8">
                            <button 
                                @click="messageInput = 'Summarize this document for me.'; send();"
                                class="p-3 text-left rounded-xl border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 text-xs font-medium transition"
                            >
                                "Summarize this document for me."
                            </button>
                            <button 
                                @click="messageInput = 'What are the key takeaways?'; send();"
                                class="p-3 text-left rounded-xl border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900 text-xs font-medium transition"
                            >
                                "What are the key takeaways?"
                            </button>
                        </div>
                    </div>

                    <div v-for="m in messages" :key="m.id" class="flex gap-4" :class="m.role === 'user' ? 'flex-row-reverse' : ''">
                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center" :class="m.role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600' ">
                            <User v-if="m.role === 'user'" class="w-4 h-4" />
                            <BookOpen v-else class="w-4 h-4" />
                        </div>
                        <div class="flex flex-col max-w-[85%] sm:max-w-[75%] gap-1" :class="m.role === 'user' ? 'items-end' : ''">
                            <div 
                                class="rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap shadow-xs"
                                :class="m.role === 'user' 
                                    ? 'bg-blue-600 text-white rounded-tr-none' 
                                    : 'bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-100 dark:border-gray-800 rounded-tl-none' "
                            >
                                {{ m.content }}
                            </div>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-tight font-medium">
                                {{ new Date(m.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                            </span>
                        </div>
                    </div>

                    <!-- Thinking Indicator -->
                    <div v-if="isLoading" class="flex gap-4">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <BookOpen class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <div class="bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl rounded-tl-none px-4 py-3 flex gap-1 items-center h-11">
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce"></span>
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
                            placeholder="Ask a question..."
                            class="block w-full rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white pl-4 pr-12 py-3.5 text-sm shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition resize-none leading-relaxed"
                            @keydown.enter.prevent="send"
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="isLoading || !messageInput.trim()"
                            class="absolute right-2 top-1.5 p-2 rounded-xl bg-blue-600 text-white disabled:opacity-30 disabled:cursor-not-allowed hover:bg-blue-700 transition shadow-sm"
                        >
                            <Send class="w-5 h-5" />
                        </button>
                    </form>
                    <p class="text-[10px] text-center text-gray-400 dark:text-gray-500 mt-2 font-medium">
                        AI may provide inaccurate information. Verify critical data.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
