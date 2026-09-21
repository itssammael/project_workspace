import { ref, type Ref } from 'vue';

export type ToastType = 'success' | 'danger' | 'info' | 'warning';

export interface Toast {
    id: string;
    message: string;
    type: ToastType;
    duration: number;
    visible: boolean;
}

export interface UseToastReturn {
    toasts: Ref<Toast[]>;
    addToast: (message: string, type?: ToastType, duration?: number) => string;
    removeToast: (id: string) => void;
}

const toasts: Ref<Toast[]> = ref([]);

export function useToast(): UseToastReturn {
    const addToast = (message: string, type: ToastType = 'success', duration: number = 4000): string => {
        const id = (Date.now() + Math.random().toString(36).substring(2, 9)).toString();
        const toast: Toast = {
            id,
            message,
            type,
            duration,
            visible: true,
        };
        toasts.value.push(toast);
        return id;
    };

    const removeToast = (id: string): void => {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            toasts.value.splice(index, 1);
        }
    };

    return {
        toasts,
        addToast,
        removeToast,
    };
}
