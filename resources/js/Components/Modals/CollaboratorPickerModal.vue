<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: Boolean,
    availableMembers: Array,
    selectedMemberId: [Number, String],
    selectedRoleId: [Number, String],
    memberRoles: Array,
    isAttaching: Boolean
});

const emit = defineEmits([
    'close',
    'select-member',
    'update:selectedRoleId',
    'confirm-add'
]);

const searchQuery = ref('');

const filteredCollaborators = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return props.availableMembers;
    
    return props.availableMembers.filter(member => {
        return member.name.toLowerCase().includes(query) || 
               (member.sectionName && member.sectionName.toLowerCase().includes(query));
    });
});

const hasRoleGlobally = (member, roleId) => {
    if (member && member.member_roles) {
        return member.member_roles.some(r => r.id === roleId);
    }
    return false;
};

const internalSelectedRoleId = computed({
    get: () => props.selectedRoleId,
    set: (val) => emit('update:selectedRoleId', val)
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden border border-slate-100 relative z-10 flex flex-col max-h-[80vh]">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-150 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h4 class="font-bold text-slate-800 text-sm">Add Project Collaborator</h4>
                        <p class="text-[10px] text-slate-500 mt-0.5">Find and assign section members to this project.</p>
                    </div>
                    <button 
                        type="button" 
                        @click="$emit('close')" 
                        class="text-slate-400 hover:text-slate-500 p-1 hover:bg-slate-100 rounded-lg transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 flex-1 overflow-y-auto space-y-4">
                    <!-- Search input -->
                    <div class="relative">
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            placeholder="Search by name or section..."
                            class="w-full rounded-lg border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] pl-8 py-1.5"
                        />
                        <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="space-y-1.5 max-h-48 overflow-y-auto">
                        <button
                            v-for="member in filteredCollaborators"
                            :key="member.id"
                            type="button"
                            @click="$emit('select-member', member)"
                            class="w-full flex items-center justify-between p-2.5 rounded-lg border text-left transition text-xs"
                            :class="[
                                selectedMemberId === member.id 
                                    ? 'bg-teal-50/50 border-teal-250 text-teal-905 shadow-sm ring-1 ring-teal-100'
                                    : 'bg-white hover:bg-slate-50 border-slate-100 text-slate-705'
                            ]"
                        >
                            <div class="min-w-0 pr-3">
                                <p class="font-bold text-slate-800">{{ member.name }}</p>
                                <p class="text-[9px] text-slate-400 font-semibold mt-0.5">{{ member.sectionName }}</p>
                            </div>
                            <div v-if="selectedMemberId === member.id" class="text-[#0D9488] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </button>
                        <div v-if="filteredCollaborators.length === 0" class="text-[11px] text-slate-400 text-center py-4">
                            No eligible section members found
                        </div>
                    </div>

                    <!-- Role selector (only if a collaborator is selected) -->
                    <div 
                        v-if="selectedMemberId" 
                        class="bg-slate-50/50 border border-slate-100 rounded-lg p-3 space-y-2 mt-3"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700">Assign Project Role</span>
                            <span class="text-[9px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded font-bold">
                                {{ availableMembers.find(m => m.id === selectedMemberId)?.name }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Project Functional Role</label>
                            <select 
                                v-model="internalSelectedRoleId"
                                class="w-full rounded-lg border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] py-1"
                            >
                                <option v-for="role in memberRoles" :key="role.id" :value="role.id">
                                    {{ role.name }} {{ hasRoleGlobally(availableMembers.find(m => m.id === selectedMemberId), role.id) ? '' : '(attach globally)' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-2">
                    <button 
                        type="button" 
                        @click="$emit('close')" 
                        class="px-3 py-1.5 border border-slate-200 text-xs font-semibold text-slate-600 rounded-lg hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        @click="$emit('confirm-add')" 
                        :disabled="!selectedMemberId || isAttaching"
                        class="px-3 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                    >
                        <span v-if="isAttaching" class="inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        {{ isAttaching ? 'Attaching...' : 'Add to Project' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
