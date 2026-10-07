<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpen,
    ChevronDown,
    Compass,
    FlaskConical,
    Pause,
    PenLine,
    Play,
    Printer,
    Search,
    Type,
    UserRound,
    Users,
    X,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as activityIndex } from '@/routes/activity';

type ActivityEvent = {
    id: number;
    type: string;
    category: string;
    description: string;
    actor: string;
    actor_is_demo: boolean;
    book_title: string | null;
    properties: Record<string, unknown> | null;
    session: string | null;
    ip: string | null;
    browser: string | null;
    url: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    categoria: string;
    persona: string;
    sesion: string;
    buscar: string;
    navegacion: boolean;
};

const props = defineProps<{
    events: Paginated<ActivityEvent>;
    filters: Filters;
    categories: Record<string, string>;
    people: { name: string; demo: boolean; last: string }[];
    stats: {
        active_now: number;
        people_today: number;
        demo_accounts: number;
        books_today: number;
        prints_today: number;
        errors_today: number;
    };
}>();

setLayoutProps({
    breadcrumbs: [{ title: 'Actividad', href: activityIndex() }],
});

// --- Aspecto de cada categoría ---
const categoryStyle: Record<string, { icon: Component; color: string }> = {
    cuenta: { icon: UserRound, color: 'bg-sky-500' },
    espacio: { icon: Users, color: 'bg-indigo-500' },
    libro: { icon: BookOpen, color: 'bg-emerald-500' },
    edicion: { icon: PenLine, color: 'bg-amber-500' },
    impresion: { icon: Printer, color: 'bg-violet-500' },
    fuente: { icon: Type, color: 'bg-pink-500' },
    error: { icon: AlertTriangle, color: 'bg-red-500' },
    navegacion: { icon: Compass, color: 'bg-slate-400' },
};

const styleFor = (category: string) =>
    categoryStyle[category] ?? categoryStyle.navegacion;

// --- Filtros ---
const search = ref(props.filters.buscar);

function applyFilters(changes: Partial<Filters>) {
    const next = { ...props.filters, ...changes };
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries(next)) {
        if (value === true) {
            query[key] = '1';
        } else if (typeof value === 'string' && value !== '') {
            query[key] = value;
        }
    }

    router.get(activityIndex.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters({ buscar: value }), 350);
});

const hasFilters = computed(
    () =>
        props.filters.categoria !== '' ||
        props.filters.persona !== '' ||
        props.filters.sesion !== '' ||
        props.filters.buscar !== '',
);

function clearFilters() {
    search.value = '';
    applyFilters({ categoria: '', persona: '', sesion: '', buscar: '' });
}

// --- En vivo: recarga los eventos cada pocos segundos en la primera página ---
const live = ref(true);
const lastUpdate = ref(new Date());
const fresh = ref(new Set<number>());
let knownIds = new Set(props.events.data.map((e) => e.id));
let liveTimer: ReturnType<typeof setInterval> | undefined;

function refresh() {
    if (
        !live.value ||
        props.events.current_page !== 1 ||
        document.visibilityState !== 'visible'
    ) {
        return;
    }

    router.reload({
        only: ['events', 'stats', 'people'],
        onSuccess: () => {
            lastUpdate.value = new Date();
        },
    });
}

// Marca durante unos segundos los eventos que acaban de llegar.
watch(
    () => props.events.data,
    (events) => {
        const arrived = events
            .map((e) => e.id)
            .filter((id) => !knownIds.has(id));
        knownIds = new Set(events.map((e) => e.id));

        if (arrived.length > 0) {
            fresh.value = new Set([...fresh.value, ...arrived]);
            setTimeout(() => {
                fresh.value = new Set(
                    [...fresh.value].filter((id) => !arrived.includes(id)),
                );
            }, 4000);
        }
    },
);

onMounted(() => {
    liveTimer = setInterval(refresh, 5000);
});

onBeforeUnmount(() => {
    clearInterval(liveTimer);
    clearTimeout(searchTimer);
});

