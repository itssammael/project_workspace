<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    toast: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const remaining = ref(props.toast.duration);
const startTime = ref(null);
const timerId = ref(null);
const paused = ref(false);

const startTimer = () => {
    startTime.value = Date.now();
    timerId.value = setTimeout(() => {
        emit('close');
    }, remaining.value);
    paused.value = false;
};

const pauseTimer = () => {
    clearTimeout(timerId.value);
    remaining.value = Math.max(0, remaining.value - (Date.now() - startTime.value));
    paused.value = true;
};

onMounted(() => {
    startTimer();
});

onBeforeUnmount(() => {
    clearTimeout(timerId.value);
});
</script>

<template>
    <div
        @mouseenter="pauseTimer"
        @mouseleave="startTimer"
        class="relative flex items-center justify-between gap-4 p-4 pr-10 rounded-xl shadow-xl border bg-white/95 dark:bg-slate-900/95 border-slate-100 dark:border-slate-800 backdrop-blur-md transition-all duration-300 transform hover:scale-[1.02] hover:-translate-y-0.5"
    >
        <!-- Accent line -->
        <div 
            class="absolute left-0 top-0 bottom-0 w-1.5 rounded-l-xl"
            :class="[toast.type === 'danger' ? 'bg-rose-500' : 'bg-emerald-500']"
        />

        <!-- Content Row -->
        <div class="flex items-center gap-3 pl-1.5">
            <!-- Icon -->
            <div class="flex-shrink-0">
                <svg v-if="toast.type === 'success'" class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-6 h-6 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>

            <!-- Message Text -->
            <div class="flex flex-col">
                <span class="font-semibold text-sm text-slate-800 dark:text-slate-100">
                    {{ toast.type === 'danger' ? 'Error' : 'Success' }}
                </span>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                    {{ toast.message }}
                </span>
            </div>
        </div>

        <!-- Close Button -->
        <button
            @click="emit('close')"
            class="absolute right-3 top-3 p-1 rounded-md text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition"
            aria-label="Close"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Progress Bar Indicator -->
        <div class="absolute bottom-0 left-0 right-0 h-1 overflow-hidden rounded-b-xl bg-slate-100 dark:bg-slate-800">
            <div
                class="h-full progress-bar-fill"
                :class="[toast.type === 'danger' ? 'bg-rose-500' : 'bg-emerald-500']"
                :style="{
                    animationDuration: toast.duration + 'ms',
                    animationPlayState: paused ? 'paused' : 'running'
                }"
            />
        </div>
    </div>
</template>

<style scoped>
@keyframes shrink {
    from { width: 100%; }
    to { width: 0%; }
}
.progress-bar-fill {
    animation-name: shrink;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}
</style>
