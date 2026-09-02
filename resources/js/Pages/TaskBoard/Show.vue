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
import TaskModal from '@/Components/Modals/TaskModal.vue';
import AssignSectionModal from '@/Components/Modals/AssignSectionModal.vue';
import RequirementsModal from '@/Components/Modals/RequirementsModal.vue';
import FilePreviewModal from '@/Components/Modals/FilePreviewModal.vue';
import CollaboratorPickerModal from '@/Components/Modals/CollaboratorPickerModal.vue';

const props = defineProps({
    taskBoard: Object,
    project: Object,
    workflows: Array,
    teamMembers: Array,
    canManageTasks: Boolean,
    canDeleteTaskBoard: Boolean,
    canDeleteProject: Boolean,
    canUpdateTaskBoard: Boolean,
    canUpdateProject: Boolean,
    sections: Array,
    memberRoles: Array,
    projectAttachments: Array,
});

const board = computed(() => props.taskBoard || props.project);

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

const deleteTaskBoard = () => {
    triggerConfirm(
        'Delete Task Board',
        `Are you sure you want to delete the task board "${board.value.name}"? This action is permanent and will delete all tasks and workflow associations.`,
        () => {
            router.delete(route('task-boards.destroy', board.value.id));
        }
    );
};
const deleteProject = deleteTaskBoard;

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
    sectionForm.put(route('task-boards.update', board.value.id), {
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
                        Delete Task Board
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
                            <h4 class="font-bold text-slate-700 text-sm">Task Board Deliverables & Attachments</h4>
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
        <TaskModal
            :show="isModalOpen"
            :mode="modalMode"
            :form="form"
            :selected-task="selectedTask"
            :team-members="teamMembers"
            :current-member-id="currentMemberId"
            :can-manage-tasks="canManageTasks"
            :attachment-forms="attachmentForms"
            :comment-inputs="commentInputs"
            :collapsed-subtasks="collapsedSubtasks"
            :project-start-date="project?.start_date"
            :workflows="ganttWorkflows"
            :can-delete-task="canManageTasks"
            @close="closeModal"
            @switch-mode="(newMode) => modalMode = newMode"
            @submit="submitForm"
            @add-subtask="addSubtask"
            @remove-subtask="removeSubtask"
            @toggle-subtask-collapse="toggleSubtaskCollapse"
            @open-requirements="openRequirementsModal"
            @open-preview="openPreviewModal"
            @submit-attachment="submitAttachment"
            @submit-comment="submitComment"
            @delete-task="deleteTask"
        />

        <!-- Update Section Modal -->
        <AssignSectionModal
            :show="isSectionModalOpen"
            :form="sectionForm"
            :sections="sections"
            :available-members="allAvailableMembers"
            :member-roles="memberRoles"
            :selected-members="selectedModalMembers"
            :selected-member-roles="selectedModalMemberRoles"
            :collaborators="selectedModalCollaborators"
            @close="closeSectionModal"
            @submit="submitSectionForm"
            @open-collaborator-picker="openModalCollaboratorModal"
            @remove-collaborator="removeModalCollaborator"
            @toggle-member="toggleModalMemberSelection"
            @update-member-role="updateModalMemberProjectRole"
        />

        <!-- Requirements Modal -->
        <RequirementsModal
            :show="isRequirementsModalOpen"
            :subtask-index="activeSubtaskIndex"
            :form="form"
            @close="closeRequirementsModal"
            @add-requirement="addRequirementRow"
            @remove-requirement="removeRequirementRow"
        />

        <!-- File Preview Modal -->
        <FilePreviewModal
            :show="isPreviewModalOpen"
            :file-path="previewFileName"
            @close="closePreviewModal"
        />

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

        <!-- Collaborator Picker Modal -->
        <CollaboratorPickerModal
            :show="isModalCollaboratorModalOpen"
            :available-members="filteredModalCollaborators"
            :selected-member-id="selectedModalCollaboratorId"
            :selected-role-id="selectedModalCollaboratorRoleId"
            :member-roles="memberRoles"
            :is-attaching="isAttachingModalCollaboratorRole"
            @close="isModalCollaboratorModalOpen = false"
            @select-member="selectModalCollaboratorForAdding"
            @update:selected-role-id="(id) => selectedModalCollaboratorRoleId = id"
            @confirm-add="confirmAddModalCollaborator"
        />
    </AppLayout>
</template>
