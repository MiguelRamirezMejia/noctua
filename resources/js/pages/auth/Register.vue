<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2, User } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import GuestAccess from '@/components/GuestAccess.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crea tu cuenta',
        description: 'Empieza a componer e imprimir tus libros en minutos.',
    },
});

const accountType = ref<'personal' | 'publisher'>('personal');

const options = [
    {
        value: 'personal',
        icon: User,
        title: 'Para mí',
        description: 'Tus propios libros, en tu espacio personal.',
    },
    {
        value: 'publisher',
        icon: Building2,
        title: 'Para una editorial',
        description: 'Un espacio compartido con tu equipo.',
    },
] as const;
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">
                ¿Cómo vas a usar Noctua?
            </legend>
            <div class="grid grid-cols-2 gap-3">
                <label
                    v-for="option in options"
                    :key="option.value"
                    class="flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-sm transition hover:border-primary/60 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                >
                    <input
                        v-model="accountType"
                        type="radio"
                        name="account_type"
                        :value="option.value"
                        class="sr-only"
                    />
                    <component :is="option.icon" class="size-5 text-primary" />
                    <span class="font-medium">{{ option.title }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        option.description
                    }}</span>
                </label>
            </div>
            <InputError :message="errors.account_type" />
        </fieldset>

        <div class="grid gap-5">
            <div v-if="accountType === 'publisher'" class="grid gap-2">
                <Label for="publisher_name">Nombre de la editorial</Label>
                <Input
                    id="publisher_name"
                    name="publisher_name"
                    required
                    placeholder="Ediciones del Búho"
                    autocomplete="organization"
                />
                <InputError :message="errors.publisher_name" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Tu nombre</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    autocomplete="name"
                    name="name"
                    placeholder="Nombre y apellidos"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="correo@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Repite la contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repite la contraseña"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Crear cuenta
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            ¿Ya tienes cuenta?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Inicia sesión</TextLink
            >
        </div>
    </Form>
    <GuestAccess compact class="mt-6" />
</template>
