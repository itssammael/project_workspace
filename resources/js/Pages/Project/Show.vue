<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KanbanBoard from '@/Components/KanbanBoard.vue';
import GanttChart from '@/Components/GanttChart.vue';
import RoleSelectDropdown from '@/Components/RoleSelectDropdown.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    project: Object,
    workflows: Array,
    teamMembers: Array,
    canManageTasks: Boolean,
    canDeleteProject: Boolean,
    canUpdateProject: Boolean,
    sections: Array,
    memberRoles: Array,
    projectAttachments: Array,
});

const page = usePage();

const showAllTasks = ref(false);

const canToggleAllTasks = computed(() => {
    // Check system role: Admin
    const systemRoleSlug = page.props.auth?.user?.role?.slug;
    const systemRoleName = page.props.auth?.user?.role?.name;
    const isAdmin = systemRoleSlug === 'admin' || systemRoleName?.toLowerCase() === 'administrator';
    
    // Check functional role: Project Manager or Department Head
    const currentMember = props.teamMembers.find(m => m.email === page.props.auth?.user?.email);
    const functionalRole = currentMember ? currentMember.role : null;
    const isPMOrDeptHead = functionalRole === 'Project Manager' || functionalRole === 'Department Head';
    
    return isAdmin || isPMOrDeptHead;
});

const currentMemberId = computed(() => {
    const userEmail = page.props.auth?.user?.email;
    return props.teamMembers.find(m => m.email === userEmail)?.id;
});

const kanbanWorkflows = computed(() => {
    // 1. Hide the board completely if the user has the system role 'Viewer'
    const roleSlug = page.props.auth?.user?.role?.slug;
    const roleName = page.props.auth?.user?.role?.name;
    if (roleSlug === 'viewer' || roleName?.toLowerCase() === 'viewer') {
        return [];
    }

    const memberId = currentMemberId.value;
    const assignedTasks = [];

    if (showAllTasks.value) {
        // Retrieve all tasks from all workflows without any assignee or subtasks filtering
        props.workflows.forEach(w => {
            (w.tasks || []).forEach(t => {
                if (!assignedTasks.some(existing => existing.id === t.id)) {
                    assignedTasks.push({ ...t });
                }
            });
        });
    } else if (memberId) {
        // Get all tasks in the project that have at least one subtask assigned to the current user
        props.workflows.forEach(w => {
            (w.tasks || []).forEach(t => {
                const userSubTasks = (t.sub_tasks || []).filter(st => st.member_id === memberId);
                if (userSubTasks.length > 0) {
                    if (!assignedTasks.some(existing => existing.id === t.id)) {
                        // Clone the task and set its sub_tasks and status based on user's assignment
                        const taskClone = { ...t };
                        taskClone.sub_tasks = userSubTasks;
                        
                        // Recalculate status based on current user's subtasks
                        if (userSubTasks.every(st => st.status === 'completed')) {
                            taskClone.status = 'completed';
                        } else if (userSubTasks.every(st => st.status === 'pending')) {
                            taskClone.status = 'pending';
                        } else if (userSubTasks.every(st => st.status === 'completed' || st.status === 'submitted')) {
                            taskClone.status = 'submitted';
                        } else {
                            taskClone.status = 'in_progress';
                        }
                        
                        assignedTasks.push(taskClone);
                    }
                }
            });
        });
    }

    // 2. Map these assigned tasks into the 4 Kanban columns based on their recalculated status
    const kanbanCols = props.workflows
        .filter(w => {
            const type = typeof w.workflow_type === 'object' && w.workflow_type !== null
                ? w.workflow_type.name
                : w.workflow_type;
            return type === 'Kanban';
        })
        .map(w => {
            const colClone = { ...w };
            
            // Determine which status corresponds to this Kanban column
            const nameLower = w.name.toLowerCase();
            let targetStatus = 'pending';
            if (nameLower === 'to do' || nameLower === 'to-do' || nameLower === 'todo') {
                targetStatus = 'pending';
            } else if (nameLower === 'doing') {
                targetStatus = 'in_progress';
            } else if (nameLower === 'submitted') {
                targetStatus = 'submitted';
            } else if (nameLower === 'completed' || nameLower === 'done') {
                targetStatus = 'completed';
            }
            
            // Filter the assigned tasks that match this column's status
            colClone.tasks = assignedTasks.filter(t => t.status === targetStatus);
            
            return colClone;
        });

    return kanbanCols;
});

const ganttWorkflows = computed(() => {
    return props.workflows.filter(w => {
        const type = typeof w.workflow_type === 'object' && w.workflow_type !== null
            ? w.workflow_type.name
            : w.workflow_type;
        return type !== 'Kanban';
    });
});

// Modal state
const isModalOpen = ref(false);
const modalMode = ref('create'); // 'create', 'edit', 'view'
const selectedTask = ref(null);

const form = useForm({
    id: null,
    name: '',
    details: '',
    workflow_id: '',
    subtasks: [],
    status: 'pending', // Fallback for status_only mode
});

const collapsedSubtasks = ref({});

const openAddTaskModal = (workflowId) => {
    form.reset();
    collapsedSubtasks.value = {};
    form.workflow_id = workflowId;
    form.subtasks = [
        {
            id: null,
            name: '',
            details: '',
            deliverables: '',
            duration: 1,
            member_id: '',
            start_date: props.project.start_date ? props.project.start_date.split('T')[0] : '',
            status: 'pending',
            has_requirements: false,
        }
    ];
    modalMode.value = 'create';
    isModalOpen.value = true;
};

const openEditTaskModal = (task) => {
    selectedTask.value = task;
    collapsedSubtasks.value = {};
    form.id = task.id;
    form.name = task.name;
    form.details = task.details || '';
    form.workflow_id = task.workflow_id;
    
    // Load subtasks
    form.subtasks = task.sub_tasks && task.sub_tasks.length > 0
        ? task.sub_tasks.map(st => ({
            id: st.id,
            name: st.name,
            details: st.details || '',
            deliverables: st.deliverables || '',
            duration: st.duration,
            member_id: st.member_id || '',
            start_date: st.start_date ? st.start_date.split('T')[0] : '',
            status: st.status,
            has_requirements: st.deliverables && (st.deliverables.includes('File Upload: ') || st.deliverables.includes('Commit ID: ') || st.deliverables.includes('Ticket Link: ') || st.deliverables.includes("PM's Approval")),
          }))
        : [
            {
                id: null,
                name: task.name + ' Subtask',
                details: task.details || '',
                deliverables: task.deliverables || '',
                duration: task.duration || 1,
                member_id: task.member_id || '',
                start_date: task.start_date ? task.start_date.split('T')[0] : '',
                status: task.status || 'pending',
                has_requirements: false,
            }
          ];

    // Check permissions
    const currentMemberId = props.teamMembers.find(m => m.email === props.$page?.props?.auth?.user?.email)?.id;
    const mySubTask = task.sub_tasks ? task.sub_tasks.find(st => st.member_id === currentMemberId) : null;
    const isAssignee = !!mySubTask;

    if (props.canManageTasks) {
        modalMode.value = 'edit';
    } else if (isAssignee) {
        modalMode.value = 'status_only';
        form.status = mySubTask.status;
    } else {
        modalMode.value = 'view';
    }
    
    isModalOpen.value = true;
};

