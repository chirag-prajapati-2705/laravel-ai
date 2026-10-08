<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Database, MessageSquare, Sparkles, Zap, ArrowRight } from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen w-full bg-background text-foreground font-sans selection:bg-primary/10 transition-colors duration-500">
        <!-- Navigation -->
        <nav class="relative flex items-center justify-between px-6 py-6 lg:px-12 z-50 border-b border-border/50 bg-background/80 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary shadow-lg shadow-primary/20 transition-transform hover:scale-105">
                    <Sparkles class="h-5 w-5 text-primary-foreground" />
                </div>
                <span class="font-heading text-xl font-extrabold tracking-tight">Synapse</span>
            </div>

            <div class="flex items-center gap-6">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="pro-button bg-primary text-primary-foreground hover:opacity-90 shadow-sm"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-semibold text-muted-foreground hover:text-foreground transition"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="pro-button bg-primary text-primary-foreground shadow-lg shadow-primary/10 hover:opacity-90"
                    >
                        Register
                    </Link>
                </template>
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="flex flex-col items-center justify-center px-6 pt-24 pb-32 lg:px-12">
            <div class="max-w-4xl text-center space-y-8 animate-fade-in">
                <!-- Tagline -->
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-primary/20 bg-primary/5 text-primary text-xs font-bold uppercase tracking-widest">
                    <Sparkles class="h-3 w-3" />
                    Professional AI Workspace
                </div>

                <h1 class="font-heading text-5xl font-extrabold tracking-tight lg:text-7xl leading-tight">
                    Intelligence <br />
                    <span class="text-primary italic">Perfectly Aligned.</span>
                </h1>

                <p class="mx-auto max-w-2xl text-lg text-muted-foreground lg:text-xl leading-relaxed">
                    A minimal, high-precision AI environment designed for professional research. Ingest documents, organize knowledge, and chat with dimensional clarity.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-8">
                    <Link
                        :href="canRegister ? register() : login()"
                        class="pro-button bg-primary text-primary-foreground flex items-center gap-2 px-8 py-4 rounded-xl text-lg hover:opacity-90 shadow-xl shadow-primary/10"
                    >
                        <span>Get Started Free</span>
                        <ArrowRight class="h-5 w-5" />
                    </Link>
                    <a
                        href="#features"
                        class="pro-button border border-border bg-card text-foreground flex items-center gap-2 px-8 py-4 rounded-xl text-lg hover:bg-muted/50"
                    >
                        Explore Features
                    </a>
                </div>
            </div>

            <!-- Feature Grid -->
            <div id="features" class="mt-40 grid w-full max-w-6xl grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Feature 1 -->
                <div class="minimal-card p-8 space-y-4">
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <BookOpen class="h-6 w-6 text-primary" />
                    </div>
                    <h3 class="font-heading text-xl font-bold">PDF Ingestion</h3>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Precision extraction of intelligence from complex documents with full spatial context.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="minimal-card p-8 space-y-4">
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <Database class="h-6 w-6 text-primary" />
                    </div>
                    <h3 class="font-heading text-xl font-bold">Vector Vault</h3>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Enterprise-grade semantic storage powered by high-dimensional vector search.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="minimal-card p-8 space-y-4">
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <MessageSquare class="h-6 w-6 text-primary" />
                    </div>
                    <h3 class="font-heading text-xl font-bold">Deep Conversations</h3>
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Focused AI chatting with deep multi-document awareness and source citations.
                    </p>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-20 border-t border-border py-12 px-6 lg:px-12 text-center text-sm text-muted-foreground/60">
            <p>&copy; 2026 Synapse AI Pro. Minimal. Professional. Sentient.</p>
        </footer>
    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.8s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
