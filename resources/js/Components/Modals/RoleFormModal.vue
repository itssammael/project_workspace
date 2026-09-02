<script setup>
defineProps({
    show: Boolean,
    mode: String,
    form: Object,
    memberRoles: Array,
});
defineEmits(['close', 'submit', 'edit-role', 'delete-role', 'cancel-edit']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        Manage Functional Roles
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Create Role Form -->
                    <form @submit.prevent="$emit('submit')" class="space-y-3">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">
                            {{ mode === 'create' ? 'Create New Functional Role' : 'Edit Functional Role' }}
                        </label>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                v-model="form.name" 
                                required 
                                placeholder="Role Name (e.g. QA Engineer)" 
                                class="flex-1 rounded-lg border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488]"
                            />
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm"
                            >
                                {{ mode === 'create' ? 'Add Role' : 'Update' }}
                            </button>
                            <button 
                                v-if="mode === 'edit'"
                                type="button" 
                                @click="$emit('cancel-edit')"
                                class="px-3 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                        </div>
                        <div v-if="form.errors.name" class="text-rose-500 text-xs">{{ form.errors.name }}</div>
                    </form>

                    <!-- Roles List -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Existing Functional Roles</label>
                        <div class="border border-slate-100 rounded-xl divide-y divide-slate-100 max-h-60 overflow-y-auto">
                            <div v-for="role in memberRoles" :key="role.id" class="px-4 py-3 flex items-center justify-between hover:bg-slate-50/50 transition">
                                <span class="text-sm font-medium text-slate-700">{{ role.name }}</span>
                                <div class="flex items-center gap-1.5">
                                    <button 
                                        @click="$emit('edit-role', role)"
                                        class="p-1 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded transition"
                                        title="Edit Role"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button 
                                        @click="$emit('delete-role', role)"
                                        class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded transition"
                                        title="Delete Role"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="$emit('close')" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
