<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpenText, LayoutGrid, Library } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard } from '@/routes';
import { index as books } from '@/routes/books';
import { create as imposition } from '@/routes/imposition';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Libros',
        href: books(),
        icon: Library,
    },
    {
        title: 'Imposición rápida',
        href: imposition(),
        icon: BookOpenText,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