// --- Agrupar por día ---
const dayFormat = new Intl.DateTimeFormat('es', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
const timeFormat = new Intl.DateTimeFormat('es', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

function dayLabel(date: Date): string {
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);

    if (date.toDateString() === today.toDateString()) {
        return 'Hoy';
    }

    if (date.toDateString() === yesterday.toDateString()) {
        return 'Ayer';
    }

    const label = dayFormat.format(date);

    return label.charAt(0).toUpperCase() + label.slice(1);
}

const days = computed(() => {
    const groups: { label: string; events: ActivityEvent[] }[] = [];

    for (const event of props.events.data) {
        const label = dayLabel(new Date(event.created_at));
        const last = groups[groups.length - 1];

        if (last && last.label === label) {
            last.events.push(event);
        } else {
            groups.push({ label, events: [event] });
        }
    }

    return groups;
});

// --- Detalles desplegables ---
const open = ref(new Set<number>());

function toggle(id: number) {
    const next = new Set(open.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    open.value = next;
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'sí' : 'no';
    }

    if (Array.isArray(value)) {
        return value.length === 0 ? '—' : value.map(formatValue).join(', ');
    }

    if (typeof value === 'object') {
        return Object.entries(value as Record<string, unknown>)
            .map(([k, v]) => `${k.replaceAll('_', ' ')}: ${formatValue(v)}`)
            .join(' · ');
    }

    return String(value);
}

const statCards = computed(() => [
    {
        label: 'Conectados ahora',
        value: props.stats.active_now,
        hint: 'sesiones en los últimos 10 min',
    },
    {
        label: 'Personas hoy',
        value: props.stats.people_today,
        hint: 'con alguna actividad',
    },
    {
        label: 'Pruebas activas',
        value: props.stats.demo_accounts,
        hint: 'cuentas de prueba sin caducar',
    },
    {
        label: 'Libros subidos hoy',
        value: props.stats.books_today,
        hint: 'PDF nuevos',
    },
    {
        label: 'PDF generados hoy',
        value: props.stats.prints_today,
        hint: 'para imprimir',
    },
    {
        label: 'Errores hoy',
        value: props.stats.errors_today,
        hint: 'revisar si no es cero',
        alert: props.stats.errors_today > 0,
    },
]);
</script>

<template>
    <Head title="Actividad" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Actividad</h1>
                <p class="text-sm text-muted-foreground">
                    Todo lo que se hace en Noctua: quién, qué y cuándo. Se
                    conserva 90 días, también de las cuentas de prueba ya
                    borradas.
                </p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="flex items-center gap-2 text-muted-foreground">
                    <span class="relative flex size-2.5">
                        <span
                            v-if="live"
                            class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75"
                        />
                        <span
                            class="relative inline-flex size-2.5 rounded-full"
                            :class="
                                live ? 'bg-emerald-500' : 'bg-muted-foreground'
                            "
                        />
                    </span>
                    {{
                        live
                            ? `En vivo · ${timeFormat.format(lastUpdate)}`
                            : 'En pausa'
                    }}
                </span>
                <Button size="sm" variant="outline" @click="live = !live">
                    <component :is="live ? Pause : Play" />
                    {{ live ? 'Pausar' : 'Reanudar' }}
                </Button>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div
                v-for="card in statCards"
                :key="card.label"
                class="rounded-xl border bg-card p-4"
                :class="card.alert ? 'border-red-500/50' : ''"
            >
                <p class="text-xs text-muted-foreground">{{ card.label }}</p>
                <p
                    class="mt-1 text-2xl font-semibold tabular-nums"
                    :class="card.alert ? 'text-red-600 dark:text-red-400' : ''"
                >
                    {{ card.value }}
                </p>
                <p class="mt-1 text-[11px] text-muted-foreground">
                    {{ card.hint }}
                </p>
            </div>
        </section>

        <section class="grid gap-3 rounded-xl border bg-card p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-56 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Buscar en descripciones, personas, libros o IP…"
                        aria-label="Buscar"
                    />
                </div>
                <select
                    :value="filters.persona"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    aria-label="Persona"
                    @change="
                        applyFilters({
                            persona: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <option value="">Todas las personas</option>
                    <option
                        v-for="person in people"
                        :key="person.name"
                        :value="person.name"
                    >
                        {{ person.name }}{{ person.demo ? ' (prueba)' : '' }}
                    </option>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="filters.navegacion"
                        @change="
                            applyFilters({
                                navegacion: ($event.target as HTMLInputElement)
                                    .checked,
                            })
                        "
                    />
                    Mostrar páginas visitadas
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :variant="filters.categoria === '' ? 'default' : 'outline'"
                    @click="applyFilters({ categoria: '' })"
                >
                    Todo
                </Button>
                <Button
                    v-for="(label, key) in categories"
                    :key="key"
                    size="sm"
                    :variant="filters.categoria === key ? 'default' : 'outline'"
                    @click="applyFilters({ categoria: String(key) })"
                >
                    <span
                        class="size-2 rounded-full"
                        :class="styleFor(String(key)).color"
                    />
                    {{ label }}
                </Button>
                <span
                    v-if="filters.sesion"
                    class="inline-flex items-center gap-1 rounded-full bg-accent px-3 py-1 text-xs"
                >
                    Sesión {{ filters.sesion }}
                    <button
                        type="button"
                        aria-label="Quitar filtro de sesión"
                        @click="applyFilters({ sesion: '' })"
                    >
                        <X class="size-3" />
                    </button>
                </span>
                <Button
                    v-if="hasFilters"
                    size="sm"
                    variant="ghost"
                    @click="clearFilters"
                >
                    <X /> Quitar filtros
                </Button>
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ events.total }} evento(s)
                </span>
            </div>
        </section>

        <section
            v-if="events.data.length === 0"
            class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
        >
            No hay actividad{{
                hasFilters ? ' con estos filtros' : ' todavía'
            }}.
        </section>

        <section v-for="day in days" :key="day.label" class="grid gap-2">
            <h2
                class="sticky top-0 z-10 bg-background/95 py-1 text-sm font-semibold text-muted-foreground backdrop-blur"
            >
                {{ day.label }}
            </h2>
            <ol class="relative grid gap-1 border-l pl-6">
                <li
                    v-for="event in day.events"
                    :key="event.id"
                    class="relative rounded-lg transition-colors duration-1000"
                    :class="fresh.has(event.id) ? 'bg-emerald-500/10' : ''"
                >
                    <span
                        class="absolute top-3 -left-[33px] flex size-5 items-center justify-center rounded-full text-white ring-4 ring-background"
                        :class="styleFor(event.category).color"
                    >
                        <component
                            :is="styleFor(event.category).icon"
                            class="size-3"
                        />
                    </span>
                    <button
                        type="button"
                        class="flex w-full flex-wrap items-start gap-x-3 gap-y-1 rounded-lg px-3 py-2 text-left hover:bg-accent/60"
                        :aria-expanded="open.has(event.id)"
                        @click="toggle(event.id)"
                    >
                        <time
                            class="w-16 shrink-0 pt-0.5 text-xs text-muted-foreground tabular-nums"
                            :datetime="event.created_at"
                        >
                            {{ timeFormat.format(new Date(event.created_at)) }}
                        </time>
                        <span
                            class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                event.actor_is_demo
                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                    : 'bg-muted'
                            "
                        >
                            <FlaskConical
                                v-if="event.actor_is_demo"
                                class="size-3"
                            />
                            {{ event.actor }}
                        </span>
                        <span
                            class="min-w-0 flex-1 text-sm"
                            :class="
                                event.category === 'error'
                                    ? 'text-red-600 dark:text-red-400'
                                    : ''
                            "
                        >
                            {{ event.description }}
                        </span>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="open.has(event.id) ? 'rotate-180' : ''"
                        />
                    </button>

                    <dl
                        v-if="open.has(event.id)"
                        class="mx-3 mb-2 grid gap-x-6 gap-y-1 rounded-md bg-muted/50 p-3 text-xs sm:grid-cols-[auto_1fr]"
                    >
                        <template
                            v-for="(value, key) in event.properties ?? {}"
                            :key="key"
                        >
                            <dt class="font-medium text-muted-foreground">
                                {{ String(key).replaceAll('_', ' ') }}
                            </dt>
                            <dd class="break-words">
                                {{ formatValue(value) }}
                            </dd>
                        </template>
                        <template v-if="event.book_title">
                            <dt class="font-medium text-muted-foreground">
                                libro
                            </dt>
                            <dd>{{ event.book_title }}</dd>
                        </template>
                        <dt class="font-medium text-muted-foreground">tipo</dt>
                        <dd>
                            <code>{{ event.type }}</code>
                        </dd>
                        <template v-if="event.session">
                            <dt class="font-medium text-muted-foreground">
                                sesión
                            </dt>
                            <dd>
                                <button
                                    type="button"
                                    class="underline underline-offset-2"
                                    @click="
                                        applyFilters({
                                            sesion: event.session ?? '',
                                        })
                                    "
                                >
                                    {{ event.session }} · ver todo lo de esta
                                    sesión
                                </button>
                            </dd>
                        </template>
                        <dt class="font-medium text-muted-foreground">desde</dt>
                        <dd>
                            {{ event.ip ?? '—' }}
                            {{ event.browser ? `· ${event.browser}` : '' }}
                        </dd>
                        <template v-if="event.url">
                            <dt class="font-medium text-muted-foreground">
                                dirección
                            </dt>
                            <dd class="break-all text-muted-foreground">
                                {{ event.url }}
                            </dd>
                        </template>
                    </dl>
                </li>
            </ol>
        </section>

        <nav
            v-if="events.last_page > 1"
            class="flex items-center justify-between gap-3 text-sm"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!events.prev_page_url"
                as-child
            >
                <Link
                    v-if="events.prev_page_url"
                    :href="events.prev_page_url"
                    preserve-scroll
                    >Más recientes</Link
                >
                <span v-else>Más recientes</span>
            </Button>
            <span class="text-muted-foreground">
                Página {{ events.current_page }} de {{ events.last_page }}
                <template v-if="events.current_page > 1">
                    · la actualización en vivo solo funciona en la primera
                </template>
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="!events.next_page_url"
                as-child
            >
                <Link
                    v-if="events.next_page_url"
                    :href="events.next_page_url"
                    preserve-scroll
                    >Más antiguos</Link
                >
                <span v-else>Más antiguos</span>
            </Button>
        </nav>
    </div>
</template>
