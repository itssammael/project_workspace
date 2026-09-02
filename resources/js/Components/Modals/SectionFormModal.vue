<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    mode: String,
    form: Object,
    departments: Array,
    membersList: Array,
});

defineEmits(['close', 'submit']);

const isMemberPickerOpen = ref(false);
const memberPickerSearch = ref('');

const assignedSectionMembers = computed(() => {
    return props.membersList.filter(m => props.form.member_ids.includes(m.id));
});

const filteredPickerMembers = computed(() => {
    if (!memberPickerSearch.value) return props.membersList;
    const query = memberPickerSearch.value.toLowerCase();
    return props.membersList.filter(m => 
        m.name.toLowerCase().includes(query) ||
        (m.email && m.email.toLowerCase().includes(query)) ||
        (m.role && m.role.toLowerCase().includes(query))
    );
});

const openMemberPicker = () => {
    memberPickerSearch.value = '';
    isMemberPickerOpen.value = true;
};

const closeMemberPicker = () => {
    isMemberPickerOpen.value = false;
};

const removeMemberAssignment = (memberId) => {
    const index = props.form.member_ids.indexOf(memberId);
    if (index > -1) {
        props.form.member_ids.splice(index, 1);
    }
};

const toggleMemberAssignment = (memberId) => {
    const index = props.form.member_ids.indexOf(memberId);
    if (index > -1) {
        props.form.member_ids.splice(index, 1);
    } else {
        props.form.member_ids.push(memberId);
    }
};

const getInitials = (name) => {
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-lg w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ mode === 'create' ? 'Create New Section' : 'Edit Section' }}
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Department</label>
                        <select v-model="form.department_id" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                            <option value="" disabled>Select Department</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                {{ dept.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.department_id" class="text-rose-500 text-xs mt-1">{{ form.errors.department_id }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Section Name</label>
                        <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Beta Development Section" />
                        <div v-if="form.errors.name" class="text-rose-500 text-xs mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Head</label>
                        <select v-model="form.member_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="m in membersList" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.role }})
                            </option>
                        </select>
                        <div v-if="form.errors.member_id" class="text-rose-500 text-xs mt-1">{{ form.errors.member_id }}</div>
                    </div>

                    <!-- Member Assignment List (Shows ONLY Assigned Members with + Button) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">
                                Section Members ({{ assignedSectionMembers.length }})
                            </label>
                            <button 
                                type="button" 
                                @click="openMemberPicker"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#0D9488] hover:text-[#0f766e] bg-teal-50 hover:bg-teal-100 px-2.5 py-1 rounded-lg border border-teal-200/60 transition shadow-sm"
                                title="Add member to section"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                <span>Add Member</span>
                            </button>
                        </div>

                        <!-- List of Assigned Members Only -->
                        <div class="border border-slate-200 rounded-xl max-h-56 overflow-y-auto p-3 bg-slate-50/50 space-y-2">
                            <div 
                                v-for="m in assignedSectionMembers" 
                                :key="m.id"
                                class="flex items-center justify-between p-2.5 rounded-lg border bg-white border-slate-200/80 shadow-sm transition-all"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="h-8 w-8 rounded-full bg-[#F0FDFA] border border-teal-200 text-[#0D9488] flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ getInitials(m.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-xs truncate leading-none text-slate-800">{{ m.name }}</p>
                                        <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider leading-none mt-1 block">
                                            {{ m.role }}
                                        </span>
                                    </div>
                                </div>

                                <button 
                                    type="button" 
                                    @click="removeMemberAssignment(m.id)"
                                    class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition shrink-0"
                                    title="Remove member from section"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Empty State when no members assigned -->
                            <div v-if="assignedSectionMembers.length === 0" class="text-center py-6 px-4">
                                <svg class="w-8 h-8 mx-auto text-slate-300 mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                </svg>
                                <p class="text-xs font-medium text-slate-500">No members assigned to this section</p>
                                <button 
                                    type="button" 
                                    @click="openMemberPicker"
                                    class="mt-1.5 text-xs font-bold text-[#0D9488] hover:underline inline-flex items-center gap-1"
                                >
                                    <span>Click "+ Add Member" to assign</span>
                                </button>
                            </div>
                        </div>
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
                            Save Section
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Searchable Member Picker Modal -->
        <div v-if="isMemberPickerOpen" class="fixed inset-0 overflow-y-auto z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeMemberPicker"></div>

            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col max-h-[80vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 flex-shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Select Section Members</h3>
                        <p class="text-xs text-slate-500">Search and select members to assign to this section.</p>
                    </div>
                    <button @click="closeMemberPicker" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 border-b border-slate-100 bg-white flex-shrink-0">
                    <input 
                        type="text" 
                        v-model="memberPickerSearch"
                        placeholder="Search by name, email, or role..." 
                        class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                        autofocus
                    />
                </div>

                <div class="p-4 overflow-y-auto flex-1 space-y-2 max-h-[340px]">
                    <div 
                        v-for="m in filteredPickerMembers" 
                        :key="m.id"
                        @click="toggleMemberAssignment(m.id)"
                        :class="[
                            'flex items-center gap-3 p-3 rounded-xl border cursor-pointer select-none transition-all',
                            form.member_ids.includes(m.id) 
                                ? 'bg-[#F0FDFA] border-teal-300 text-teal-900 shadow-sm' 
                                : 'bg-white border-slate-100 text-slate-600 hover:bg-slate-50'
                        ]"
                    >
                        <input 
                            type="checkbox" 
                            :checked="form.member_ids.includes(m.id)" 
                            @click.stop 
                            @change="toggleMemberAssignment(m.id)"
                            class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                        />
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-xs truncate leading-none text-slate-800">{{ m.name }}</p>
                            <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider leading-none mt-1 block">
                                {{ m.role }}
                            </span>
                        </div>
                        <span v-if="form.member_ids.includes(m.id)" class="px-2 py-0.5 text-[9px] font-bold uppercase rounded bg-teal-100 text-teal-800">
                            Assigned ✓
                        </span>
                    </div>

                    <div v-if="filteredPickerMembers.length === 0" class="text-center py-8 text-slate-400">
                        <p class="text-xs font-semibold">No members match your search query.</p>
                    </div>
                </div>

                <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-end flex-shrink-0">
                    <button 
                        type="button" 
                        @click="closeMemberPicker" 
                        class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        Done ({{ form.member_ids.length }} Selected)
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
