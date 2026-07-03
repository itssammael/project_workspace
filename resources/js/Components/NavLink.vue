<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: String,
    active: Boolean,
});

const classes = computed(() => {
    return props.active
        ? 'inline-flex items-center px-3 pt-1 border-b-2 border-[#0D9488] text-sm font-bold leading-5 text-[#0d6e65] bg-[#F0FDFA] rounded-t-lg focus:outline-none focus:border-[#0f766e] transition duration-150 ease-in-out'
        : 'inline-flex items-center px-3 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-slate-500 hover:text-[#0D9488] hover:border-teal-200 hover:bg-teal-50/30 rounded-t-lg focus:outline-none transition duration-150 ease-in-out';
});
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
