import { ref } from 'vue';

const toasts = ref([]);

export function useToast() {
    const addToast = (message, type = 'success', duration = 4000) => {
        const id = Date.now() + Math.random().toString(36).substring(2, 9);
        const toast = {
            id,
            message,
            type, // 'success', 'danger', 'info', 'warning'
            duration,
            visible: true
        };
        toasts.value.push(toast);
        return id;
    };

    const removeToast = (id) => {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    };

    return {
        toasts,
        addToast,
        removeToast
    };
}
