<script setup lang="ts">
/**
 * El murciélago guía de Noctua, animado: flota batiendo las alas (más rápido
 * cuando habla), parpadea, mueve la boca, inclina la cabeza en cada paso y
 * mira hacia lo que está señalando.
 */
const props = withDefaults(
    defineProps<{
        /** Está «hablando»: bate las alas más rápido y mueve la boca. */
        talking?: boolean;
        /** Hacia dónde mira, de -1 a 1 en cada eje. */
        lookX?: number;
        lookY?: number;
        /** Cambia en cada paso para que incline la cabeza. */
        tilt?: number;
    }>(),
    { talking: false, lookX: 0, lookY: 0, tilt: 0 },
);

const pupil = (axis: number) => Math.max(-1, Math.min(1, axis)) * 2.6;
</script>

<template>
    <svg
        viewBox="0 0 200 150"
        class="guide-bat overflow-visible"
        :class="{ 'is-talking': props.talking }"
        role="img"
        aria-label="Murciélago guía de Noctua"
    >
        <!-- Sombra en el suelo: se encoge cuando sube. -->
        <ellipse
            cx="100"
            cy="144"
            rx="34"
            ry="4"
            class="bat-shadow fill-black/15"
        />

        <g class="bat-float">
            <!-- Ala izquierda: membrana festoneada con sus «dedos». -->
            <g class="bat-wing bat-wing-left">
                <path
                    d="M74 70 C58 46 30 36 8 44 C14 52 16 60 14 68 C22 64 30 66 34 74 C40 68 50 70 54 78 C60 72 68 74 74 82 Z"
                    fill="#3f3557"
                />
                <path
                    d="M74 72 L10 46 M72 76 L30 70 M72 80 L50 76"
                    stroke="#2a2340"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    fill="none"
                />
            </g>
            <!-- Ala derecha -->
            <g class="bat-wing bat-wing-right">
                <path
                    d="M126 70 C142 46 170 36 192 44 C186 52 184 60 186 68 C178 64 170 66 166 74 C160 68 150 70 146 78 C140 72 132 74 126 82 Z"
                    fill="#3f3557"
                />
                <path
                    d="M126 72 L190 46 M128 76 L170 70 M128 80 L150 76"
                    stroke="#2a2340"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    fill="none"
                />
            </g>

            <!-- Cuerpo -->
            <ellipse cx="100" cy="96" rx="28" ry="30" fill="#4b3f66" />
            <ellipse cx="100" cy="102" rx="17" ry="20" fill="#7d6f9c" />
            <!-- Pelo del pecho -->
            <g
                fill="none"
                stroke="#9a8db8"
                stroke-width="1.4"
                stroke-linecap="round"
            >
                <path d="M93 96 q3 3 6 0 M101 96 q3 3 6 0" />
                <path d="M90 106 q3 3 6 0 M98 106 q3 3 6 0 M106 106 q3 3 6 0" />
            </g>

            <!-- Librito entre las patas -->
            <g class="bat-book">
                <path
                    d="M82 120 L100 115 L118 120 L118 131 L100 126 L82 131 Z"
                    fill="#fdfaf3"
                    stroke="#2a2340"
                    stroke-width="1.4"
                    stroke-linejoin="round"
                />
                <path
                    d="M100 115 L100 126"
                    stroke="#2a2340"
                    stroke-width="1.4"
                />
                <path
                    d="M87 122 L97 119.5 M87 126 L97 123.5 M103 119.5 L113 122 M103 123.5 L113 126"
                    stroke="#b9aed1"
                    stroke-width="1"
                />
            </g>
            <!-- Patitas -->
            <g fill="#2a2340">
                <ellipse cx="92" cy="132" rx="4" ry="2.2" />
                <ellipse cx="108" cy="132" rx="4" ry="2.2" />
            </g>

            <!-- Cabeza -->
            <g class="bat-head" :style="{ '--tilt': `${props.tilt}deg` }">
                <!-- Orejas grandes -->
                <path d="M76 52 L68 14 L94 40 Z" fill="#4b3f66" />
                <path d="M124 52 L132 14 L106 40 Z" fill="#4b3f66" />
                <path d="M78 46 L73 24 L89 40 Z" fill="#c98aa8" />
                <path d="M122 46 L127 24 L111 40 Z" fill="#c98aa8" />

                <ellipse cx="100" cy="56" rx="28" ry="24" fill="#4b3f66" />
                <!-- Carita -->
                <ellipse cx="100" cy="62" rx="20" ry="15" fill="#7d6f9c" />

                <!-- Ojos -->
                <circle cx="89" cy="54" r="9" fill="#fff" />
                <circle cx="111" cy="54" r="9" fill="#fff" />
                <g
                    class="bat-pupils"
                    :style="{
                        transform: `translate(${pupil(props.lookX)}px, ${pupil(props.lookY)}px)`,
                    }"
                >
                    <circle cx="89" cy="54" r="5" fill="#16111f" />
                    <circle cx="111" cy="54" r="5" fill="#16111f" />
                    <circle cx="90.8" cy="52" r="1.7" fill="#fff" />
                    <circle cx="112.8" cy="52" r="1.7" fill="#fff" />
                </g>
                <!-- Párpados -->
                <g class="bat-lids" fill="#4b3f66">
                    <ellipse cx="89" cy="54" rx="10" ry="10" />
                    <ellipse cx="111" cy="54" rx="10" ry="10" />
                </g>
                <!-- Mejillas -->
                <ellipse
                    cx="82"
                    cy="66"
                    rx="4"
                    ry="2.4"
                    fill="#e7a3c0"
                    opacity="0.7"
                />
                <ellipse
                    cx="118"
                    cy="66"
                    rx="4"
                    ry="2.4"
                    fill="#e7a3c0"
                    opacity="0.7"
                />
                <!-- Nariz -->
                <path
                    d="M97 62 q3 2 6 0"
                    fill="none"
                    stroke="#2a2340"
                    stroke-width="1.4"
                    stroke-linecap="round"
                />

                <!-- Boca con colmillitos -->
                <g class="bat-mouth">
                    <path
                        d="M93 67 Q100 73 107 67 Q100 70 93 67 Z"
                        fill="#2a2340"
                    />
                    <path
                        d="M95 67.6 L96.4 71.5 L97.8 68.6 Z M102.2 68.6 L103.6 71.5 L105 67.6 Z"
                        fill="#fff"
                    />
                </g>
            </g>
        </g>
    </svg>
