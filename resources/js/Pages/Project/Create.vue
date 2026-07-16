<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import RoleSelectDropdown from '@/Components/RoleSelectDropdown.vue';

const props = defineProps({
    sections: Array,
    workflows: Array,
    memberRoles: Array,
});

const workflowTypes = computed(() => {
    const list = new Set();
    if (props.workflows) {
        props.workflows.forEach(workflow => {
            list.add(workflow.workflow_type || 'General');
        });
    }
    return Array.from(list);
});

const workflowTypesWithoutKanban = computed(() => {
    return workflowTypes.value.filter(t => t !== 'Kanban');
});

const selectedWorkflow = ref('');

const workflowSearchQuery = ref('');
const isWorkflowDropdownOpen = ref(false);

const filteredWorkflows = computed(() => {
    return workflowTypesWithoutKanban.value.filter(wf => 
        wf.toLowerCase().includes(workflowSearchQuery.value.toLowerCase())
    );
});

const selectWorkflow = (wf) => {
    selectedWorkflow.value = wf;
    isWorkflowDropdownOpen.value = false;
    workflowSearchQuery.value = '';
    // Auto-select all workflows of the newly selected workflow type plus Kanban
    const kanbanIds = props.workflows.filter(w => w.workflow_type === 'Kanban').map(w => w.id);
    const otherIds = props.workflows.filter(w => w.workflow_type === wf).map(w => w.id);
    form.workflow_ids = [...kanbanIds, ...otherIds];
};

const selectNoneWorkflow = () => {
    selectedWorkflow.value = '';
    isWorkflowDropdownOpen.value = false;
    workflowSearchQuery.value = '';
    // Select ONLY Kanban workflows
    const kanbanIds = props.workflows.filter(w => w.workflow_type === 'Kanban').map(w => w.id);
    form.workflow_ids = [...kanbanIds];
};

const getInitialWorkflowIds = () => {
    if (!props.workflows) return [];
    const kanbanIds = props.workflows.filter(w => w.workflow_type === 'Kanban').map(w => w.id);
    return [...kanbanIds];
};

const form = useForm({
    name: '',
    description: '',
    status: 'planning',
    section_id: '',
    start_date: '',
    end_date: '',
    workflow_ids: getInitialWorkflowIds(),
    members: [], // list of { id, member_role_id }
});

const groupedWorkflows = computed(() => {
    const groups = {};
    if (props.workflows) {
        // Kanban is always visible and preselected
        const kanbanWfs = props.workflows.filter(w => w.workflow_type === 'Kanban');
        if (kanbanWfs.length > 0) {
            groups['Kanban'] = kanbanWfs;
        }
        
        // Selected workflow (if not Kanban)
        if (selectedWorkflow.value && selectedWorkflow.value !== 'Kanban') {
            const otherWfs = props.workflows.filter(w => w.workflow_type === selectedWorkflow.value);
            if (otherWfs.length > 0) {
                groups[selectedWorkflow.value] = otherWfs;
            }
        }
    }
    return groups;
});

const toggleWorkflow = (workflowId) => {
    const workflow = props.workflows.find(w => w.id === workflowId);
    if (workflow && workflow.workflow_type === 'Kanban') return; // Cannot toggle Kanban
    
    const index = form.workflow_ids.indexOf(workflowId);
    if (index > -1) {
        form.workflow_ids.splice(index, 1);
    } else {
        form.workflow_ids.push(workflowId);
    }
};

const selectAllWorkflows = () => {
    const kanbanIds = props.workflows.filter(w => w.workflow_type === 'Kanban').map(w => w.id);
    const currentWorkflows = props.workflows.filter(w => w.workflow_type === selectedWorkflow.value).map(w => w.id);
    form.workflow_ids = [...new Set([...kanbanIds, ...currentWorkflows])];
};

const deselectAllWorkflows = () => {
    const kanbanIds = props.workflows.filter(w => w.workflow_type === 'Kanban').map(w => w.id);
    form.workflow_ids = [...kanbanIds];
};

const submit = () => {
    form.post(route('projects.store'));
};

const selectedSectionMembers = computed(() => {
    if (!form.section_id) return [];
    const section = props.sections.find(t => t.id === form.section_id);
    if (!section) return [];
    return section.members || [];
});

const isMemberSelected = (memberId) => {
    return form.members.some(m => m.id === memberId);
};

