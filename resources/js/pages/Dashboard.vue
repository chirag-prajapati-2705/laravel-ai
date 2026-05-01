<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { LayoutDashboard, Sparkles, TrendingUp, Users } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-6 lg:p-8 overflow-y-auto">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 class="font-heading text-3xl font-extrabold tracking-tight flex items-center gap-3">
                    <LayoutDashboard class="h-8 w-8 text-primary" />
                    Dashboard
                </h1>
                <p class="text-muted-foreground">Professional overview of your research intelligence and active cells.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid gap-6 md:grid-cols-3">
                <div class="minimal-card p-6 flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Knowledge base</p>
                        <h3 class="font-heading text-3xl font-extrabold">2,482</h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <Sparkles class="h-6 w-6 text-primary" />
                    </div>
                </div>

                <div class="minimal-card p-6 flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Total Threads</p>
                        <h3 class="font-heading text-3xl font-extrabold">412</h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <TrendingUp class="h-6 w-6 text-primary" />
                    </div>
                </div>

                <div class="minimal-card p-6 flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Active nodes</p>
                        <h3 class="font-heading text-3xl font-extrabold">12</h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-primary/5 flex items-center justify-center">
                        <Users class="h-6 w-6 text-primary" />
                    </div>
                </div>
            </div>

            <!-- Activity Area -->
            <div class="minimal-card p-8 min-h-[400px] flex flex-col">
                <div class="flex items-center justify-between mb-8 pb-4 border-b border-border/50">
                    <h2 class="font-heading text-xl font-extrabold">Research Activity</h2>
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-primary/5 border border-primary/10">
                        <div class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></div>
                        <span class="text-[10px] font-extrabold text-primary uppercase tracking-widest">Active Engine</span>
                    </div>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center border-2 border-dashed border-border rounded-xl">
                    <div class="text-center space-y-4">
                        <div class="h-16 w-16 rounded-full bg-muted/50 flex items-center justify-center mx-auto transition-transform hover:scale-110">
                            <Sparkles class="h-8 w-8 text-muted-foreground/30" />
                        </div>
                        <div>
                            <p class="text-foreground font-bold italic">Intelligence Engine Idling.</p>
                            <p class="text-sm text-muted-foreground max-w-xs mx-auto mt-2">Upload or select a document in the library to begin high-dimensional processing.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