</template>

<style scoped>
.bat-float {
    animation: bat-float 2.6s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

.bat-shadow {
    transform-origin: 100px 144px;
    animation: bat-shadow 2.6s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}

.bat-head {
    transform: rotate(var(--tilt, 0deg));
    transform-origin: 100px 70px;
    transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.bat-pupils {
    transition: transform 0.4s ease-out;
}

.bat-lids {
    transform: scaleY(0);
    transform-origin: 100px 44px;
    animation: bat-blink 4.2s infinite;
}

/* Las alas baten siempre, despacio; al hablar, más rápido. */
.bat-wing-left {
    transform-origin: 76px 74px;
    animation: bat-flap-left 1.3s ease-in-out infinite;
}

.bat-wing-right {
    transform-origin: 124px 74px;
    animation: bat-flap-right 1.3s ease-in-out infinite;
}

.is-talking .bat-wing-left,
.is-talking .bat-wing-right {
    animation-duration: 0.45s;
}

.bat-mouth {
    transform-origin: 100px 67px;
}

.is-talking .bat-mouth {
    animation: bat-talk 0.22s ease-in-out infinite alternate;
}

@keyframes bat-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

@keyframes bat-shadow {
    0%,
    100% {
        transform: scaleX(1);
        opacity: 1;
    }
    50% {
        transform: scaleX(0.8);
        opacity: 0.6;
    }
}

@keyframes bat-blink {
    0%,
    90%,
    100% {
        transform: scaleY(0);
    }
    94% {
        transform: scaleY(1);
    }
}

@keyframes bat-flap-left {
    0%,
    100% {
        transform: rotate(0deg) scaleY(1);
    }
    50% {
        transform: rotate(-24deg) scaleY(0.8);
    }
}

@keyframes bat-flap-right {
    0%,
    100% {
        transform: rotate(0deg) scaleY(1);
    }
    50% {
        transform: rotate(24deg) scaleY(0.8);
    }
}

@keyframes bat-talk {
    from {
        transform: scaleY(1);
    }
    to {
        transform: scaleY(1.6);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bat-float,
    .bat-shadow,
    .bat-lids,
    .bat-wing-left,
    .bat-wing-right,
    .is-talking .bat-mouth {
        animation: none;
    }
}
</style>