const addSubtask = () => {
    form.subtasks.push({
        id: null,
        name: '',
        details: '',
        deliverables: '',
        duration: 1,
        member_id: '',
        start_date: props.project.start_date ? props.project.start_date.split('T')[0] : '',
        status: 'pending',
        has_requirements: false,
    });
    collapsedSubtasks.value[form.subtasks.length - 1] = false; // keep new subtask expanded
};

const removeSubtask = (index) => {
    if (form.subtasks.length > 1) {
        form.subtasks.splice(index, 1);
        // Shift collapse state keys accordingly
        const newCollapsed = {};
        Object.keys(collapsedSubtasks.value).forEach(k => {
            const numKey = Number(k);
            if (numKey < index) {
                newCollapsed[numKey] = collapsedSubtasks.value[numKey];
            } else if (numKey > index) {
                newCollapsed[numKey - 1] = collapsedSubtasks.value[numKey];
            }
        });
        collapsedSubtasks.value = newCollapsed;
    } else {
        alert('At least one subtask is required.');
    }
};

const toggleSubtaskCollapse = (index) => {
    collapsedSubtasks.value[index] = !collapsedSubtasks.value[index];
};

const isSubtaskCollapsed = (index) => {
    return !!collapsedSubtasks.value[index];
};

// Requirements Modal and formatting state
const isRequirementsModalOpen = ref(false);
const activeSubtaskIndex = ref(null);
const modalRequirements = ref([]);

// File Preview Modal state
const isPreviewModalOpen = ref(false);
const previewFileUrl = ref('');
const previewFileName = ref('');

const openPreviewModal = (filename) => {
    previewFileName.value = filename;
    previewFileUrl.value = '/attachments/' + filename;
    isPreviewModalOpen.value = true;
};

const closePreviewModal = () => {
    isPreviewModalOpen.value = false;
    previewFileUrl.value = '';
    previewFileName.value = '';
};
const getFileExtension = (filename) => {
    if (!filename) return 'FILE';
    const parts = filename.split('.');
    if (parts.length < 2) return 'FILE';
    return parts[parts.length - 1].toUpperCase();
};

const parseDeliverables = (str) => {
    if (!str) return [];
    return str.split(', ').map(item => {
        if (item.startsWith('File Upload: ')) {
            return { type: 'File Upload', value: item.replace('File Upload: ', '') };
        } else if (item.startsWith('Commit ID: ')) {
            return { type: 'Commit ID', value: item.replace('Commit ID: ', '') };
        } else if (item.startsWith('Ticket Link: ')) {
            return { type: 'Ticket Link', value: item.replace('Ticket Link: ', '') };
        } else if (item === "PM's Approval") {
            return { type: 'Approval', value: "PM's Approval" };
        } else {
            return { type: 'File Upload', value: item };
        }
    });
};

const isViewableFile = (filename) => {
    if (!filename) return false;
    const nameLower = filename.toLowerCase();
    return nameLower.endsWith('.jpg') || nameLower.endsWith('.jpeg') || nameLower.endsWith('.png') || nameLower.endsWith('.pdf');
};

const openRequirementsModal = (index) => {
    activeSubtaskIndex.value = index;
    const subtask = form.subtasks[index];
    modalRequirements.value = parseDeliverables(subtask.deliverables);
    if (modalRequirements.value.length === 0) {
        modalRequirements.value.push({ type: 'File Upload', value: '' });
    }
    isRequirementsModalOpen.value = true;
};

const closeRequirementsModal = () => {
    isRequirementsModalOpen.value = false;
    activeSubtaskIndex.value = null;
    modalRequirements.value = [];
};

const addRequirementRow = () => {
    modalRequirements.value.push({ type: 'File Upload', value: '' });
};

const removeRequirementRow = (idx) => {
    modalRequirements.value.splice(idx, 1);
};

const onRequirementTypeChange = (row) => {
    if (row.type === 'Approval') {
        row.value = "PM's Approval";
    }else if (row.type === 'Commit ID') {
        row.value = "Attach the Repository Commit ID";
    }else if (row.type === 'Ticket Link') {
        row.value = "Provide the Ticket Link";
    }else {
        row.value = "";
    }
};

const onRequirementsCheckboxChange = (index, isChecked) => {
    const subtask = form.subtasks[index];
    subtask.has_requirements = isChecked;
    if (isChecked) {
        openRequirementsModal(index);
    } else {
        subtask.deliverables = '';
    }
};

const saveRequirements = () => {
    if (activeSubtaskIndex.value === null) return;
    const subtask = form.subtasks[activeSubtaskIndex.value];
    
    // Compile to deliverables string
    subtask.deliverables = modalRequirements.value.map(r => {
        if (r.type === 'Approval') {
            return "PM's Approval";
        }
        if (r.type === 'Commit ID') {
            return 'Commit ID: ' + r.value;
        }
        if (r.type === 'Ticket Link') {
            return 'Ticket Link: ' + r.value;
        }
        return 'File Upload: ' + r.value;
    }).join(', ');
    
    closeRequirementsModal();
};

