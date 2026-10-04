<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Building2, LogOut, Trash2, User } from '@lucide/vue';
import { computed } from 'vue';
import PublisherController from '@/actions/App/Http/Controllers/PublisherController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/workspace';
import { destroy, update } from '@/routes/workspace/members';
import type { WorkspaceRole } from '@/types/auth';

const props = defineProps<{
    workspace: {
        id: number;
        name: string;
        personal: boolean;
        role: WorkspaceRole | null;
    };
    members: {
        id: number;
        name: string;
        email: string;
        role: WorkspaceRole | null;
    }[];
    roles: { value: WorkspaceRole; label: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Espacio de trabajo', href: edit() }],
    },
});

const isOwner = computed(() => props.workspace.role === 'owner');
const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

function changeRole(memberId: number, role: string) {
    router.put(update(memberId).url, { role }, { preserveScroll: true });
}

function remove(member: { id: number; name: string }, self: boolean) {
    const question = self
        ? `¿Salir de «${props.workspace.name}»? Dejarás de ver sus libros.`
        : `¿Quitar a ${member.name} de la editorial?`;

    if (window.confirm(question)) {
        router.delete(destroy(member.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Espacio de trabajo" />

    <div class="flex flex-col space-y-8">
        <div class="flex items-center gap-3">
            <div
                class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground"
            >
                <User v-if="workspace.personal" class="size-5" />
                <Building2 v-else class="size-5" />
            </div>
            <Heading
                variant="small"
                :title="workspace.name"
                :description="
                    workspace.personal
                        ? 'Tu espacio personal: solo tú ves estos libros.'
                        : 'Editorial: sus miembros comparten libros, fuentes e imposiciones.'
                "
            />
        </div>

        <Form
            v-if="isOwner"
            v-bind="PublisherController.update.form()"
            class="grid max-w-md gap-2"
            v-slot="{ errors, processing }"
        >
            <Label for="name">Nombre</Label>
            <div class="flex gap-2">
                <Input
                    id="name"
                    name="name"
                    :default-value="workspace.name"
                    required
                />
                <Button type="submit" :disabled="processing">Guardar</Button>
            </div>
            <InputError :message="errors.name" />
        </Form>

        <section
            v-if="workspace.personal"
            class="max-w-xl rounded-lg border p-4 text-sm text-muted-foreground"
        >
            Para trabajar con otras personas, crea una editorial desde el
            selector de espacios (arriba en el menú) y añade a tus compañeros.
        </section>

        <section v-else class="grid gap-4" aria-labelledby="miembros">
            <Heading
                id="miembros"
                variant="small"
                title="Miembros"
                description="Los propietarios administran la editorial; los editores trabajan en los libros."
            />

            <ul class="divide-y rounded-lg border">
                <li
                    v-for="member in members"
                    :key="member.id"
                    class="flex flex-wrap items-center gap-3 p-3"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ member.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ member.email }}
                        </p>
                    </div>
                    <select
                        v-if="isOwner"
                        :class="selectClass"
                        :value="member.role"
                        :aria-label="`Rol de ${member.name}`"
                        @change="
                            changeRole(
                                member.id,
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                            class="bg-background"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <span v-else class="text-xs text-muted-foreground">
                        {{ roles.find((r) => r.value === member.role)?.label }}
                    </span>
                    <Button
                        v-if="member.id === $page.props.auth.user.id"
                        variant="ghost"
                        size="sm"
                        @click="remove(member, true)"
                    >
                        <LogOut /> Salir
                    </Button>
                    <Button
                        v-else-if="isOwner"
                        variant="ghost"
                        size="icon"
                        class="text-destructive"
                        :aria-label="`Quitar a ${member.name}`"
                        @click="remove(member, false)"
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>

            <Form
                v-if="isOwner"
                v-bind="PublisherController.addMember.form()"
                class="grid max-w-xl gap-2"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <Label for="email">Añadir un miembro</Label>
                <div class="flex flex-wrap gap-2">
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        placeholder="correo@ejemplo.com"
                        class="min-w-56 flex-1"
                    />
                    <select
                        name="role"
                        :class="selectClass"
                        aria-label="Rol del nuevo miembro"
                    >
                        <option
                            v-for="r in roles"
                            :key="r.value"
                            :value="r.value"
                            :selected="r.value === 'editor'"
                            class="bg-background"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <Button type="submit" :disabled="processing">Añadir</Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    La persona debe tener ya una cuenta en Noctua.
                </p>
                <InputError :message="errors.email ?? errors.role" />
            </Form>
        </section>
    </div>
</template>
