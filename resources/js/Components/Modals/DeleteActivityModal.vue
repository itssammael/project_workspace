<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    activityName: {
        type: String,
        default: '',
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['close', 'confirm']);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl border border-slate-100 space-y-4">
                <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </div>

                <div class="text-center space-y-1">
                    <h3 class="text-base font-bold text-slate-800">Remove Activity?</h3>
                    <p class="text-xs text-slate-500">
                        Are you sure you want to remove <span class="font-bold text-slate-700">"{{ activityName }}"</span>? This will delete the activity column and its attendance records.
                    </p>
                </div>

                <div class="flex items-center justify-center gap-2 pt-2">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-all cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="$emit('confirm')"
                        :disabled="isSaving"
                        class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-2xs transition-all disabled:opacity-50 cursor-pointer"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