const submitForm = () => {
    if (modalMode.value === 'create') {
        form.post(route('tasks.store', props.project.id), {
            onSuccess: () => closeModal(),
        });
    } else if (modalMode.value === 'edit') {
        form.put(route('tasks.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else if (modalMode.value === 'status_only') {
        form.put(route('tasks.update', form.id), {
            only: ['status'],
            onSuccess: () => closeModal(),
        });
    }
};

const confirmModalState = ref({
    show: false,
    title: '',
    message: '',
    onConfirm: null,
});

const triggerConfirm = (title, message, callback) => {
    confirmModalState.value = {
        show: true,
        title,
        message,
        onConfirm: () => {
            callback();
            confirmModalState.value.show = false;
        }
    };
};

const deleteTask = () => {
    triggerConfirm(
        'Delete Task',
        'Are you sure you want to delete this task?',
        () => {
            form.delete(route('tasks.destroy', form.id), {
                onSuccess: () => closeModal(),
            });
        }
    );
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    selectedTask.value = null;
};

const attachmentForms = ref({});
const commentInputs = ref({});

watch(() => selectedTask.value, (task) => {
    if (task && task.sub_tasks) {
        task.sub_tasks.forEach(st => {
            if (!attachmentForms.value[st.id]) {
                attachmentForms.value[st.id] = {
                    type: 'File Upload',
                    value: '',
                    file: null
                };
            }
            if (commentInputs.value[st.id] === undefined) {
                commentInputs.value[st.id] = '';
            }
        });
    }
}, { immediate: true });

watch(() => props.workflows, (newWorkflows) => {
    if (selectedTask.value) {
        for (const w of newWorkflows) {
            const found = (w.tasks || []).find(t => t.id === selectedTask.value.id);
            if (found) {
                selectedTask.value = found;
                break;
            }
        }
    }
}, { deep: true });

const handleFileChange = (event, subTaskId) => {
    if (!attachmentForms.value[subTaskId]) {
        attachmentForms.value[subTaskId] = { type: 'File Upload', value: '', file: null };
    }
    attachmentForms.value[subTaskId].file = event.target.files[0];
};

const submitAttachment = (subTaskId) => {
    const formState = attachmentForms.value[subTaskId];
    if (!formState) return;

    const formData = new FormData();
    formData.append('attachment_type', formState.type);
    
    if (formState.type === 'File Upload') {
        if (!formState.file) {
            alert('Please select a file to upload.');
            return;
        }
        formData.append('attachment', formState.file);
    } else {
        if (!formState.value || !formState.value.trim()) {
            alert('Please enter a link or commit ID.');
            return;
        }
        formData.append('attachment', formState.value);
    }

    router.post(route('subtasks.store-attachment', subTaskId), formData, {
        preserveScroll: true,
        onSuccess: () => {
            formState.value = '';
            formState.file = null;
        }
    });
};

const submitComment = (subTaskId) => {
    const text = commentInputs.value[subTaskId];
    if (!text || !text.trim()) return;

    router.post(route('subtasks.store-comment', subTaskId), {
        comment: text
    }, {
        preserveScroll: true,
        onSuccess: () => {
            commentInputs.value[subTaskId] = '';
        }
    });
};

const deleteProject = () => {
    triggerConfirm(
        'Delete Project',
        `Are you sure you want to delete the project "${props.project.name}"? This action is permanent and will delete all tasks and workflow associations.`,
        () => {
            router.delete(route('projects.destroy', props.project.id));
        }
    );
};

const isSectionModalOpen = ref(false);
const sectionForm = useForm({
    section_id: props.project.section_id || '',
    members: [], // list of { id, member_role_id }
});

const modalSectionMembers = computed(() => {
    if (!sectionForm.section_id) return [];
    const section = props.sections.find(t => t.id === sectionForm.section_id);
    if (!section) return [];
    return section.members || [];
});

const isModalMemberSelected = (memberId) => {
    return sectionForm.members.some(m => m.id === memberId);
};

const toggleModalMemberSelection = (member) => {
    const index = sectionForm.members.findIndex(m => m.id === member.id);
    if (index > -1) {
        sectionForm.members.splice(index, 1);
    } else {
        const defaultRoleId = member.member_roles && member.member_roles.length > 0 
            ? member.member_roles[0].id 
            : '';
        sectionForm.members.push({
            id: member.id,
            member_role_id: defaultRoleId
        });
    }
};

const getModalMemberProjectRoleId = (memberId) => {
    const found = sectionForm.members.find(m => m.id === memberId);
    return found ? found.member_role_id : '';
};

const updateModalMemberProjectRole = (memberId, roleId) => {
    const found = sectionForm.members.find(m => m.id === memberId);
    if (found) {
        found.member_role_id = Number(roleId);
    }
};

const openSectionModal = () => {
    sectionForm.section_id = props.project.section_id || '';
    sectionForm.members = [];
    if (props.project.section_id && props.project.members) {
        props.project.members.forEach(m => {
            sectionForm.members.push({
                id: m.id,
                member_role_id: m.pivot.member_role_id
            });
        });
    }
    isSectionModalOpen.value = true;
};

const closeSectionModal = () => {
    isSectionModalOpen.value = false;
};

const submitSectionForm = () => {
    sectionForm.put(route('projects.update', props.project.id), {
        onSuccess: () => {
            closeSectionModal();
        }
    });
};

watch(() => sectionForm.section_id, (newSectionId) => {
    if (newSectionId !== props.project.section_id) {
        sectionForm.members = [];
    } else {
        sectionForm.members = [];
        if (props.project.members) {
            props.project.members.forEach(m => {
                sectionForm.members.push({
                    id: m.id,
                    member_role_id: m.pivot.member_role_id
                });
            });
        }
    }
});

// --- Modal Collaborators Logic ---
const isModalCollaboratorModalOpen = ref(false);
const modalCollaboratorSearchQuery = ref('');
const selectedModalCollaboratorId = ref('');
const selectedModalCollaboratorRoleId = ref('');
const isAttachingModalCollaboratorRole = ref(false);

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

const filteredModalCollaborators = computed(() => {
    const query = modalCollaboratorSearchQuery.value.toLowerCase().trim();
    return allAvailableMembers.value.filter(member => {
        // Exclude members who belong to the currently selected section
        if (modalSectionMembers.value.some(sm => sm.id === member.id)) {
            return false;
        }
        // Exclude members already in sectionForm.members
        if (sectionForm.members.some(m => m.id === member.id)) {
            return false;
        }
        if (!query) return true;
        return member.name.toLowerCase().includes(query) || 
               (member.sectionName && member.sectionName.toLowerCase().includes(query));
    });
});

const selectedModalCollaborators = computed(() => {
    return sectionForm.members.filter(m => !modalSectionMembers.value.some(sm => sm.id === m.id)).map(m => {
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

const openModalCollaboratorModal = () => {
    isModalCollaboratorModalOpen.value = true;
    modalCollaboratorSearchQuery.value = '';
    selectedModalCollaboratorId.value = '';
    selectedModalCollaboratorRoleId.value = props.memberRoles && props.memberRoles.length > 0 
        ? props.memberRoles[0].id 
        : '';
};

const selectModalCollaboratorForAdding = (member) => {
    selectedModalCollaboratorId.value = member.id;
    const defaultRole = member.member_roles && member.member_roles.length > 0 
        ? member.member_roles[0].id 
        : (props.memberRoles && props.memberRoles.length > 0 ? props.memberRoles[0].id : '');
    selectedModalCollaboratorRoleId.value = defaultRole;
};

const confirmAddModalCollaborator = () => {
    if (!selectedModalCollaboratorId.value || !selectedModalCollaboratorRoleId.value) return;
    
    const member = allAvailableMembers.value.find(m => m.id === selectedModalCollaboratorId.value);
    if (!member) return;

    const roleId = Number(selectedModalCollaboratorRoleId.value);
    
    const addToFormMembers = () => {
        if (!sectionForm.members.some(m => m.id === member.id)) {
            sectionForm.members.push({
                id: member.id,
                member_role_id: roleId
            });
        }
        isModalCollaboratorModalOpen.value = false;
        selectedModalCollaboratorId.value = '';
    };

    if (!hasRoleGlobally(member, roleId)) {
        isAttachingModalCollaboratorRole.value = true;
        router.post(route('admin.members.attach-role', member.id), {
            member_role_id: roleId
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                isAttachingModalCollaboratorRole.value = false;
                const roleObj = props.memberRoles.find(r => r.id === roleId);
                if (roleObj && member.member_roles) {
                    member.member_roles.push({ id: roleObj.id, name: roleObj.name });
                }
                addToFormMembers();
            },
            onError: () => {
                isAttachingModalCollaboratorRole.value = false;
            }
        });
    } else {
        addToFormMembers();
    }
};

const removeModalCollaborator = (memberId) => {
    const index = sectionForm.members.findIndex(m => m.id === memberId);
    if (index > -1) {
        sectionForm.members.splice(index, 1);
    }
};
</script>

<template>
    <AppLayout :title="project.name">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-xs font-semibold text-[#0D9488]">
                        <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                        <span>&bull;</span>
                        <span class="text-slate-400">Task Boards</span>
                    </div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        {{ project.name }}
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1.5 text-xs font-bold uppercase rounded-xl border bg-[#F0FDFA] text-[#0D9488] border-teal-200">
                        Section: {{ project.section?.name || 'Unassigned' }}
                    </span>
                    <button 
                        v-if="canUpdateProject"
                        @click="openSectionModal"
                        class="px-3 py-1.5 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                        title="Update Team"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.003 9.003 0 0 0-12 0m12 0a9 9 0 0 0-3-2.24M18 18.72V17a4.907 4.907 0 0 0-1.815-3.815m1.815 5.535A9.003 9.003 0 0 0 20 17a9.003 9.003 0 0 0-3-2.24m0 0A9.003 9.003 0 0 0 12 10.75A9.003 9.003 0 0 0 7 14.76m5-3.01v-1.5a3 3 0 1 1 6 0v1.5m-6 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-7 7.72V17a4.907 4.907 0 0 1 1.815-3.815M1.815 18.72A9.003 9.003 0 0 1 4 17a9.003 9.003 0 0 1 3-2.24" />
                        </svg>
                        Update Team
                    </button>
                    <button 
                        v-if="canDeleteProject"
                        @click="deleteProject"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete Project
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-full mx-auto px-8 sm:px-6 lg:px-16 space-y-8 ">
                <!-- Project Details Summary -->
                <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between gap-8">
                    <div class="space-y-4 max-w-3xl flex-1">
                        <h3 class="font-bold text-slate-800 text-lg">Task Board Summary</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">{{ project.description || 'No description provided.' }}</p>
                        
                        <!-- Project Deliverables & Attachments list -->
                        <div v-if="projectAttachments && projectAttachments.length > 0" class="border-t border-slate-100 pt-4 mt-4 space-y-3">
                            <h4 class="font-bold text-slate-700 text-sm">Project Deliverables & Attachments</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[250px] overflow-y-auto pr-1">
                                <div v-for="att in projectAttachments" :key="att.id" class="flex items-center justify-between border border-slate-100 rounded-xl p-3 bg-slate-50/50 hover:bg-slate-50 transition">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-white" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-55' : att.attachment_type === 'Commit ID' ? 'text-indigo-700 border-indigo-200 bg-indigo-55' : 'text-blue-700 border-blue-200 bg-blue-55'">
                                                {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                            </span>
                                            <span class="text-xs font-bold text-slate-700 truncate" :title="att.attachment">{{ att.attachment }}</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-1 truncate">
                                            Subtask: {{ att.sub_task_name }} &bull; Task: {{ att.task_name }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 ml-2 flex items-center gap-1.5">
                                        <button 
                                            v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                            type="button"
                                            @click="openPreviewModal(att.attachment)" 
                                            class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-bold rounded-lg transition shadow-sm inline-block"
                                        >
                                            View
                                        </button>
                                        <a 
                                            v-if="att.attachment_type === 'File Upload'" 
                                            :href="'/attachments/' + att.attachment" 
                                            download 
                                            class="px-2.5 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-[10px] font-bold rounded-lg transition shadow-sm inline-block"
                                        >
                                            Download
                                        </a>
                                        <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded-lg transition shadow-sm inline-block">
                                            Go to Link
                                        </a>
                                        <span v-else class="text-slate-405 text-xs italic">Text Attachment</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-lg">Task Board Team</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" v-if="project.members && project.members.length">
                        <div v-for="member in project.members" :key="member.id" class="border border-slate-100 rounded-xl p-4 flex items-center gap-3 hover:bg-slate-50 transition">
                            <div class="h-10 w-10 rounded-full bg-[#F0FDFA] border border-teal-100 text-[#0D9488] flex items-center justify-center font-bold text-sm">
                                {{ member.user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-bold text-slate-800 text-sm truncate">{{ member.user.name }}</h4>
                                <p class="text-xs text-slate-400 truncate">{{ member.project_role || 'Developer' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-slate-400 text-sm">
                        No members assigned to this project yet.
                    </div>
                </div>
                    <div class="shrink-0 bg-slate-50 border border-slate-100 rounded-xl p-5 w-full md:w-64 space-y-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider">Status:</span>
                            <span class="font-bold text-slate-700 capitalize">{{ project.status }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider">Starts:</span>
                            <span class="font-bold text-slate-700">{{ project.start_date ? new Date(project.start_date).toLocaleDateString() : 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider">Ends:</span>
                            <span class="font-bold text-slate-700">{{ project.end_date ? new Date(project.end_date).toLocaleDateString() : 'N/A' }}</span>
                        </div>
                        <div class="border-t border-slate-200/60 pt-3 flex justify-between" v-if="project.section?.project_manager">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider">Manager:</span>
                            <span class="font-bold text-[#0D9488]">{{ project.section.project_manager.user.name }}</span>
                        </div>
                        <div class="border-t border-slate-200/60 pt-3 space-y-1.5" v-if="project.progress !== undefined">
                            <div class="flex justify-between font-semibold text-slate-500">
                                <span class="text-slate-400 uppercase tracking-wider">Progress:</span>
                                <span>{{ project.progress }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-1.5">
                                <div class="bg-[#0D9488] h-1.5 rounded-full transition-all duration-500" :style="`width: ${project.progress}%`"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-400">
                                <span>{{ project.completed_tasks }} / {{ project.total_tasks }} tasks</span>
                            </div>
                        </div>
                    </div>
                </div>
               
                <!-- Kanban Board Component -->
                <KanbanBoard 
                    v-if="kanbanWorkflows.length > 0"
                    :project="project" 
                    :workflows="kanbanWorkflows" 
                    :can-manage-tasks="canManageTasks"
                    :show-all-tasks="showAllTasks"
                    :can-toggle-all-tasks="canToggleAllTasks"
                    :current-member-id="currentMemberId"
                    @toggle-all-tasks="showAllTasks = !showAllTasks"
                    @add-task="openAddTaskModal"
                    @edit-task="openEditTaskModal"
                    class="mb-8"
                />
               
                <!-- Gantt Chart Component -->
                <GanttChart 
                    v-if="ganttWorkflows.length > 0"
                    :project="project" 
                    :workflows="ganttWorkflows" 
                    :can-manage-tasks="canManageTasks"
                    @add-task="openAddTaskModal"
                    @edit-task="openEditTaskModal"
                />
               
              
            </div>
        </div>

        <!-- Task Creation/Edit Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeModal"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-10 transform transition-all flex flex-col" :class="modalMode === 'view' || modalMode === 'status_only' ? 'max-w-lg w-full' : 'max-w-4xl w-full'">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ modalMode === 'create' ? 'Add New Task' : modalMode === 'edit' ? 'Edit Task Details' : modalMode === 'status_only' ? 'Update Task Status' : 'Task Details' }}
                    </h3>
                    
                    <div class="flex items-center gap-3">
                        <button 
                            v-if="canManageTasks && modalMode !== 'create'" 
                            type="button" 
                            @click="modalMode = modalMode === 'edit' ? 'view' : 'edit'"
                            class="px-3 py-1.5 border border-slate-205 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm"
                        >
                            {{ modalMode === 'edit' ? 'Switch to View Details' : 'Switch to Edit Task' }}
                        </button>
                        
                        <button type="button" @click="closeModal" class="text-slate-400 hover:text-slate-600 flex items-center justify-center p-1 rounded-lg hover:bg-slate-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <!-- Read-Only View -->
                    <div v-if="modalMode === 'view'" class="space-y-4 text-sm text-slate-600">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Task Name</span>
                            <span class="text-slate-800 font-bold text-base mt-1 block">{{ selectedTask?.name }}</span>
                        </div>
                        <div v-if="selectedTask?.details">
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Details</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ selectedTask?.details }}</p>
                        </div>
                        
                        <div class="border-t border-slate-100 pt-4">
                            <h4 class="font-bold text-slate-800 text-sm mb-3">Subtasks</h4>
                            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                <div v-for="st in selectedTask?.sub_tasks" :key="st.id" class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                                    <div class="flex justify-between items-start gap-2">
                                        <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                            {{ st.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 text-[11px] text-slate-400">
                                        <div><strong>Duration:</strong> {{ st.duration }} Days</div>
                                        <div><strong>Start:</strong> {{ st.start_date }}</div>
                                        <div><strong>Assignee:</strong> {{ st.member?.user?.name || st.member?.name || 'Unassigned' }}</div>
                                    </div>
                                    <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                        <strong>Deliverables:</strong> {{ st.deliverables }}
                                    </div>

                                    <!-- Deliverables Attachments & Comments Section -->
                                    <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                        <!-- Attachments -->
                                        <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                            <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                            <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                                <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                            {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                        </span>
                                                        <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                    </div>
                                                    <div class="shrink-0">
                                                        <button 
                                                            v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                            type="button"
                                                            @click="openPreviewModal(att.attachment)" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                        >
                                                            View File
                                                        </button>
                                                        <a 
                                                            v-else-if="att.attachment_type === 'File Upload'" 
                                                            :href="'/attachments/' + att.attachment" 
                                                            target="_blank" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                        >
                                                            View File
                                                        </a>
                                                        <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                            Open Link
                                                        </a>
                                                        <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                        </div>

                                        <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                        <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                    <option value="File Upload">File Upload</option>
                                                    <option value="Commit ID">Commit ID</option>
                                                    <option value="Ticket Link">Ticket Link</option>
                                                </select>
                                                
                                                <div class="flex-1">
                                                    <input 
                                                        v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                        type="file" 
                                                        @change="handleFileChange($event, st.id)" 
                                                        class="block w-full text-xs text-slate-550 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                    />
                                                    <input 
                                                        v-else 
                                                        type="text" 
                                                        v-model="attachmentForms[st.id].value" 
                                                        class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                        placeholder="Enter link, commit ID or text..."
                                                    />
                                                </div>
                                                
                                                <button type="button" @click="submitAttachment(st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                    Attach
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Comments Section -->
                                        <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                            <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                            
                                            <!-- Comments list -->
                                            <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                                <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                    <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                        <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                        <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                    </div>
                                                    <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                            
                                            <!-- Comment input form -->
                                            <div class="flex gap-2 mt-2">
                                                <input 
                                                    type="text" 
                                                    v-model="commentInputs[st.id]" 
                                                    class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Write a comment..."
                                                    @keyup.enter="submitComment(st.id)"
                                                />
                                                <button type="button" @click="submitComment(st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                    Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Only Form -->
                    <div v-else-if="modalMode === 'status_only'" class="space-y-4">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Task Name</span>
                            <span class="text-slate-800 font-bold block">{{ selectedTask?.name }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Update Status</label>
                            <select v-model="form.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="submitted">Submitted</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <!-- List of assignee's subtasks with attachments & comments -->
                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <h4 class="font-bold text-slate-800 text-sm mb-3">Your Subtasks & Comments</h4>
                            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                <div 
                                    v-for="st in selectedTask?.sub_tasks?.filter(s => s.member_id === currentMemberId)" 
                                    :key="st.id" 
                                    class="border border-slate-100 rounded-xl p-4 bg-slate-50/50"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                            {{ st.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                    <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                        <strong>Deliverables:</strong> {{ st.deliverables }}
                                    </div>
                                    
                                    <!-- Deliverables Attachments & Comments Section -->
                                    <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                        <!-- Attachments -->
                                        <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                            <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                            <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                                <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                            {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                        </span>
                                                        <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                    </div>
                                                    <div class="shrink-0">
                                                        <button 
                                                            v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                            type="button"
                                                            @click="openPreviewModal(att.attachment)" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                        >
                                                            View File
                                                        </button>
                                                        <a 
                                                            v-else-if="att.attachment_type === 'File Upload'" 
                                                            :href="'/attachments/' + att.attachment" 
                                                            target="_blank" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                        >
                                                            View File
                                                        </a>
                                                        <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                            Open Link
                                                        </a>
                                                        <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                        </div>

                                        <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                        <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                    <option value="File Upload">File Upload</option>
                                                    <option value="Commit ID">Commit ID</option>
                                                    <option value="Ticket Link">Ticket Link</option>
                                                </select>
                                                
                                                <div class="flex-1">
                                                    <input 
                                                        v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                        type="file" 
                                                        @change="handleFileChange($event, st.id)" 
                                                        class="block w-full text-xs text-slate-555 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                    />
                                                    <input 
                                                        v-else 
                                                        type="text" 
                                                        v-model="attachmentForms[st.id].value" 
                                                        class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                        placeholder="Enter link, commit ID or text..."
                                                    />
                                                </div>
                                                
                                                <button type="button" @click="submitAttachment(st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                    Attach
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Comments Section -->
                                        <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                            <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                            
                                            <!-- Comments list -->
                                            <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                                <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                    <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                        <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                        <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                    </div>
                                                    <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                            
                                            <!-- Comment input form -->
                                            <div class="flex gap-2 mt-2">
                                                <input 
                                                    type="text" 
                                                    v-model="commentInputs[st.id]" 
                                                    class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Write a comment..."
                                                    @keyup.enter="submitComment(st.id)"
                                                />
                                                <button type="button" @click="submitComment(st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                    Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Create/Edit Form -->
                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Left Column: Task Group Details -->
                            <div class="md:col-span-1 space-y-4">
                                <h4 class="font-bold text-slate-800 text-sm border-b pb-2">Task Details</h4>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Task Name</label>
                                    <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Implement Checkout Flow" />
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Details / Description</label>
                                    <textarea v-model="form.details" rows="5" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="Detail the task scope..."></textarea>
                                </div>
                            </div>

                            <!-- Right Column: Subtasks List -->
                            <div class="md:col-span-2 space-y-4">
                                <div class="flex justify-between items-center border-b pb-2">
                                    <h4 class="font-bold text-slate-800 text-sm">Subtasks Setup</h4>
                                    <button type="button" @click="addSubtask" class="inline-flex items-center gap-1.5 text-[#0D9488] hover:text-[#0f766e] text-xs font-bold transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        Add Subtask
                                    </button>
                                </div>

                                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                                    <div 
                                        v-for="(subtask, index) in form.subtasks" 
                                        :key="index" 
                                        class="border border-slate-100 rounded-xl overflow-hidden bg-white shadow-sm transition-all"
                                    >
                                        <!-- Subtask Header (Togglable) -->
                                        <div 
                                            @click="toggleSubtaskCollapse(index)"
                                            class="flex justify-between items-center p-3 bg-slate-50/50 hover:bg-slate-50 cursor-pointer select-none transition border-b border-transparent"
                                            :class="{ 'border-slate-100': !isSubtaskCollapsed(index) }"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <!-- Collapse Arrow -->
                                                <svg 
                                                    class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
                                                    :class="{ 'rotate-180': !isSubtaskCollapsed(index) }"
                                                    fill="none" 
                                                    viewBox="0 0 24 24" 
                                                    stroke-width="2.5" 
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0">Subtask #{{ index + 1 }}</span>
                                                <span 
                                                    class="text-xs font-semibold text-slate-800 truncate"
                                                    v-if="isSubtaskCollapsed(index)"
                                                >
                                                    &mdash; {{ subtask.name || 'Untitled Subtask' }}
                                                </span>
                                            </div>

                                            <!-- Remove Action -->
                                            <button 
                                                type="button" 
                                                v-if="form.subtasks.length > 1" 
                                                @click.stop="removeSubtask(index)" 
                                                class="flex items-center justify-center p-1 rounded hover:bg-rose-50 text-rose-500 hover:text-rose-700 transition shrink-0"
                                                title="Delete Subtask"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Subtask Body Fields -->
                                        <div v-show="!isSubtaskCollapsed(index)" class="p-4 space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subtask Name</label>
                                                    <input type="text" v-model="subtask.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" placeholder="e.g. Write integration tests" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Key Deliverables</label>
                                                    <input type="text" v-model="subtask.deliverables" :disabled="subtask.has_requirements" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs bg-slate-50 disabled:opacity-80 disabled:cursor-not-allowed" placeholder="e.g. Unit tests, test suites" />
                                                    
                                                    <!-- Requirements checkbox -->
                                                    <div class="flex items-center gap-2 mt-1.5">
                                                        <input 
                                                            type="checkbox" 
                                                            :id="'req-check-' + index"
                                                            v-model="subtask.has_requirements"
                                                            @change="onRequirementsCheckboxChange(index, $event.target.checked)"
                                                            class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-3.5 w-3.5"
                                                        />
                                                        <label :for="'req-check-' + index" class="text-[11px] font-semibold text-slate-600 cursor-pointer select-none">
                                                            Add Requiremen
                                                        </label>
                                                        
                                                        <button
                                                            v-if="subtask.has_requirements"
                                                            type="button"
                                                            @click="openRequirementsModal(index)"
                                                            class="text-[10px] text-[#0D9488] hover:underline font-bold"
                                                        >
                                                            (Edit Requirements)
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Details</label>
                                                <textarea v-model="subtask.details" rows="2" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" placeholder="Details of this specific subtask..."></textarea>
                                            </div>

                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Duration (d)</label>
                                                    <input type="number" v-model="subtask.duration" min="1" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Start Date</label>
                                                    <input type="date" v-model="subtask.start_date" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Assignee</label>
                                                    <select v-model="subtask.member_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs">
                                                        <option value="">Unassigned</option>
                                                        <option v-for="member in teamMembers" :key="member.id" :value="member.id">
                                                            {{ member.name }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                                                    <select v-model="subtask.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs">
                                                        <option value="pending">Pending</option>
                                                        <option value="in_progress">In Progress</option>
                                                        <option value="submitted">Submitted</option>
                                                        <option value="completed">Completed</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subtask Deliverables, Attachments & Comments (Edit Mode) -->
                    <div v-if="modalMode === 'edit' && selectedTask?.sub_tasks && selectedTask.sub_tasks.length > 0" class="border-t border-slate-100 pt-4 mt-6">
                        <h4 class="font-bold text-slate-800 text-sm mb-3">Subtasks Deliverables, Attachments & Comments</h4>
                        <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                            <div v-for="st in selectedTask?.sub_tasks" :key="st.id" class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                                <div class="flex justify-between items-start gap-2">
                                    <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                        {{ st.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 text-[11px] text-slate-400">
                                    <div><strong>Duration:</strong> {{ st.duration }} Days</div>
                                    <div><strong>Start:</strong> {{ st.start_date }}</div>
                                    <div><strong>Assignee:</strong> {{ st.member?.name || 'Unassigned' }}</div>
                                </div>
                                <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                    <strong>Deliverables:</strong> {{ st.deliverables }}
                                </div>
                                
                                <!-- Deliverables Attachments & Comments Section -->
                                <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                    <!-- Attachments -->
                                    <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                        <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                        <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                            <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                        {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                    </span>
                                                    <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                </div>
                                                <div class="shrink-0">
                                                    <button 
                                                        v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                        type="button"
                                                        @click="openPreviewModal(att.attachment)" 
                                                        class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                    >
                                                        View File
                                                    </button>
                                                    <a 
                                                        v-else-if="att.attachment_type === 'File Upload'" 
                                                        :href="'/attachments/' + att.attachment" 
                                                        target="_blank" 
                                                        class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                    >
                                                        View File
                                                    </a>
                                                    <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                        Open Link
                                                    </a>
                                                    <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                    </div>

                                    <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                    <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                <option value="File Upload">File Upload</option>
                                                <option value="Commit ID">Commit ID</option>
                                                <option value="Ticket Link">Ticket Link</option>
                                            </select>
                                            
                                            <div class="flex-1">
                                                <input 
                                                    v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                    type="file" 
                                                    @change="handleFileChange($event, st.id)" 
                                                    class="block w-full text-xs text-slate-555 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                />
                                                <input 
                                                    v-else 
                                                    type="text" 
                                                    v-model="attachmentForms[st.id].value" 
                                                    class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Enter link, commit ID or text..."
                                                />
                                            </div>
                                            
                                            <button type="button" @click="submitAttachment(st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                Attach
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Comments Section -->
                                    <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                        <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                        
                                        <!-- Comments list -->
                                        <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                            <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                    <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                    <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                </div>
                                                <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                            </div>
                                        </div>
                                        <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                        
                                        <!-- Comment input form -->
                                        <div class="flex gap-2 mt-2">
                                            <input 
                                                type="text" 
                                                v-model="commentInputs[st.id]" 
                                                class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                placeholder="Write a comment..."
                                                @keyup.enter="submitComment(st.id)"
                                            />
                                            <button type="button" @click="submitComment(st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                Send
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-between items-center pt-4 border-t border-slate-100 mt-6">
                        <div>
                            <button 
                                v-if="modalMode === 'edit'" 
                                type="button" 
                                @click="deleteTask" 
                                class="inline-flex items-center px-4 py-2 border border-rose-200 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition"
                            >
                                Delete Task
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <button 
                                type="button" 
                                @click="closeModal" 
                                class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Close
                            </button>
                            <button 
                                v-if="modalMode !== 'view'" 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Update Section Modal -->
        <div v-if="isSectionModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeSectionModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-visible max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        Assign Section to Project
                    </h3>
                    <button @click="closeSectionModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitSectionForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Select Section</label>
                        <select 
                            v-model="sectionForm.section_id" 
                            required 
                            class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                        >
                            <option value="" disabled>Select a section</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id">
                                {{ section.name }}
                            </option>
                        </select>
                        <div v-if="sectionForm.errors.section_id" class="text-rose-500 text-xs mt-1">{{ sectionForm.errors.section_id }}</div>
                    </div>

                    <!-- Selected Section Members & Roles Assignment -->
                    <div v-if="sectionForm.section_id" class="mt-4 border-t border-slate-100 pt-4 overflow-visible">
                        <div class="flex justify-between items-center mb-2.5">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Assign Section Members & Project Roles</label>
                            <button 
                                type="button" 
                                @click="openModalCollaboratorModal" 
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
                                v-for="member in modalSectionMembers" 
                                :key="member.id"
                                class="border border-slate-100 rounded-lg p-3 bg-slate-50/30 space-y-2"
                            >
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="checkbox" 
                                        :id="'modal-member-' + member.id"
                                        :checked="isModalMemberSelected(member.id)"
                                        @change="toggleModalMemberSelection(member)"
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
                                        @update:model-value="updateModalMemberProjectRole(member.id, $event)"
                                    />
                                </div>
                            </div>
                        </div>
                        <div v-if="sectionForm.errors.members" class="text-rose-500 text-xs mt-1">{{ sectionForm.errors.members }}</div>

                        <!-- Collaborators Section inside modal -->
                        <div v-if="selectedModalCollaborators.length > 0" class="mt-4 border-t border-slate-100 pt-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Project Collaborators (Other Sections)</label>
                            <div class="space-y-3">
                                <div 
                                    v-for="collaborator in selectedModalCollaborators" 
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
                                            @click="removeModalCollaborator(collaborator.id)"
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
                                            @update:model-value="updateModalMemberProjectRole(collaborator.id, $event)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closeSectionModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="sectionForm.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Assign Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Requirements Modal -->
        <div v-if="isRequirementsModalOpen" class="fixed inset-0 overflow-y-auto z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeRequirementsModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-sm">
                        Describe Requirements
                    </h3>
                    <button @click="closeRequirementsModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveRequirements" class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Requirements List</label>
                        <button 
                            type="button" 
                            @click="addRequirementRow" 
                            class="text-xs font-bold text-[#0D9488] hover:text-[#0f766e] flex items-center gap-1"
                        >
                            + Add Requirement
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[250px] overflow-y-auto pr-1">
                        <div v-for="(row, idx) in modalRequirements" :key="idx" class="flex gap-2 items-center">
                            <select 
                                v-model="row.type" 
                                @change="onRequirementTypeChange(row)"
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs w-1/3"
                            >
                                <option value="File Upload">File Upload</option>
                                <option value="Approval">Approval</option>
                                <option value="Commit ID">Commit ID</option>
                                <option value="Ticket Link">Ticket Link</option>
                            </select>

                            <input 
                                type="text" 
                                v-model="row.value" 
                                :disabled="row.type === 'Approval'"
                                :pattern="row.type === 'Commit ID' ? '^[a-fA-F0-9]{40}$' : undefined"
                                :maxlength="row.type === 'Commit ID' ? 40 : undefined"
                                :minlength="row.type === 'Commit ID' ? 40 : undefined"
                                :title="row.type === 'Commit ID' ? 'A 40-character hexadecimal Commit ID is required' : undefined"
                                required
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs flex-1 bg-white disabled:bg-slate-50 disabled:opacity-80"
                                :placeholder="row.type === 'Commit ID' ? 'Enter 40-char hex commit ID...' : (row.type === 'Ticket Link' ? 'Specify ticket link description...' : 'Specify file description...')"
                            />

                            <button 
                                type="button" 
                                @click="removeRequirementRow(idx)"
                                class="text-rose-500 hover:text-rose-700 p-1 transition"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="modalRequirements.length === 0" class="text-slate-400 text-xs text-center py-4">
                            No requirements defined yet. Click "+ Add Requirement".
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closeRequirementsModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- File Preview Modal -->
        <div v-if="isPreviewModalOpen" class="fixed inset-0 overflow-y-auto z-[60] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closePreviewModal"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-10 transform transition-all flex flex-col max-w-4xl w-full">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg truncate max-w-2xl" :title="previewFileName">
                        Preview File: {{ previewFileName }}
                    </h3>
                    <button @click="closePreviewModal" class="text-slate-400 hover:text-slate-600 flex items-center justify-center p-1 rounded-lg hover:bg-slate-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 bg-slate-50/50 flex items-center justify-center min-h-[300px]">
                    <iframe 
                        v-if="previewFileName.toLowerCase().endsWith('.pdf')" 
                        :src="previewFileUrl" 
                        class="w-full h-[70vh] border-0 rounded-xl shadow-sm bg-white"
                    ></iframe>
                    
                    <img 
                        v-else 
                        :src="previewFileUrl" 
                        class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-sm border border-slate-100 bg-white"
                        alt="Image Preview"
                    />
                </div>
                
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end bg-slate-50/50 gap-3">
                    <a :href="previewFileUrl" download class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm">
                        Download
                    </a>
                    <button @click="closePreviewModal" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition bg-white">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <ConfirmationModal :show="confirmModalState.show" @close="confirmModalState.show = false">
            <template #title>
                {{ confirmModalState.title }}
            </template>

            <template #content>
                {{ confirmModalState.message }}
            </template>

            <template #footer>
                <SecondaryButton @click="confirmModalState.show = false">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="confirmModalState.onConfirm"
                >
                    Confirm
                </DangerButton>
            </template>
        </ConfirmationModal>
        <!-- Collaborator Selection Modal inside Assign Section Modal -->
        <div v-if="isModalCollaboratorModalOpen" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="isModalCollaboratorModalOpen = false"></div>

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
                        @click="isModalCollaboratorModalOpen = false" 
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
                            v-model="modalCollaboratorSearchQuery"
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
                            v-for="member in filteredModalCollaborators"
                            :key="member.id"
                            type="button"
                            @click="selectModalCollaboratorForAdding(member)"
                            class="w-full flex items-center justify-between p-2.5 rounded-lg border text-left transition text-xs"
                            :class="[
                                selectedModalCollaboratorId === member.id 
                                    ? 'bg-teal-50/50 border-teal-250 text-teal-905 shadow-sm ring-1 ring-teal-100'
                                    : 'bg-white hover:bg-slate-50 border-slate-100 text-slate-705'
                            ]"
                        >
                            <div class="min-w-0 pr-3">
                                <p class="font-bold text-slate-800">{{ member.name }}</p>
                                <p class="text-[9px] text-slate-400 font-semibold mt-0.5">{{ member.sectionName }}</p>
                            </div>
                            <div v-if="selectedModalCollaboratorId === member.id" class="text-[#0D9488] shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </button>
                        <div v-if="filteredModalCollaborators.length === 0" class="text-[11px] text-slate-400 text-center py-4">
                            No eligible section members found
                        </div>
                    </div>

                    <!-- Role selector (only if a collaborator is selected) -->
                    <div 
                        v-if="selectedModalCollaboratorId" 
                        class="bg-slate-50/50 border border-slate-100 rounded-lg p-3 space-y-2 mt-3"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-700">Assign Project Role</span>
                            <span class="text-[9px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded font-bold">
                                {{ allAvailableMembers.find(m => m.id === selectedModalCollaboratorId)?.name }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Project Functional Role</label>
                            <select 
                                v-model="selectedModalCollaboratorRoleId"
                                class="w-full rounded-lg border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] py-1"
                            >
                                <option v-for="role in memberRoles" :key="role.id" :value="role.id">
                                    {{ role.name }} {{ hasRoleGlobally(allAvailableMembers.find(m => m.id === selectedModalCollaboratorId), role.id) ? '' : '(attach globally)' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-150 bg-slate-50/50 flex justify-end gap-2">
                    <button 
                        type="button" 
                        @click="isModalCollaboratorModalOpen = false" 
                        class="px-3 py-1.5 border border-slate-200 text-xs font-semibold text-slate-600 rounded-lg hover:bg-slate-50 transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        @click="confirmAddModalCollaborator" 
                        :disabled="!selectedModalCollaboratorId || isAttachingModalCollaboratorRole"
                        class="px-3 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                    >
                        <span v-if="isAttachingModalCollaboratorRole" class="inline-block w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        {{ isAttachingModalCollaboratorRole ? 'Attaching...' : 'Add to Project' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
