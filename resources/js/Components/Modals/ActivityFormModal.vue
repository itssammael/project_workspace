<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    editingActivity: {
        type: Object,
        default: null,
    },
    isSaving: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit']);

// Local form state (mirrored from parent's activityForm)
import { ref, watch } from 'vue';

const localName = ref('');
const localDate = ref(new Date().toISOString().substring(0, 10));

watch(() => props.show, (newVal) => {
    if (newVal) {
        localName.value = props.editingActivity?.name || '';
        localDate.value = props.editingActivity?.date || new Date().toISOString().substring(0, 10);
    }
});

const handleSubmit = () => {
    if (!localName.value || !localDate.value) return;
    emit('submit', { name: localName.value, date: localDate.value });
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-100 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-800">
                        {{ editingActivity ? 'Edit LGU Activity' : 'Add New LGU Activity' }}
                    </h3>
                    <button
                        @click="$emit('close')"
                        class="text-slate-400 hover:text-slate-600 rounded-lg p-1 transition-colors cursor-pointer"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="handleSubmit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Activity Name
                        </label>
                        <input
                            v-model="localName"
                            type="text"
                            required
                            placeholder="e.g. TAWO-TAWO CIVIC PARADE"
                            class="w-full text-xs font-bold rounded-xl border-slate-200 shadow-2xs focus:border-[#0D9488] focus:ring-[#0D9488] px-3.5 py-2.5 text-slate-800"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Activity Date
                        </label>
                        <input
                            v-model="localDate"
                            type="date"
                            required
                            class="w-full text-xs font-bold rounded-xl border-slate-200 shadow-2xs focus:border-[#0D9488] focus:ring-[#0D9488] px-3.5 py-2.5 text-slate-800"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="$emit('close')"
                            class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-all cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSaving"
                            class="px-4 py-2 text-xs font-bold text-white bg-[#0D9488] hover:bg-teal-700 rounded-xl shadow-2xs transition-all disabled:opacity-50 cursor-pointer"
                        >
                            {{ editingActivity ? 'Save Changes' : 'Create Activity' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
