<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';
import ToastCard from './ToastCard.vue';

const { toasts, addToast, removeToast } = useToast();
const page = usePage();

// Watch standard Laravel/Inertia flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        addToast(flash.success, 'success');
    }
    if (flash?.error) {
        addToast(flash.error, 'danger');
    }
}, { deep: true, immediate: true });

// Watch Jetstream flash messages
watch(() => page.props.jetstream?.flash, (jetstreamFlash) => {
    if (jetstreamFlash?.banner) {
        const type = jetstreamFlash.bannerStyle === 'danger' ? 'danger' : 'success';
        addToast(jetstreamFlash.banner, type);
    }
}, { deep: true, immediate: true });

// Watch validation errors to trigger failure alert
watch(() => page.props.errors, (errors) => {
    if (errors && Object.keys(errors).length > 0) {
        addToast('Action failed. Please review the errors on the form.', 'danger');
    }
}, { deep: true });
</script>

<template>
    <div class="fixed top-6 right-6 z-[100] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
        <TransitionGroup
            name="toast"
            tag="div"
            class="flex flex-col gap-3 pointer-events-auto"
        >
            <ToastCard
                v-for="toast in toasts"
                :key="toast.id"
                :toast="toast"
                @close="removeToast(toast.id)"
            />
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-leave-active {
    transition: all 0.3s ease;
    position: absolute;
    right: 0;
    width: 100%;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(50%) scale(0.95);
}
.toast-move {
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
</style>
