<script setup lang="ts">
import { Form, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    ChevronsUpDown,
    Plus,
    Settings,
    User,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PublisherController from '@/actions/App/Http/Controllers/PublisherController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { edit as workspaceSettings } from '@/routes/workspace';
import { switchMethod } from '@/routes/workspaces';

const page = usePage();
const current = computed(() => page.props.auth.publisher);
const workspaces = computed(() => page.props.auth.publishers);
const { isMobile, state } = useSidebar();
const creating = ref(false);

function switchTo(id: number) {
    if (id !== current.value?.id) {
        router.put(switchMethod().url, { publisher_id: id });
    }
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        :tooltip="current?.name ?? 'Espacio'"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                        >
                            <User v-if="current?.personal" class="size-4" />
                            <Building2 v-else class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">
                                {{ current?.name ?? 'Noctua' }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{
                                    current?.personal
                                        ? 'Espacio personal'
                                        : 'Editorial'
                                }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-60 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'right'
                              : 'bottom'
                    "
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Espacios de trabajo
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="workspace in workspaces"
                        :key="workspace.id"
                        class="gap-2"
                        @select="switchTo(workspace.id)"
                    >
                        <User v-if="workspace.personal" class="size-4" />
                        <Building2 v-else class="size-4" />
                        <span class="flex-1 truncate">{{
                            workspace.name
                        }}</span>
                        <Check
                            v-if="workspace.id === current?.id"
                            class="size-4"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem class="gap-2" @select="creating = true">
                        <Plus class="size-4" /> Crear una editorial
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        class="gap-2"
                        @select="router.visit(workspaceSettings().url)"
                    >
                        <Settings class="size-4" /> Ajustes del espacio
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>

    <Dialog v-model:open="creating">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Crear una editorial</DialogTitle>
                <DialogDescription>
                    Un espacio compartido para trabajar en libros con otras
                    personas. Tus libros personales siguen en «Personal».
                </DialogDescription>
            </DialogHeader>
            <Form
                v-bind="PublisherController.store.form()"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="creating = false"
            >
                <div class="grid gap-2">
                    <Label for="workspace-name">Nombre de la editorial</Label>
                    <Input
                        id="workspace-name"
                        name="name"
                        required
                        autofocus
                        placeholder="Ediciones del Búho"
                    />
                    <InputError :message="errors.name" />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="creating = false"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="processing">Crear</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