const toggleMemberSelection = (member) => {
    const index = form.members.findIndex(m => m.id === member.id);
    if (index > -1) {
        form.members.splice(index, 1);
    } else {
        const defaultRoleId = member.member_roles && member.member_roles.length > 0 
            ? member.member_roles[0].id 
            : '';
        form.members.push({
            id: member.id,
            member_role_id: defaultRoleId
        });
    }
};

const getMemberProjectRoleId = (memberId) => {
    const found = form.members.find(m => m.id === memberId);
    return found ? found.member_role_id : '';
};

const updateMemberProjectRole = (memberId, roleId) => {
    const found = form.members.find(m => m.id === memberId);
    if (found) {
        found.member_role_id = Number(roleId);
    }
};

// --- Collaborators Logic ---
const isCollaboratorModalOpen = ref(false);
const collaboratorSearchQuery = ref('');
const selectedCollaboratorId = ref('');
const selectedCollaboratorRoleId = ref('');
const isAttachingCollaboratorRole = ref(false);

const allAvailableMembers = computed(() => {
    const list = [];
    const seen = new Set();
    if (props.sections) {
        props.sections.forEach(section => {
            if (section.members) {
                section.members.forEach(member => {
                    if (!seen.has(member.id)) {
                        seen.add(member.id);
                        list.push({
                            ...member,
                            sectionName: section.name
                        });
                    }
                });
            }
        });
    }
    return list;
});

const filteredCollaborators = computed(() => {
    const query = collaboratorSearchQuery.value.toLowerCase().trim();
    return allAvailableMembers.value.filter(member => {
        // Exclude members who belong to the currently selected section
        if (selectedSectionMembers.value.some(sm => sm.id === member.id)) {
            return false;
        }
        // Exclude members already in form.members
        if (form.members.some(m => m.id === member.id)) {
            return false;
        }
        if (!query) return true;
        return member.name.toLowerCase().includes(query) || 
               (member.sectionName && member.sectionName.toLowerCase().includes(query));
    });
});

const selectedCollaborators = computed(() => {
    return form.members.filter(m => !selectedSectionMembers.value.some(sm => sm.id === m.id)).map(m => {
        const found = allAvailableMembers.value.find(sm => sm.id === m.id);
        return found ? { ...found } : null;
    }).filter(Boolean);
});

const hasRoleGlobally = (member, roleId) => {
    if (member && member.member_roles) {
        return member.member_roles.some(r => r.id === roleId);
    }
    return false;
};

const openCollaboratorModal = () => {
    isCollaboratorModalOpen.value = true;
    collaboratorSearchQuery.value = '';
    selectedCollaboratorId.value = '';
    selectedCollaboratorRoleId.value = props.memberRoles && props.memberRoles.length > 0 
        ? props.memberRoles[0].id 
        : '';
};

const selectCollaboratorForAdding = (member) => {
    selectedCollaboratorId.value = member.id;
    const defaultRole = member.member_roles && member.member_roles.length > 0 
        ? member.member_roles[0].id 
        : (props.memberRoles && props.memberRoles.length > 0 ? props.memberRoles[0].id : '');
    selectedCollaboratorRoleId.value = defaultRole;
};

const confirmAddCollaborator = () => {
    if (!selectedCollaboratorId.value || !selectedCollaboratorRoleId.value) return;
    
    const member = allAvailableMembers.value.find(m => m.id === selectedCollaboratorId.value);
    if (!member) return;

    const roleId = Number(selectedCollaboratorRoleId.value);
    
    const addToFormMembers = () => {
        if (!form.members.some(m => m.id === member.id)) {
            form.members.push({
                id: member.id,
                member_role_id: roleId
            });
        }
        isCollaboratorModalOpen.value = false;
        selectedCollaboratorId.value = '';
    };

    if (!hasRoleGlobally(member, roleId)) {
        isAttachingCollaboratorRole.value = true;
        router.post(route('admin.members.attach-role', member.id), {
            member_role_id: roleId
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isAttachingCollaboratorRole.value = false;
                const roleObj = props.memberRoles.find(r => r.id === roleId);
                if (roleObj && member.member_roles) {
                    member.member_roles.push({ id: roleObj.id, name: roleObj.name });
                }
                addToFormMembers();
            },
            onError: () => {
                isAttachingCollaboratorRole.value = false;
            }
        });
    } else {
        addToFormMembers();
    }
};

