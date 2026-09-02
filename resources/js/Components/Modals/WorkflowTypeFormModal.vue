<script setup>
defineProps({
    show: Boolean,
    mode: String,
    form: Object,
});
defineEmits(['close', 'submit']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ mode === 'create' ? 'Add Workflow Type' : 'Edit Workflow Type' }}
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type Name</label>
                        <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Software Development" />
                        <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="$emit('close')" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="form.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
