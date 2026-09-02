<script setup>
defineProps({
    show: Boolean,
    mode: String,
    form: Object,
    workflowTypes: Array,
});

defineEmits(['close', 'submit', 'add-row', 'remove-row']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div :class="[mode === 'create' ? 'max-w-2xl' : 'max-w-md', 'bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden w-full z-10 transform transition-all flex flex-col max-h-[90vh]']">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">
                            {{ mode === 'create' ? 'Add Workflows' : 'Edit Workflow' }}
                        </h3>
                        <p v-if="mode === 'create'" class="text-xs text-slate-500">
                            Create one or multiple workflows at once.
                        </p>
                    </div>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 overflow-y-auto space-y-4 flex-1">
                    <!-- CREATE MODE: Multiple Workflow Rows -->
                    <template v-if="mode === 'create'">
                        <div class="space-y-4">
                            <div 
                                v-for="(row, index) in form.workflows" 
                                :key="index"
                                class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3 relative group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-[#0D9488] uppercase tracking-wider">
                                        Workflow #{{ index + 1 }}
                                    </span>
                                    <button 
                                        v-if="form.workflows.length > 1" 
                                        type="button" 
                                        @click="$emit('remove-row', index)"
                                        class="text-slate-400 hover:text-rose-600 p-1 rounded transition"
                                        title="Remove workflow row"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                                    <div class="sm:col-span-5">
                                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Workflow Name</label>
                                        <input 
                                            type="text" 
                                            v-model="row.name" 
                                            required 
                                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                            placeholder="e.g. Design & Prototype" 
                                        />
                                        <div v-if="form.errors[`workflows.${index}.name`]" class="text-rose-500 text-xs mt-1">
                                            {{ form.errors[`workflows.${index}.name`] }}
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Display Order</label>
                                        <input 
                                            type="number" 
                                            v-model="row.order" 
                                            required 
                                            min="0" 
                                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                            placeholder="e.g. 1" 
                                        />
                                        <div v-if="form.errors[`workflows.${index}.order`]" class="text-rose-500 text-xs mt-1">
                                            {{ form.errors[`workflows.${index}.order`] }}
                                        </div>
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Workflow Type</label>
                                        <select v-model="row.workflow_type_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                            <option value="">Uncategorized / General</option>
                                            <option v-for="t in workflowTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                        </select>
                                        <div v-if="form.errors[`workflows.${index}.workflow_type_id`]" class="text-rose-500 text-xs mt-1">
                                            {{ form.errors[`workflows.${index}.workflow_type_id`] }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button 
                            type="button" 
                            @click="$emit('add-row')" 
                            class="w-full py-2.5 px-4 border-2 border-dashed border-slate-200 hover:border-[#0D9488] text-slate-600 hover:text-[#0D9488] text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 bg-slate-50/50 hover:bg-[#F0FDFA]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Another Workflow
                        </button>
                    </template>

                    <!-- EDIT MODE: Single Workflow -->
                    <template v-else>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Workflow Name</label>
                            <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Design & Prototype" />
                            <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Display Order</label>
                            <input type="number" v-model="form.order" required min="0" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. 1" />
                            <div v-if="form.errors.order" class="text-rose-500 text-xs mt-1">{{ form.errors.order }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Workflow Type</label>
                            <select v-model="form.workflow_type_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="">Uncategorized / General</option>
                                <option v-for="t in workflowTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <div v-if="form.errors.workflow_type_id" class="text-rose-500 text-xs mt-1">{{ form.errors.workflow_type_id }}</div>
                        </div>
                    </template>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6 flex-shrink-0">
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
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm flex items-center gap-2"
                        >
                            <span v-if="form.processing" class="inline-block animate-spin rounded-full h-3 w-3 border-b-2 border-white"></span>
                            {{ mode === 'create' ? (form.workflows.length > 1 ? `Save ${form.workflows.length} Workflows` : 'Save Workflow') : 'Save Workflow' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
