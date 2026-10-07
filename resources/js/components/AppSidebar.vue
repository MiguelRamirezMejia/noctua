<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Activity, BookOpenText, LayoutGrid, Library, Type } from '@lucide/vue';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
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
import { index as activity } from '@/routes/activity';
import { index as books } from '@/routes/books';
import { index as fonts } from '@/routes/fonts';
import { create as imposition } from '@/routes/imposition';
import type { NavItem } from '@/types';

const page = usePage();

const baseNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Libros',
        href: books(),
        icon: Library,
        guide: 'nav-libros',
    },
    {
        title: 'Fuentes',
        href: fonts(),
        icon: Type,
    },
    {
        title: 'Imposición rápida',
        href: imposition(),
        icon: BookOpenText,
    },
];

// «Actividad» solo aparece para quien administra la instalación.
const mainNavItems = computed<NavItem[]>(() =>
    page.props.auth.can?.viewActivity
        ? [
              ...baseNavItems,
              { title: 'Actividad', href: activity(), icon: Activity },
          ]
        : baseNavItems,
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent data-guide="menu">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
