<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { CheckCircle2, XCircle, X } from 'lucide-vue-next';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref<'success' | 'error'>('success');
let timeout: ReturnType<typeof setTimeout> | null = null;

function show(msg: string, t: 'success' | 'error' = 'success') {
    if (timeout) clearTimeout(timeout);
    
    message.value = msg;
    type.value = t;
    visible.value = true;
    
    timeout = setTimeout(() => {
        visible.value = false;
    }, 5000);
}

watch(
    () => page.props.flash,
    (flash: any) => {
        if (flash?.success) {
            show(flash.success, 'success');
        } else if (flash?.error) {
            show(flash.error, 'error');
        }
    },
    { deep: true, immediate: true }
);

function close() {
    visible.value = false;
}
</script>

<template>
    <div
        v-if="visible"
        class="fixed top-4 right-4 z-[100] flex w-full max-w-sm items-center gap-3 overflow-hidden rounded-2xl border bg-card p-4 shadow-2xl transition-all duration-300 animate-in fade-in slide-in-from-top-4"
        :class="type === 'success' ? 'border-emerald-500/20 shadow-emerald-500/10' : 'border-red-500/20 shadow-red-500/10'"
    >
        <div 
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
            :class="type === 'success' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'"
        >
            <CheckCircle2 v-if="type === 'success'" class="h-5 w-5" />
            <XCircle v-else class="h-5 w-5" />
        </div>
        
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-foreground">
                {{ type === 'success' ? 'Success' : 'Error' }}
            </p>
            <p class="mt-0.5 text-xs text-muted-foreground truncate">
                {{ message }}
            </p>
        </div>
        
        <button 
            @click="close"
            class="ml-auto rounded-lg p-1 text-muted-foreground/50 transition hover:bg-muted hover:text-foreground"
        >
            <X class="h-4 w-4" />
        </button>
        
        <div 
            class="absolute bottom-0 left-0 h-1 transition-all duration-[5000ms] ease-linear"
            :class="[
                type === 'success' ? 'bg-emerald-500' : 'bg-red-500',
                visible ? 'w-full' : 'w-0'
            ]"
        ></div>
    </div>
</template>