const removeCollaborator = (memberId) => {
    const index = form.members.findIndex(m => m.id === memberId);
    if (index > -1) {
        form.members.splice(index, 1);
    }
};

watch(() => form.section_id, () => {
    form.members = [];
});
</script>

<template>
    <AppLayout title="Create New Project">
        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#0D9488] mb-1">
                <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                <span>&bull;</span>
                <span class="text-slate-400">Create Project</span>
            </div>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                Create New Board
            </h2>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 bg-slate-50/50 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-base">Board Details</h3>
                        <p class="text-xs text-slate-500 mt-1">Specify high-level board parameters and team bindings.</p>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Board Name</label>
                            <input 
                                type="text" 
                                v-model="form.name" 
                                required 
                                class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                placeholder="e.g. Enterprise CRM Integration"
                            />
                            <div v-if="form.errors.name" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Description</label>
                            <textarea 
                                v-model="form.description" 
                                rows="4" 
                                class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                placeholder="Detail the business context, objectives, and deliverables..."
                            ></textarea>
                            <div v-if="form.errors.description" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.description }}</div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Assign Section</label>
                                <select 
                                    v-model="form.section_id" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                >
                                    <option value="" disabled>Select a section...</option>
                                    <option v-for="section in sections" :key="section.id" :value="section.id">
                                        {{ section.name }} (PM: {{ section.project_manager?.user?.name || 'None' }})
                                    </option>
                                </select>
                                <div v-if="form.errors.section_id" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.section_id }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Initial Status</label>
                                <select 
                                    v-model="form.status" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                >
                                    <option value="planning">Planning</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="on_hold">On Hold</option>
                                </select>
                                <div v-if="form.errors.status" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.status }}</div>
                            </div>
                        </div>

                        <!-- Selected Section Members & Roles Assignment -->
                        <div v-if="form.section_id" class="border-t border-slate-100 pt-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Assign Section Members & Project Roles</label>
                                    <p class="text-xs text-slate-400 mt-0.5">Select members from this section to work on this project, and assign their project-specific functional roles.</p>
                                </div>
                                <button 
                                    type="button" 
                                    @click="openCollaboratorModal" 
                                    class="shrink-0 px-3 py-1.5 text-xs font-bold text-[#0D9488] hover:text-white hover:bg-[#0D9488] bg-white rounded-lg border border-[#0D9488] transition flex items-center gap-1 shadow-sm"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add Collaborator
                                </button>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                <div 
                                    v-for="member in selectedSectionMembers" 
                                    :key="member.id"
                                    class="border border-slate-100 rounded-xl p-4 flex flex-col gap-3 bg-slate-50/20"
                                >
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="checkbox" 
                                            :id="'member-' + member.id"
                                            :checked="isMemberSelected(member.id)"
                                            @change="toggleMemberSelection(member)"
                                            class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                        />
                                        <label :for="'member-' + member.id" class="text-xs font-bold text-slate-800 cursor-pointer select-none">
                                            {{ member.name }}
                                        </label>
                                    </div>
                                    
                                    <div v-if="isMemberSelected(member.id)" class="pl-6 space-y-1">
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Project Role</label>
                                        <RoleSelectDropdown
                                            :member="member"
                                            :all-roles="memberRoles"
                                            :model-value="getMemberProjectRoleId(member.id)"
                                            @update:model-value="updateMemberProjectRole(member.id, $event)"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-if="form.errors.members" class="text-xs text-rose-500 font-semibold mt-2">{{ form.errors.members }}</div>

                            <!-- Collaborators Section -->
                            <div v-if="selectedCollaborators.length > 0" class="mt-6 border-t border-slate-100 pt-6">
                                <div class="mb-4">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Project Collaborators (Other Sections)</label>
                                    <p class="text-xs text-slate-400 mt-0.5">Collaborators from other sections assigned to this project.</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    <div 
                                        v-for="collaborator in selectedCollaborators" 
                                        :key="collaborator.id"
                                        class="border border-teal-100 rounded-xl p-4 flex flex-col gap-3 bg-[#F0FDFA]/40 relative"
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
                                                @click="removeCollaborator(collaborator.id)"
                                                class="absolute top-3 right-3 text-slate-400 hover:text-rose-500 p-1 rounded-lg hover:bg-rose-50 transition"
                                                title="Remove collaborator"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.24 9m4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <div class="space-y-1">
                                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Project Role</label>
                                            <RoleSelectDropdown
                                                :member="collaborator"
                                                :all-roles="memberRoles"
                                                :model-value="getMemberProjectRoleId(collaborator.id)"
                                                @update:model-value="updateMemberProjectRole(collaborator.id, $event)"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Start Date</label>
                                <input 
                                    type="date" 
                                    v-model="form.start_date" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                />
                                <div v-if="form.errors.start_date" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.start_date }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">End Date</label>
                                <input 
                                    type="date" 
                                    v-model="form.end_date" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                />
                                <div v-if="form.errors.end_date" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.end_date }}</div>
                            </div>
                        </div>

                        <!-- Development Workflows Selection -->
                        <div class="border-t border-slate-100 pt-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Choose Workflow</label>
                                    <p class="text-xs text-slate-400 mt-0.5">Select the execution workflows that will construct the project timeline.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button 
                                        type="button" 
                                        @click="selectAllWorkflows" 
                                        class="px-2 py-1 text-[10px] font-bold text-[#0D9488] hover:text-[#0f766e] bg-[#F0FDFA] hover:bg-teal-100 rounded border border-teal-100 transition"
                                    >
                                        Select All
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="deselectAllWorkflows" 
                                        class="px-2 py-1 text-[10px] font-bold text-slate-500 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded border border-slate-200 transition"
                                    >
                                        Clear All
                                    </button>
                                </div>
                            </div>

                            <div v-if="form.errors.workflow_ids" class="text-xs text-rose-500 font-semibold mb-3">{{ form.errors.workflow_ids }}</div>

                            <!-- Search/Select Workflow Dropdown -->
                            <div class="mb-4 relative">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Select Project Workflow</label>
                                <div class="relative w-full md:w-72">
                                    <!-- Trigger Button -->
                                    <button
                                        type="button"
                                        @click="isWorkflowDropdownOpen = !isWorkflowDropdownOpen"
                                        class="w-full flex items-center justify-between gap-2 px-3 py-2 border border-slate-200 rounded-lg shadow-sm bg-white text-left text-sm text-slate-700 focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488] transition hover:bg-slate-50/50"
                                    >
                                        <span class="truncate font-medium">
                                            {{ selectedWorkflow || 'None' }}
                                        </span>
                                        <svg class="w-4 h-4 text-slate-400 shrink-0 transition" :class="{ 'rotate-180': isWorkflowDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>

                                    <!-- Dropdown backdrop -->
                                    <div v-if="isWorkflowDropdownOpen" class="fixed inset-0 z-40" @click="isWorkflowDropdownOpen = false"></div>

                                    <!-- Dropdown menu -->
                                    <div
                                        v-if="isWorkflowDropdownOpen"
                                        class="absolute left-0 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg z-50 p-2 space-y-2 max-h-[280px] overflow-y-auto"
                                    >
                                        <!-- Search input inside dropdown -->
                                        <div class="relative">
                                            <input 
                                                type="text" 
                                                v-model="workflowSearchQuery"
                                                placeholder="Search workflow..."
                                                class="w-full rounded-lg border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] pl-8 py-1.5"
                                                @click.stop
                                            />
                                            <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- Workflow options list -->
                                        <div class="space-y-0.5 max-h-40 overflow-y-auto">
                                            <button
                                                type="button"
                                                @click="selectNoneWorkflow"
                                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition"
                                                :class="!selectedWorkflow ? 'bg-[#E6F4F1] text-[#0D9488]' : 'text-slate-700 hover:bg-slate-50'"
                                            >
                                                None
                                            </button>
                                            <button
                                                v-for="wf in filteredWorkflows"
                                                :key="wf"
                                                type="button"
                                                @click="selectWorkflow(wf)"
                                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition"
                                                :class="selectedWorkflow === wf ? 'bg-[#E6F4F1] text-[#0D9488]' : 'text-slate-700 hover:bg-slate-50'"
                                            >
                                                {{ wf }}
                                            </button>
                                            <div v-if="filteredWorkflows.length === 0 && workflowSearchQuery" class="text-xs text-slate-400 text-center py-2">
                                                No workflows found
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div v-for="(workflowsGroup, groupName) in groupedWorkflows" :key="groupName" class="bg-slate-50/50 border border-slate-100 rounded-xl p-4">
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ groupName }} Workflow</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        <div 
                                            v-for="workflow in workflowsGroup" 
                                            :key="workflow.id"
                                            @click="workflow.workflow_type === 'Kanban' ? null : toggleWorkflow(workflow.id)"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border select-none transition-all',
                                                workflow.workflow_type !== 'Kanban' ? 'cursor-pointer' : 'cursor-not-allowed opacity-80 bg-slate-50/20',
                                                form.workflow_ids.includes(workflow.id) 
                                                    ? 'bg-white border-teal-200 text-teal-900 shadow-sm ring-1 ring-teal-100' 
                                                    : 'bg-white/80 border-slate-150 text-slate-650 hover:bg-slate-50'
                                            ]"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :checked="form.workflow_ids.includes(workflow.id)" 
                                                @click.stop 
                                                @change="toggleWorkflow(workflow.id)"
                                                :disabled="workflow.workflow_type === 'Kanban'"
                                                class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4 disabled:opacity-50 disabled:cursor-not-allowed"
                                            />
                                            <div class="min-w-0">
                                                <p class="font-bold text-xs leading-none text-slate-800">{{ workflow.name }}</p>
                                                <span class="text-[9px] text-slate-400 font-semibold leading-none mt-1.5 block">Order #{{ workflow.order }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                            <Link 
                                :href="route('dashboard')" 
                                class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Cancel
                            </Link>
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm disabled:opacity-50"
                            >
                                Create Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Collaborator Selection Modal -->
        <div v-if="isCollaboratorModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="isCollaboratorModalOpen = false"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100 relative z-10 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="p-6 border-b border-slate-150 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Add Project Collaborator</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Find and assign section members to this project.</p>
                    </div>
                    <button 
                        type="button" 
                        @click="isCollaboratorModalOpen = false" 
                        class="text-slate-400 hover:text-slate-500 p-1 hover:bg-slate-100 rounded-lg transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 flex-1 overflow-y-auto space-y-4">
                    <!-- Search input -->
                    <div class="relative">
                        <input 
                            type="text" 
                            v-model="collaboratorSearchQuery"
                            placeholder="Search by name or section..."
                            class="w-full rounded-lg border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] pl-9 py-2"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div class="space-y-1.5 max-h-56 overflow-y-auto">
                        <button
                            v-for="member in filteredCollaborators"
                            :key="member.id"
                            type="button"
                            @click="selectCollaboratorForAdding(member)"
                            class="w-full flex items-center justify-between p-3 rounded-xl border text-left transition"
                            :class="[
                                selectedCollaboratorId === member.id 
                                    ? 'bg-teal-50/50 border-teal-200 text-teal-900 shadow-sm ring-1 ring-teal-100'
                                    : 'bg-white hover:bg-slate-50 border-slate-100 text-slate-700'
                            ]"
                        >
                            <div class="min-w-0 pr-4">
                                <p class="font-bold text-xs text-slate-800">{{ member.name }}</p>
                                <p class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ member.sectionName }}</p>
                            </div>
                            <div v-if="selectedCollaboratorId === member.id" class="text-[#0D9488] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </button>
                        <div v-if="filteredCollaborators.length === 0" class="text-xs text-slate-400 text-center py-6">
                            No eligible section members found
                        </div>
                    </div>

                    <!-- Role selector (only if a collaborator is selected) -->
                    <div 
                        v-if="selectedCollaboratorId" 
                        class="bg-slate-50/50 border border-slate-100 rounded-xl p-4 space-y-3 mt-4"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">Assign Project Role</span>
                            <span class="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded font-bold">
                                {{ allAvailableMembers.find(m => m.id === selectedCollaboratorId)?.name }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Project Functional Role</label>
                            <select 
                                v-model="selectedCollaboratorRoleId"
                                class="w-full rounded-lg border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]"
                            >
                                <option v-for="role in memberRoles" :key="role.id" :value="role.id">
                                    {{ role.name }} {{ hasRoleGlobally(allAvailableMembers.find(m => m.id === selectedCollaboratorId), role.id) ? '' : '(attach globally)' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-6 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-2">
                    <button 
                        type="button" 
                        @click="isCollaboratorModalOpen = false" 
                        class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-600 rounded-lg hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        @click="confirmAddCollaborator" 
                        :disabled="!selectedCollaboratorId || isAttachingCollaboratorRole"
                        class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                    >
                        <span v-if="isAttachingCollaboratorRole" class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        {{ isAttachingCollaboratorRole ? 'Attaching...' : 'Add to Project' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
