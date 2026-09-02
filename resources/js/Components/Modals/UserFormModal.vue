<script setup>
defineProps({
    show: Boolean,
    mode: String,
    form: Object,
    roles: Array,
    memberRoles: Array,
    sections: Array,
    employeeTypes: Array,
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
                        {{ mode === 'create' ? 'Add New User & Member' : 'Edit User Profile' }}
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                        <input type="text" autocomplete="off" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Jane Doe" />
                        <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Username</label>
                        <input type="text" autocomplete="off" v-model="form.username" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. janedoe" />
                        <div v-if="form.errors.username" class="text-rose-500 text-xs mt-1">{{ form.errors.username }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" autocomplete="off" v-model="form.email" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. jane@example.com" />
                        <div v-if="form.errors.email" class="text-rose-500 text-xs mt-1">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                            Password <span v-if="mode === 'edit'" class="text-slate-400 font-normal">(Leave empty to keep current)</span>
                        </label>
                        <input type="password" autocomplete="off" v-model="form.password" :required="mode === 'create'" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="Minimum 8 characters" />
                        <div v-if="form.errors.password" class="text-rose-500 text-xs mt-1">{{ form.errors.password }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">System Role</label>
                            <select v-model="form.role_id" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="">Select Role</option>
                                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                            <div v-if="form.errors.role_id" class="text-rose-500 text-xs mt-1">{{ form.errors.role_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Functional Roles</label>
                            <div class="mt-2 space-y-2 max-h-[120px] overflow-y-auto border border-slate-200 rounded-lg p-2 bg-slate-50/50">
                                <div v-for="mr in memberRoles" :key="mr.id" class="flex items-center">
                                    <input 
                                        type="checkbox" 
                                        :id="'member_role_' + mr.id" 
                                        :value="mr.id" 
                                        v-model="form.member_role_ids" 
                                        class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                    />
                                    <label :for="'member_role_' + mr.id" class="ms-2 text-xs font-medium text-slate-700 select-none cursor-pointer">
                                        {{ mr.name }}
                                    </label>
                                </div>
                            </div>
                            <div v-if="form.errors.member_role_ids" class="text-rose-500 text-xs mt-1">{{ form.errors.member_role_ids }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Employee Type</label>
                        <div class="flex items-center gap-6 py-2.5 px-3 border border-slate-200 rounded-lg bg-slate-50/50">
                            <label 
                                v-for="et in employeeTypes" 
                                :key="et.id" 
                                class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 select-none"
                            >
                                <input 
                                    type="radio" 
                                    name="employee_type" 
                                    :value="et.id" 
                                    v-model="form.employee_type_id" 
                                    class="text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4" 
                                />
                                <span>{{ et.description }}</span>
                            </label>
                        </div>
                        <div v-if="form.errors.employee_type_id" class="text-rose-500 text-xs mt-1">{{ form.errors.employee_type_id }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assign to Section(s)</label>
                        <div class="mt-2 space-y-2 max-h-[120px] overflow-y-auto border border-slate-200 rounded-lg p-2 bg-slate-50/50">
                            <div v-for="sec in sections" :key="sec.id" class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    :id="'user_sec_' + sec.id" 
                                    :value="sec.id" 
                                    v-model="form.section_ids" 
                                    class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                />
                                <label :for="'user_sec_' + sec.id" class="ms-2 text-xs font-medium text-slate-700 select-none cursor-pointer">
                                    {{ sec.name }}
                                </label>
                            </div>
                            <div v-if="sections.length === 0" class="text-xs text-slate-400 italic">
                                No sections available.
                            </div>
                        </div>
                        <div v-if="form.errors.section_ids" class="text-rose-500 text-xs mt-1">{{ form.errors.section_ids }}</div>
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
                            Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
