<script setup>
import RoleSelectDropdown from '@/Components/RoleSelectDropdown.vue';

const props = defineProps({
    show: Boolean,
    form: Object,
    sections: Array,
    availableMembers: Array,
    memberRoles: Array,
    selectedMembers: Array,
    selectedMemberRoles: Object,
    collaborators: Array
});

const emit = defineEmits([
    'close',
    'submit',
    'open-collaborator-picker',
    'remove-collaborator',
    'toggle-member',
    'update-member-role'
]);

const modalSectionMembers = () => {
    if (!props.form.section_id) return [];
    const section = props.sections.find(t => t.id === props.form.section_id);
    if (!section) return [];
    return section.members || [];
};

const isModalMemberSelected = (memberId) => {
    return props.form.members.some(m => m.id === memberId);
};

const getModalMemberProjectRoleId = (memberId) => {
    const found = props.form.members.find(m => m.id === memberId);
    return found ? found.member_role_id : '';
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-visible max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        Assign Section to Project
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 flex items-center justify-center p-1 rounded-lg hover:bg-slate-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Select Section</label>
                        <select 
                            v-model="form.section_id" 
                            required 
                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                        >
                            <option value="" disabled>Select a section</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id">
                                {{ section.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.section_id" class="text-rose-500 text-xs mt-1">{{ form.errors.section_id }}</div>
                    </div>

                    <!-- Selected Section Members & Roles Assignment -->
                    <div v-if="form.section_id" class="mt-4 border-t border-slate-100 pt-4 overflow-visible">
                        <div class="flex justify-between items-center mb-2.5">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Assign Section Members & Project Roles</label>
                            <button 
                                type="button" 
                                @click="$emit('open-collaborator-picker')" 
                                class="px-2 py-1 text-[10px] font-bold text-[#0D9488] hover:text-white hover:bg-[#0D9488] bg-white rounded border border-[#0D9488] transition flex items-center gap-0.5 shadow-sm"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add Collaborator
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div 
                                v-for="member in modalSectionMembers()" 
                                :key="member.id"
                                class="border border-slate-100 rounded-lg p-3 bg-slate-50/30 space-y-2"
                            >
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="checkbox" 
                                        :id="'modal-member-' + member.id"
                                        :checked="isModalMemberSelected(member.id)"
                                        @change="$emit('toggle-member', member)"
                                        class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                    />
                                    <label :for="'modal-member-' + member.id" class="text-xs font-bold text-slate-800 cursor-pointer select-none">
                                        {{ member.name }}
                                    </label>
                                </div>
                                
                                <div v-if="isModalMemberSelected(member.id)" class="pl-6">
                                    <RoleSelectDropdown
                                        :member="member"
                                        :all-roles="memberRoles"
                                        :model-value="getModalMemberProjectRoleId(member.id)"
                                        @update:model-value="$emit('update-member-role', member.id, $event)"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-if="form.errors.members" class="text-rose-500 text-xs mt-1">{{ form.errors.members }}</div>

                        <!-- Collaborators Section inside modal -->
                        <div v-if="collaborators.length > 0" class="mt-4 border-t border-slate-100 pt-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Project Collaborators (Other Sections)</label>
                            <div class="space-y-3">
                                <div 
                                    v-for="collaborator in collaborators" 
                                    :key="collaborator.id"
                                    class="border border-teal-100 rounded-lg p-3 bg-[#F0FDFA]/40 relative space-y-2"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex flex-col min-w-0 pr-6">
                                            <span class="text-xs font-bold text-slate-800 truncate">
                                                {{ collaborator.name }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-semibold truncate mt-0.5">
                                                {{ collaborator.sectionName }}
                                            </span>
                                        </div>
                                        <button 
                                            type="button"
                                            @click="$emit('remove-collaborator', collaborator.id)"
                                            class="absolute top-2.5 right-2.5 text-slate-400 hover:text-rose-500 p-1 rounded-lg hover:bg-rose-50 transition"
                                            title="Remove collaborator"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.24 9m4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <div class="pl-0">
                                        <RoleSelectDropdown
                                            :member="collaborator"
                                            :all-roles="memberRoles"
                                            :model-value="getModalMemberProjectRoleId(collaborator.id)"
                                            @update:model-value="$emit('update-member-role', collaborator.id, $event)"
                                        />
                                    </div>
                                </div>
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
                            Assign Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
