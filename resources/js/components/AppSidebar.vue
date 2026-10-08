<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, History, LayoutGrid, MessageSquare, Youtube } from 'lucide-vue-next';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { history as chatHistory } from '@/routes/chat';
import { dashboard, documents, imageGenerator, knowledgeBase, policyQa, ragQa, summary } from '@/routes';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Youtube Summary',
        href: summary(),
        icon: MessageSquare,
    },
    {
        title: 'Policy Q&A',
        href: policyQa(),
        icon: MessageSquare,
    },
    {
        title: 'RAG Q&A',
        href: ragQa(),
        icon: MessageSquare,
    },
    {
        title: 'Image Generator',
        href: imageGenerator(),
        icon: MessageSquare,
    },
    {
        title: 'Documents',
        href: documents(),
        icon: Folder,
    },
    // {
    //     title: 'Knowledge Base',
    //     href: knowledgeBase(),
    //     icon: BookOpen,
    // },
    {
        title: 'Chat History',
        href: chatHistory(),
        icon: History,
    },
];

const footerNavItems: NavItem[] = [
    
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-border bg-sidebar transition-colors duration-200">
        <SidebarHeader class="border-b border-border py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-muted/50 transition-all rounded-lg">
                        <Link :href="dashboard()" prefetch cache-for="1m">
                            <AppLogo />
                            <span class="font-heading font-extrabold text-foreground ml-3 tracking-tight">Synapse</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="py-4 px-2">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-border py-4 px-2">
            <NavFooter :items="footerNavItems" />
            <div class="mt-2">
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
