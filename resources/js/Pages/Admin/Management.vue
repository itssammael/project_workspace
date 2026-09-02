<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import UserFormModal from '@/Components/Modals/UserFormModal.vue';
import SectionFormModal from '@/Components/Modals/SectionFormModal.vue';
import WorkflowFormModal from '@/Components/Modals/WorkflowFormModal.vue';
import WorkflowTypeFormModal from '@/Components/Modals/WorkflowTypeFormModal.vue';
import RoleFormModal from '@/Components/Modals/RoleFormModal.vue';
import DepartmentFormModal from '@/Components/Modals/DepartmentFormModal.vue';

const props = defineProps({
    users: Array,
    sections: Array,
    departments: {
        type: Array,
        default: () => []
    },
    canManageDepartments: {
        type: Boolean,
        default: false
    },
    roles: Array,
    memberRoles: Array,
    membersList: Array,
    workflows: Array,
    workflowTypes: Array,
    employeeTypes: {
        type: Array,
        default: () => [
            { id: 1, description: 'Regular' },
            { id: 2, description: 'Casual' },
            { id: 3, description: 'JOW' }
        ]
    },
    systemLogs: {
        type: Array,
        default: () => []
    }
});

// Active tab
const activeTab = ref('users'); // 'users', 'sections', 'departments', 'workflows', or 'system_logs'

// Search & filter states
const userSearch = ref('');
const roleFilter = ref('');
const sectionSearch = ref('');
const departmentSearch = ref('');
const workflowSearch = ref('');
const systemLogSearch = ref('');

// Modals state
const isUserModalOpen = ref(false);
const userModalMode = ref('create'); // 'create', 'edit'
const isSectionModalOpen = ref(false);
const sectionModalMode = ref('create'); // 'create', 'edit'
const isDepartmentModalOpen = ref(false);
const departmentModalMode = ref('create'); // 'create', 'edit'
const isWorkflowModalOpen = ref(false);
const workflowModalMode = ref('create'); // 'create', 'edit'
const isWorkflowTypeModalOpen = ref(false);
const workflowTypeModalMode = ref('create'); // 'create', 'edit'
const isRoleModalOpen = ref(false);
const roleModalMode = ref('create'); // 'create', 'edit'

// Bulk workflow assignment selection
const selectedWorkflowIds = ref([]);
const bulkWorkflowTypeId = ref('');

// User selection for bulk delete
const selectedUserIds = ref([]);

// Forms
const userForm = useForm({
    id: null,
    name: '',
    username: '',
    email: '',
    password: '',
    role_id: '',
    employee_type_id: 1,
    member_role_ids: [],
    section_ids: [],
});

// Sync email domain when creating a new user by default
watch(() => userForm.username, (newUsername) => {
    if (userModalMode.value === 'create') {
        userForm.email = newUsername ? `${newUsername}@bayawancity.gov.ph` : '';
    }
});

const sectionForm = useForm({
    id: null,
    name: '',
    department_id: '',
    member_id: '', // PM
    member_ids: [], // Assigned members
});

const departmentForm = useForm({
    id: null,
    name: '',
    short_name: '',
    department_head_id: '',
});

const workflowForm = useForm({
    id: null,
    name: '',
    order: 0,
    workflow_type_id: '',
    workflows: [],
});

const workflowTypeForm = useForm({
    id: null,
    name: '',
});

const roleForm = useForm({
    id: null,
    name: '',
});

const bulkDeleteForm = useForm({
    ids: [],
});

// Compute stats reactively
const stats = computed(() => {
    const totalUsers = props.users.length;
    const totalAdmins = props.users.filter(u => u.system_role_slug === 'admin').length;
    const totalPMs = props.users.filter(u => u.member_role && u.member_role.includes('Project Manager')).length;
    const totalDevs = props.users.filter(u => u.member_role && (u.member_role.includes('Developer') || u.member_role.includes('Lead Developer'))).length;
    
    const totalSections = props.sections.length;
    
    // Count unique members assigned to at least one section
    const assignedMemberIds = new Set();
    props.sections.forEach(t => {
        t.members.forEach(m => assignedMemberIds.add(m.id));
    });
    const assignedMembers = assignedMemberIds.size;
    const unassignedMembers = props.membersList.length - assignedMembers;

    return {
        totalUsers,
        totalAdmins,
        totalPMs,
        totalDevs,
        totalSections,
        assignedMembers,
        unassignedMembers
    };
});

const page = usePage();
const availableSections = computed(() => {
    const user = page.props.auth.user;
    if (user.role?.slug === 'admin') {
        return props.sections;
    }
    return user.member?.sections || [];
});

// Filter users
const filteredUsers = computed(() => {
    return props.users.filter(u => {
        const matchesSearch = u.name.toLowerCase().includes(userSearch.value.toLowerCase()) || 
                              u.email.toLowerCase().includes(userSearch.value.toLowerCase()) ||
                              (u.username && u.username.toLowerCase().includes(userSearch.value.toLowerCase()));
        const matchesRole = !roleFilter.value || (u.member_role_ids && u.member_role_ids.includes(Number(roleFilter.value))) || u.role_id == roleFilter.value;
        return matchesSearch && matchesRole;
    });
});

// Pagination state for users
const userPerPage = 6;
const userCurrentPage = ref(1);

const paginatedUsers = computed(() => {
    const start = (userCurrentPage.value - 1) * userPerPage;
    const end = start + userPerPage;
    return filteredUsers.value.slice(start, end);
});

const userTotalPages = computed(() => {
    return Math.ceil(filteredUsers.value.length / userPerPage);
});

// Watch filters/search to reset page
watch([userSearch, roleFilter], () => {
    userCurrentPage.value = 1;
});

// Watch total pages to clamp current page if it becomes out of range (e.g. on deletion/filter)
watch(userTotalPages, (newTotal) => {
    if (userCurrentPage.value > newTotal) {
        userCurrentPage.value = Math.max(1, newTotal);
    }
});

// Filter sections
const filteredSections = computed(() => {
    if (!sectionSearch.value) return props.sections;
    return props.sections.filter(t => 
        t.name.toLowerCase().includes(sectionSearch.value.toLowerCase()) ||
        (t.project_manager && t.project_manager.name.toLowerCase().includes(sectionSearch.value.toLowerCase()))
    );
});

// Filter workflows
const filteredWorkflows = computed(() => {
    return props.workflows.filter(w => {
        const nameMatch = w.name.toLowerCase().includes(workflowSearch.value.toLowerCase());
        const typeSearchMatch = w.workflow_type && w.workflow_type.toLowerCase().includes(workflowSearch.value.toLowerCase());
        return nameMatch || typeSearchMatch;
    });
});

// Group workflows by workflow type
const groupedWorkflows = computed(() => {
    const groups = {};
    filteredWorkflows.value.forEach(w => {
        const typeName = w.workflow_type || 'General/Uncategorized';
        if (!groups[typeName]) {
            groups[typeName] = [];
        }
        groups[typeName].push(w);
    });
    return groups;
});

// Filter system logs
const filteredSystemLogs = computed(() => {
    if (!systemLogSearch.value) return props.systemLogs;
    const query = systemLogSearch.value.toLowerCase();
    return props.systemLogs.filter(log => {
        return (log.action && log.action.toLowerCase().includes(query)) ||
               (log.description && log.description.toLowerCase().includes(query)) ||
               (log.user_name && log.user_name.toLowerCase().includes(query)) ||
               (log.ip_address && log.ip_address.toLowerCase().includes(query)) ||
               (log.created_at && log.created_at.toLowerCase().includes(query));
    });
});

// Pagination state for system logs
const logsPerPage = 10;
const logsCurrentPage = ref(1);

const paginatedSystemLogs = computed(() => {
    const start = (logsCurrentPage.value - 1) * logsPerPage;
    const end = start + logsPerPage;
    return filteredSystemLogs.value.slice(start, end);
});

const logsTotalPages = computed(() => {
    return Math.ceil(filteredSystemLogs.value.length / logsPerPage);
});

// Watch logs search to reset page
watch(systemLogSearch, () => {
    logsCurrentPage.value = 1;
});

const formatDateTime = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleString();
};

const availableSystemRolesForForm = computed(() => {
    if (page.props.auth.user.role?.slug !== 'admin') {
        return props.roles.filter(r => r.slug === 'user');
    }
    return props.roles;
});

const canEditUser = (user) => {
    const loggedInUser = page.props.auth.user;
    if (loggedInUser.role?.slug === 'admin') {
        return true;
    }
    // Admin Staff cannot update details of users with System Role "Administrator" or Functional Role "Department Head"
    const isTargetAdmin = user.system_role_slug === 'admin';
    const isTargetDeptHead = (user.member_role_slugs && user.member_role_slugs.includes('department_head')) ||
                             (user.member_role && user.member_role.toLowerCase().includes('department head'));
    
    return !isTargetAdmin && !isTargetDeptHead;
};

// User Actions
const openAddUserModal = () => {
    userForm.reset();
    userForm.employee_type_id = 1;
    userModalMode.value = 'create';
    if (page.props.auth.user.role?.slug !== 'admin') {
        const userRole = props.roles.find(r => r.slug === 'user');
        if (userRole) {
            userForm.role_id = userRole.id;
        }
    }
    isUserModalOpen.value = true;
};

const openEditUserModal = (user) => {
    if (!canEditUser(user)) {
        return;
    }
    userForm.reset();
    userForm.id = user.id;
    userForm.name = user.name;
    userForm.username = user.username;
    userForm.email = user.email;
    userForm.password = ''; // leave blank by default
    userForm.role_id = user.role_id;
    userForm.employee_type_id = user.employee_type_id || 1;
    userForm.member_role_ids = user.member_role_ids || [];
    userForm.section_ids = user.sections ? user.sections.map(s => s.id) : [];
    userModalMode.value = 'edit';
    isUserModalOpen.value = true;
};

const submitUserForm = () => {
    if (userModalMode.value === 'create') {
        userForm.post(route('admin.users.store'), {
            onSuccess: () => closeUserModal(),
        });
    } else {
        userForm.put(route('admin.users.update', userForm.id), {
            onSuccess: () => closeUserModal(),
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

const deleteUser = (user) => {
    triggerConfirm(
        'Delete User',
        `Are you sure you want to delete ${user.name}? This will also delete their member profile.`,
        () => {
            userForm.delete(route('admin.users.destroy', user.id));
        }
    );
};

const toggleAllUsers = () => {
    const paginatedIds = paginatedUsers.value.map(u => u.id);
    const allSelectedOnPage = paginatedIds.every(id => selectedUserIds.value.includes(id));
    
    if (allSelectedOnPage) {
        // Deselect only the paginated users of the current page
        selectedUserIds.value = selectedUserIds.value.filter(id => !paginatedIds.includes(id));
    } else {
        // Select all paginated users of the current page
        const currentSelection = [...selectedUserIds.value];
        paginatedIds.forEach(id => {
            if (!currentSelection.includes(id)) {
                currentSelection.push(id);
            }
        });
        selectedUserIds.value = currentSelection;
    }
};

const bulkDeleteUsers = () => {
    if (selectedUserIds.value.length === 0) return;
    triggerConfirm(
        'Delete Selected Users',
        `Are you sure you want to delete ${selectedUserIds.value.length} selected users? This will also delete their member profiles.`,
        () => {
            bulkDeleteForm.ids = selectedUserIds.value;
            bulkDeleteForm.post(route('admin.users.bulk-destroy'), {
                onSuccess: () => {
                    selectedUserIds.value = [];
                }
            });
        }
    );
};

// Role Management Actions
const openRoleModal = () => {
    roleForm.reset();
    roleForm.id = null;
    roleModalMode.value = 'create';
    isRoleModalOpen.value = true;
};

const closeRoleModal = () => {
    isRoleModalOpen.value = false;
    roleForm.reset();
    roleForm.id = null;
    roleModalMode.value = 'create';
};

const openEditRoleModal = (role) => {
    roleForm.id = role.id;
    roleForm.name = role.name;
    roleModalMode.value = 'edit';
};

const cancelEditRoleMode = () => {
    roleForm.reset();
    roleForm.id = null;
    roleModalMode.value = 'create';
};

const submitRoleForm = () => {
    if (roleModalMode.value === 'create') {
        roleForm.post(route('admin.member-roles.store'), {
            onSuccess: () => {
                roleForm.reset();
            }
        });
    } else {
        roleForm.put(route('admin.member-roles.update', roleForm.id), {
            onSuccess: () => {
                roleForm.reset();
                roleForm.id = null;
                roleModalMode.value = 'create';
            }
        });
    }
};

const deleteRole = (role) => {
    triggerConfirm(
        'Delete Functional Role',
        `Are you sure you want to delete the functional role "${role.name}"?`,
        () => {
            roleForm.delete(route('admin.member-roles.destroy', role.id));
        }
    );
};

const closeUserModal = () => {
    isUserModalOpen.value = false;
    userForm.reset();
};

// Section Actions
const openAddSectionModal = () => {
    sectionForm.reset();
    if (props.departments && props.departments.length > 0) {
        sectionForm.department_id = props.departments[0].id;
    }
    sectionModalMode.value = 'create';
    isSectionModalOpen.value = true;
};

const openEditSectionModal = (section) => {
    sectionForm.reset();
    sectionForm.id = section.id;
    sectionForm.name = section.name;
    sectionForm.department_id = section.department_id || (props.departments && props.departments.length > 0 ? props.departments[0].id : '');
    sectionForm.member_id = section.member_id || '';
    sectionForm.member_ids = section.members ? section.members.map(m => m.id) : [];
    sectionModalMode.value = 'edit';
    isSectionModalOpen.value = true;
};

const submitSectionForm = () => {
    if (sectionModalMode.value === 'create') {
        sectionForm.post(route('admin.sections.store'), {
            onSuccess: () => closeSectionModal(),
        });
    } else {
        sectionForm.put(route('admin.sections.update', sectionForm.id), {
            onSuccess: () => closeSectionModal(),
        });
    }
};

const deleteSection = (section) => {
    triggerConfirm(
        'Delete Section',
        `Are you sure you want to delete the section "${section.name}"?`,
        () => {
            sectionForm.delete(route('admin.sections.destroy', section.id));
        }
    );
};

// Department Actions
const openAddDepartmentModal = () => {
    departmentForm.reset();
    departmentModalMode.value = 'create';
    isDepartmentModalOpen.value = true;
};

const openEditDepartmentModal = (dept) => {
    departmentForm.reset();
    departmentForm.id = dept.id;
    departmentForm.name = dept.name;
    departmentForm.short_name = dept.short_name;
    departmentForm.department_head_id = dept.department_head_id || '';
    departmentModalMode.value = 'edit';
    isDepartmentModalOpen.value = true;
};

const closeDepartmentModal = () => {
    isDepartmentModalOpen.value = false;
    departmentForm.reset();
};

const submitDepartmentForm = () => {
    if (departmentModalMode.value === 'create') {
        departmentForm.post(route('admin.departments.store'), {
            onSuccess: () => closeDepartmentModal(),
        });
    } else {
        departmentForm.put(route('admin.departments.update', departmentForm.id), {
            onSuccess: () => closeDepartmentModal(),
        });
    }
};

const deleteDepartment = (dept) => {
    triggerConfirm(
        'Delete Department',
        `Are you sure you want to delete the department "${dept.name}" (${dept.short_name})?`,
        () => {
            departmentForm.delete(route('admin.departments.destroy', dept.id));
        }
    );
};

const filteredDepartments = computed(() => {
    if (!departmentSearch.value) return props.departments;
    const query = departmentSearch.value.toLowerCase();
    return props.departments.filter(d => 
        d.name.toLowerCase().includes(query) ||
        d.short_name.toLowerCase().includes(query) ||
        (d.department_head && d.department_head.name.toLowerCase().includes(query))
    );
});

const closeSectionModal = () => {
    isSectionModalOpen.value = false;
    sectionForm.reset();
};

const getInitials = (name) => {
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
};

// Workflow Actions
const addWorkflowRow = () => {
    const lastRow = workflowForm.workflows.length > 0 
        ? workflowForm.workflows[workflowForm.workflows.length - 1] 
        : null;
    const nextOrder = lastRow ? Number(lastRow.order || 0) + 1 : 0;
    const nextType = lastRow ? lastRow.workflow_type_id : '';
    workflowForm.workflows.push({
        name: '',
        order: nextOrder,
        workflow_type_id: nextType,
    });
};

const removeWorkflowRow = (index) => {
    if (workflowForm.workflows.length > 1) {
        workflowForm.workflows.splice(index, 1);
    }
};

const openAddWorkflowModal = () => {
    workflowForm.reset();
    workflowForm.clearErrors();
    workflowForm.id = null;
    workflowForm.workflows = [
        { name: '', order: 0, workflow_type_id: '' }
    ];
    workflowModalMode.value = 'create';
    isWorkflowModalOpen.value = true;
};

const openEditWorkflowModal = (workflow) => {
    workflowForm.reset();
    workflowForm.clearErrors();
    workflowForm.id = workflow.id;
    workflowForm.name = workflow.name;
    workflowForm.order = workflow.order;
    workflowForm.workflow_type_id = workflow.workflow_type_id || '';
    workflowForm.workflows = [];
    workflowModalMode.value = 'edit';
    isWorkflowModalOpen.value = true;
};

const submitWorkflowForm = () => {
    if (workflowModalMode.value === 'create') {
        workflowForm.post(route('admin.workflows.store'), {
            onSuccess: () => closeWorkflowModal(),
        });
    } else {
        workflowForm.put(route('admin.workflows.update', workflowForm.id), {
            onSuccess: () => closeWorkflowModal(),
        });
    }
};

const deleteWorkflow = (workflow) => {
    triggerConfirm(
        'Delete Workflow',
        `Are you sure you want to delete the workflow "${workflow.name}"? This could affect tasks currently in this workflow.`,
        () => {
            workflowForm.delete(route('admin.workflows.destroy', workflow.id));
        }
    );
};

const closeWorkflowModal = () => {
    isWorkflowModalOpen.value = false;
    workflowForm.reset();
    workflowForm.clearErrors();
};

// Workflow Type Actions
const openAddWorkflowTypeModal = () => {
    workflowTypeForm.reset();
    workflowTypeModalMode.value = 'create';
    isWorkflowTypeModalOpen.value = true;
};

const openEditWorkflowTypeModal = (type) => {
    workflowTypeForm.reset();
    workflowTypeForm.id = type.id;
    workflowTypeForm.name = type.name;
    workflowTypeModalMode.value = 'edit';
    isWorkflowTypeModalOpen.value = true;
};

const submitWorkflowTypeForm = () => {
    if (workflowTypeModalMode.value === 'create') {
        workflowTypeForm.post(route('admin.workflow-types.store'), {
            onSuccess: () => closeWorkflowTypeModal(),
        });
    } else {
        workflowTypeForm.put(route('admin.workflow-types.update', workflowTypeForm.id), {
            onSuccess: () => closeWorkflowTypeModal(),
        });
    }
};

const deleteWorkflowType = (type) => {
    triggerConfirm(
        'Delete Workflow Type',
        `Are you sure you want to delete the workflow type "${type.name}"? This will unlink all associated phases.`,
        () => {
            workflowTypeForm.delete(route('admin.workflow-types.destroy', type.id));
        }
    );
};

const closeWorkflowTypeModal = () => {
    isWorkflowTypeModalOpen.value = false;
    workflowTypeForm.reset();
};

const submitBulkAssign = () => {
    if (selectedWorkflowIds.value.length === 0) return;

    const bulkForm = useForm({
        workflow_ids: selectedWorkflowIds.value,
        workflow_type_id: bulkWorkflowTypeId.value,
    });

    bulkForm.post(route('admin.workflows.bulk-assign'), {
        onSuccess: () => {
            selectedWorkflowIds.value = [];
            bulkWorkflowTypeId.value = '';
        },
    });
};
</script>

<template>
    <AppLayout title="Admin Management">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-xs font-semibold text-[#0D9488]">
                        <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                        <span>&bull;</span>
                        <span class="text-slate-400">Admin</span>
                        <span>&bull;</span>
                        <span class="text-slate-400">Management</span>
                    </div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Admin Management
                    </h2>
                </div>

                <!-- Tabs -->
                <div class="flex bg-slate-100 p-1.5 rounded-xl border border-slate-200/60 max-w-fit shadow-inner">
                    <button 
                        @click="activeTab = 'users'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'users' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                         ]"
                    >
                        Users & Members
                    </button>
                    <button 
                        v-if="$page.props.auth.user.role?.slug === 'admin'"
                        @click="activeTab = 'sections'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'sections' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                         ]"
                    >
                        Sections & Assignments
                    </button>
                    <button 
                        v-if="canManageDepartments || $page.props.auth.user.role?.slug === 'admin'"
                        @click="activeTab = 'departments'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'departments' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                         ]"
                    >
                        Departments
                    </button>
                    <button 
                        v-if="$page.props.auth.user.role?.slug === 'admin'"
                        @click="activeTab = 'workflows'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'workflows' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                           ]"
                    >
                        Workflows
                    </button>
                    <button 
                        v-if="$page.props.auth.user.role?.slug === 'admin'"
                        @click="activeTab = 'system_logs'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'system_logs' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                           ]"
                    >
                        System Logs
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-full mx-auto px-8 sm:px-6 lg:px-16 space-y-8">
                
                <!-- Tab: Users & Members -->
                <div v-if="activeTab === 'users'" class="space-y-6">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-[#F0FDFA] border border-teal-100 flex items-center justify-center text-[#0D9488]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.109A11.386 11.386 0 0 1 10.089 20c-2.3 0-4.47-.53-6.402-1.478l-.137-.089V18.25a8.94 8.94 0 0 1 2.224-6.143M21.25 15.75c1.279-1.355 2.152-3.136 2.228-5.093m-2.228 5.093a8.96 8.96 0 0 1-5.093 2.228m5.093-2.228h-.008a9 9 0 0 0-4.834-3.072M2 12.75c.767 1.96 1.64 3.741 2.919 5.094m4.834-3.072A9 9 0 0 0 5.01 12.25H5a8.96 8.96 0 0 0-5.093 2.228m5.093-2.228A9 9 0 0 1 10 3c2.483 0 4.73 1.004 6.36 2.628m-6.36 2.628A8.96 8.96 0 0 0 5 12.25" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Users</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalUsers }}</h3>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-center text-[#EA580C]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Administrators</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalAdmins }}</h3>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-green-50 border border-green-100 flex items-center justify-center text-[#16A34A]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Project Managers</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalPMs }}</h3>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-slate-100 border border-slate-200/50 flex items-center justify-center text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Developers</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalDevs }}</h3>
                            </div>
                        </div>
                    </div>

                    <!-- User management controls -->
                    <div class="flex flex-col sm:flex-row justify-between gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                        <div class="flex flex-1 gap-3 max-w-2xl">
                            <input 
                                type="text" 
                                v-model="userSearch"
                                placeholder="Search by name or email..."
                                class="flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                            />
                            <select 
                                v-model="roleFilter"
                                class="rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm min-w-[150px]"
                            >
                                <option value="">All Roles</option>
                                <optgroup label="System Roles">
                                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </optgroup>
                                <optgroup label="Functional Roles">
                                    <option v-for="mr in memberRoles" :key="mr.id" :value="mr.id">{{ mr.name }}</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                v-if="$page.props.auth.user.role?.slug === 'admin'"
                                @click="openRoleModal"
                                class="px-4 py-2.5 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-slate-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                Manage Roles
                            </button>
                            <button 
                                @click="openAddUserModal"
                                class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add New User
                            </button>
                        </div>
                    </div>

                    <!-- Bulk Delete Toolbar -->
                    <div v-if="selectedUserIds.length > 0 && $page.props.auth.user.role?.slug === 'admin'" class="flex items-center justify-between bg-rose-50 border border-rose-100 p-4 rounded-2xl shadow-sm animate-fade-in transition-all">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-rose-700 uppercase tracking-wider">
                                {{ selectedUserIds.length }} User(s) Selected
                            </span>
                        </div>
                        <button 
                            @click="bulkDeleteUsers"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            Delete Selected
                        </button>
                    </div>

                    <!-- User Table -->
                    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-[#F0FDFA]/70 border-b border-teal-100 text-[#0f766e] font-bold uppercase text-[10px] tracking-wider">
                                        <th v-if="$page.props.auth.user.role?.slug === 'admin'" class="py-4 px-6 w-12 text-center">
                                            <input type="checkbox" :checked="paginatedUsers.length > 0 && paginatedUsers.every(u => selectedUserIds.includes(u.id))" @change="toggleAllUsers" class="rounded border-slate-300 text-[#0D9488] focus:ring-[#0D9488]" />
                                        </th>
                                        <th class="py-4 px-6">User details</th>
                                        <th class="py-4 px-6">System Role</th>
                                        <th class="py-4 px-6">Functional Role</th>
                                        <th class="py-4 px-6">Employee Type</th>
                                        <th class="py-4 px-6">Assigned Section</th>
                                        <th class="py-4 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                    <tr v-for="user in paginatedUsers" :key="user.id" class="hover:bg-slate-50/50 transition even:bg-gray-200/50">
                                        <td v-if="$page.props.auth.user.role?.slug === 'admin'" class="py-4 px-6 text-center">
                                            <input type="checkbox" v-model="selectedUserIds" :value="user.id" class="rounded border-slate-300 text-[#0D9488] focus:ring-[#0D9488]" />
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-slate-100 border border-slate-200/50 text-slate-600 flex items-center justify-center font-bold text-xs">
                                                    {{ getInitials(user.name) }}
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-slate-800 leading-tight">
                                                        {{ user.name }}
                                                        <span class="text-[11px] font-normal text-slate-400 ml-1">@{{ user.username }}</span>
                                                    </h4>
                                                    <span class="text-xs text-slate-400">{{ user.email }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span 
                                                :class="[
                                                    'px-2.5 py-1 text-[10px] font-bold rounded-lg border uppercase tracking-wider',
                                                    user.system_role_slug === 'admin' 
                                                        ? 'bg-rose-50 text-rose-700 border-rose-100' 
                                                        : user.system_role_slug === 'viewer' 
                                                            ? 'bg-slate-50 text-slate-500 border-slate-200' 
                                                            : 'bg-[#F0FDFA] text-[#0D9488] border-[#0D9488]/30'
                                                ]"
                                            >
                                                {{ user.system_role }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="font-medium text-slate-700">{{ user.member_role }}</span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg border uppercase tracking-wider bg-teal-50 text-teal-800 border-teal-200">
                                                {{ user.employee_type || 'Regular' }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex flex-wrap gap-1.5" v-if="user.sections.length">
                                                <span 
                                                    v-for="t in user.sections" 
                                                    :key="t.id"
                                                    class="px-2 py-0.5 text-xs font-semibold rounded-md border bg-slate-50 text-slate-600 border-slate-150"
                                                >
                                                    {{ t.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-slate-400 italic">No assigned sections</span>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button 
                                                    @click="openEditUserModal(user)"
                                                    :disabled="!canEditUser(user)"
                                                    :class="[
                                                        'p-1.5 rounded-lg transition',
                                                        canEditUser(user) 
                                                            ? 'text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA]' 
                                                            : 'text-slate-300 opacity-40 cursor-not-allowed'
                                                    ]"
                                                    :title="canEditUser(user) ? 'Edit User' : 'Admin Staff cannot update details of Administrators or Department Heads'"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                    </svg>
                                                </button>
                                                <button 
                                                    v-if="$page.props.auth.user.role?.slug === 'admin'"
                                                    @click="deleteUser(user)"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                    title="Delete User"
                                                    :disabled="user.id === $page.props.auth.user.id"
                                                    :class="{'opacity-40 cursor-not-allowed': user.id === $page.props.auth.user.id}"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredUsers.length === 0">
                                        <td :colspan="$page.props.auth.user.role?.slug === 'admin' ? 6 : 5" class="py-8 text-center text-slate-400 italic">No users found matching your filters.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="userTotalPages > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div class="text-xs text-slate-500 font-medium">
                                Showing <span class="font-semibold text-slate-700">{{ (userCurrentPage - 1) * userPerPage + 1 }}</span> to 
                                <span class="font-semibold text-slate-700">{{ Math.min(userCurrentPage * userPerPage, filteredUsers.length) }}</span> of 
                                <span class="font-semibold text-slate-700">{{ filteredUsers.length }}</span> users
                            </div>
                            <div class="flex items-center gap-1">
                                <button 
                                    @click="userCurrentPage--" 
                                    :disabled="userCurrentPage === 1"
                                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                    </svg>
                                </button>
                                <button 
                                    v-for="page in userTotalPages" 
                                    :key="page"
                                    @click="userCurrentPage = page"
                                    :class="[
                                        'px-3 py-1 text-xs font-bold rounded-lg transition-all',
                                        userCurrentPage === page 
                                            ? 'bg-[#0D9488] text-white shadow-sm' 
                                            : 'border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                                <button 
                                    @click="userCurrentPage++" 
                                    :disabled="userCurrentPage === userTotalPages"
                                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Sections -->
                <div v-else-if="activeTab === 'sections'" class="space-y-6">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-[#F0FDFA] border border-teal-100 flex items-center justify-center text-[#0D9488]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Sections</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalSections }}</h3>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-green-50 border border-green-100 flex items-center justify-center text-[#16A34A]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Assigned Members</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.assignedMembers }}</h3>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-slate-100 border border-slate-200/50 flex items-center justify-center text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Unassigned Members</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.unassignedMembers }}</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Sections control header -->
                    <div class="flex flex-col sm:flex-row justify-between gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                        <input 
                            type="text" 
                            v-model="sectionSearch"
                            placeholder="Search by section name or manager..."
                            class="max-w-xl flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                        />
                        <button 
                            @click="openAddSectionModal"
                            class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Create New Section
                        </button>
                    </div>

                    <!-- Sections Card Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div 
                            v-for="section in filteredSections" 
                            :key="section.id"
                            class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition relative group"
                        >
                            <div class="space-y-4">
                                <div class="flex justify-between items-start gap-4">
                                    <div>
                                        <span v-if="section.department_name" class="inline-block px-2 py-0.5 text-[10px] font-bold text-teal-700 bg-teal-50 border border-teal-200/60 rounded-md mb-1">
                                            {{ section.department_name }}
                                        </span>
                                        <h3 class="font-bold text-slate-800 text-lg leading-tight group-hover:text-[#0D9488] transition">{{ section.name }}</h3>
                                    </div>
                                    
                                    <div class="flex gap-1.5 opacity-80 group-hover:opacity-100 transition">
                                        <button 
                                            @click="openEditSectionModal(section)"
                                            class="p-1.5 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded-lg transition"
                                            title="Edit Section"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteSection(section)"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Delete Section"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- PM -->
                                <div class="bg-slate-50 border border-slate-100 rounded-xl p-3.5 flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-full bg-[#F0FDFA] border border-teal-100 text-[#0D9488] flex items-center justify-center font-bold text-xs">
                                        {{ section.project_manager ? getInitials(section.project_manager.name) : 'Head' }}
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Head</p>
                                        <h4 class="font-bold text-slate-700 text-sm mt-0.5">
                                            {{ section.project_manager ? section.project_manager.name : 'Unassigned' }}
                                        </h4>
                                    </div>
                                </div>

                                <!-- Members List -->
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-400 font-bold uppercase tracking-wider">Section Members</span>
                                        <span class="font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">{{ section.members.length }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 max-h-[120px] overflow-y-auto" v-if="section.members.length">
                                        <div 
                                            v-for="m in section.members" 
                                            :key="m.id"
                                            class="border border-slate-100 rounded-lg py-1.5 px-2.5 flex items-center gap-2 hover:bg-slate-50/50 transition cursor-default bg-white shadow-sm"
                                        >
                                            <div class="h-6 w-6 rounded-full bg-slate-100 border border-slate-200 text-slate-600 flex items-center justify-center font-bold text-[9px]">
                                                {{ getInitials(m.name) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-800 text-[11px] truncate leading-none">{{ m.name }}</p>
                                                <span class="text-[9px] text-slate-400 leading-none truncate block mt-0.5">{{ m.role }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="text-slate-400 text-xs italic py-2">
                                        No members assigned to this section yet.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredSections.length === 0" class="col-span-full py-8 text-center text-slate-400 italic bg-white border border-slate-100 rounded-2xl shadow-sm">
                            No sections found matching your search.
                        </div>
                    </div>
                </div>

                <!-- Tab: Departments -->
                <div v-else-if="activeTab === 'departments'" class="space-y-6">
                    <!-- Header Controls & Search -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="relative flex-1 max-w-md">
                            <input 
                                type="text" 
                                v-model="departmentSearch" 
                                placeholder="Search department by name, short code, or department head..." 
                                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border-slate-200 focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm" 
                            />
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-400 absolute left-3.5 top-2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>

                        <div class="flex items-center gap-3">
                            <button 
                                @click="openAddDepartmentModal"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add New Department
                            </button>
                        </div>
                    </div>

                    <!-- Departments Table -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3.5">Department Name</th>
                                        <th class="px-6 py-3.5">Short Code</th>
                                        <th class="px-6 py-3.5">Department Head</th>
                                        <th class="px-6 py-3.5">Associated Sections</th>
                                        <th class="px-6 py-3.5 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <tr v-for="dept in filteredDepartments" :key="dept.id" class="hover:bg-slate-50/50 transition">
                                        <td class="px-6 py-4 font-bold text-slate-800">
                                            <div class="flex items-center gap-2.5">
                                                <div class="h-8 w-8 rounded-lg bg-teal-50 border border-teal-200 text-[#0D9488] flex items-center justify-center font-extrabold text-xs uppercase">
                                                    {{ dept.short_name.slice(0, 3) }}
                                                </div>
                                                <span>{{ dept.name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 uppercase tracking-wide">
                                                {{ dept.short_name }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div v-if="dept.department_head" class="flex items-center gap-2">
                                                <div class="h-6 w-6 rounded-full bg-teal-100 text-teal-800 text-[10px] font-bold flex items-center justify-center">
                                                    {{ getInitials(dept.department_head.name) }}
                                                </div>
                                                <div>
                                                    <p class="font-bold text-slate-800 text-xs leading-none">{{ dept.department_head.name }}</p>
                                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">{{ dept.department_head.email }}</p>
                                                </div>
                                            </div>
                                            <span v-else class="text-slate-400 italic">Unassigned</span>
                                        </td>
                                        <td class="px-6 py-4 font-bold">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-[#0D9488] border border-teal-100">
                                                {{ dept.sections_count }} {{ dept.sections_count === 1 ? 'Section' : 'Sections' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button 
                                                    @click="openEditDepartmentModal(dept)" 
                                                    class="px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition"
                                                >
                                                    Edit
                                                </button>
                                                <button 
                                                    @click="deleteDepartment(dept)" 
                                                    class="px-2.5 py-1 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-lg transition"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr v-if="filteredDepartments.length === 0">
                                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            <p class="font-bold text-slate-600 text-sm">No departments found</p>
                                            <p class="text-xs text-slate-400 mt-1">Get started by creating a new department.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Workflows -->
                <div v-else-if="activeTab === 'workflows'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Workflow Types management card -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-xs text-slate-400 uppercase tracking-wider">Workflow Types</h3>
                                <button 
                                    @click="openAddWorkflowTypeModal"
                                    class="p-1.5 bg-[#F0FDFA] hover:bg-teal-100 text-[#0D9488] rounded-lg transition"
                                    title="Add Workflow Type"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="divide-y divide-slate-100 text-sm text-slate-600">
                                <div v-for="type in props.workflowTypes" :key="type.id" class="py-3 flex items-center justify-between group">
                                    <span class="font-semibold text-slate-700">{{ type.name }}</span>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button 
                                            @click="openEditWorkflowTypeModal(type)"
                                            class="p-1 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded"
                                            title="Edit Type"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteWorkflowType(type)"
                                            class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded"
                                            title="Delete Type"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="props.workflowTypes.length === 0" class="py-4 text-center text-slate-400 italic">
                                    No workflow types configured.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Workflows grouped list -->
                    <div class="lg:col-span-2 space-y-4">
                        <!-- Bulk Actions Bar -->
                        <div v-if="selectedWorkflowIds.length > 0" class="bg-[#F0FDFA] border border-teal-100 rounded-2xl p-4 flex flex-col sm:flex-row justify-between items-center gap-4 transition-all shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="h-6 w-6 rounded-lg bg-[#0D9488] text-white flex items-center justify-center text-xs font-bold shadow-sm">{{ selectedWorkflowIds.length }}</span>
                                <span class="text-xs font-bold text-teal-900 uppercase tracking-wider">Workflow(s) Selected</span>
                            </div>
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <select 
                                    v-model="bulkWorkflowTypeId" 
                                    class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm bg-white min-w-[180px] py-1.5"
                                >
                                    <option value="" disabled selected>Assign to Workflow Type...</option>
                                    <option value="uncategorized">General / Uncategorized</option>
                                    <option v-for="t in props.workflowTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <button 
                                    @click="submitBulkAssign"
                                    class="px-3.5 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl transition shadow-sm"
                                >
                                    Apply
                                </button>
                                <button 
                                    @click="selectedWorkflowIds = []"
                                    class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl transition shadow-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                            <input 
                                type="text" 
                                v-model="workflowSearch"
                                placeholder="Search workflows..."
                                class="max-w-xl flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                            />
                            <button 
                                @click="openAddWorkflowModal"
                                class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add Workflow
                            </button>
                        </div>

                        <!-- Grouped Workflows Display -->
                        <div class="space-y-6">
                            <div 
                                v-for="(workflowsGroup, typeName) in groupedWorkflows" 
                                :key="typeName" 
                                class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden"
                            >
                                <div class="bg-slate-50/70 border-b border-slate-100 px-6 py-3 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                                        {{ typeName }}
                                    </span>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-[#F0FDFA] text-[#0D9488] uppercase tracking-wider">
                                        {{ workflowsGroup.length }} Workflow(s)
                                    </span>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-[#F0FDFA]/70 border-b border-teal-100 text-[#0f766e] font-bold uppercase text-[9px] tracking-wider">
                                                <th class="py-2.5 px-6 w-12 text-center">
                                                    <!-- Checkbox column -->
                                                </th>
                                                <th class="py-2.5 px-6 w-20">Order</th>
                                                <th class="py-2.5 px-6">Workflow Name</th>
                                                <th class="py-2.5 px-6 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                            <tr v-for="workflow in workflowsGroup" :key="workflow.id" class="hover:bg-slate-50/30 transition">
                                                <td class="py-3 px-6 text-center w-12">
                                                    <input 
                                                        type="checkbox" 
                                                        :value="workflow.id"
                                                        v-model="selectedWorkflowIds"
                                                        class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                                    />
                                                </td>
                                                <td class="py-3 px-6 font-bold text-slate-500">
                                                    #{{ workflow.order }}
                                                </td>
                                                <td class="py-3 px-6 font-bold text-slate-800">
                                                    {{ workflow.name }}
                                                </td>
                                                <td class="py-3 px-6 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button 
                                                            @click="openEditWorkflowModal(workflow)"
                                                            class="p-1.5 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded-lg transition"
                                                            title="Edit Workflow"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                            </svg>
                                                        </button>
                                                        <button 
                                                            @click="deleteWorkflow(workflow)"
                                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                            title="Delete Workflow"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="Object.keys(groupedWorkflows).length === 0" class="bg-white border border-slate-100 p-8 text-center text-slate-400 italic rounded-2xl shadow-sm">
                                No workflows found.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: System Logs -->
                <div v-else-if="activeTab === 'system_logs'" class="space-y-6">
                    <!-- Search Card -->
                    <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="relative flex-1 max-w-md">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
                                </svg>
                            </span>
                            <input 
                                type="text" 
                                v-model="systemLogSearch" 
                                class="w-full pl-9 rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" 
                                placeholder="Search logs by action, description, user, IP..." 
                            />
                        </div>
                        <div class="text-xs font-semibold text-slate-400">
                            Total Logs: {{ filteredSystemLogs.length }}
                        </div>
                    </div>

                    <!-- Logs Table Card -->
                    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                        <th class="py-3.5 px-6">Timestamp</th>
                                        <th class="py-3.5 px-6">User</th>
                                        <th class="py-3.5 px-6">Action</th>
                                        <th class="py-3.5 px-6">Description</th>
                                        <th class="py-3.5 px-6">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs text-slate-600">
                                    <tr v-for="log in paginatedSystemLogs" :key="log.id" class="hover:bg-slate-50/50 transition">
                                        <td class="py-4 px-6 font-medium text-slate-700 whitespace-nowrap">
                                            {{ formatDateTime(log.created_at) }}
                                        </td>
                                        <td class="py-4 px-6 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <div class="h-6 w-6 rounded-full bg-slate-100 text-slate-650 flex items-center justify-center font-bold text-[9px] uppercase">
                                                    {{ log.user_name ? log.user_name.slice(0, 2) : 'SY' }}
                                                </div>
                                                <span class="font-semibold text-slate-700">{{ log.user_name || 'System' }}</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 whitespace-nowrap">
                                            <span 
                                                class="px-2 py-0.5 text-[10px] font-bold rounded-lg border uppercase whitespace-nowrap"
                                                :class="[
                                                    log.action.includes('Create') ? 'text-teal-700 border-teal-200 bg-teal-50' : 
                                                    log.action.includes('Update') ? 'text-indigo-700 border-indigo-200 bg-indigo-50/50' : 
                                                    log.action.includes('Delete') ? 'text-rose-700 border-rose-200 bg-rose-50' : 
                                                    log.action.includes('Login') || log.action.includes('Logout') ? 'text-amber-700 border-amber-200 bg-amber-50' :
                                                    'text-slate-700 border-slate-200 bg-slate-50'
                                                ]"
                                            >
                                                {{ log.action }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 min-w-[280px]">
                                            {{ log.description }}
                                        </td>
                                        <td class="py-4 px-6 text-slate-400 font-mono whitespace-nowrap">
                                            {{ log.ip_address || 'N/A' }}
                                        </td>
                                    </tr>
                                    <tr v-if="paginatedSystemLogs.length === 0">
                                        <td colspan="5" class="py-8 px-6 text-center text-slate-400 italic">
                                            No system logs found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div v-if="logsTotalPages > 1" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/20">
                            <div class="text-xs text-slate-400 font-semibold">
                                Showing <span class="font-semibold text-slate-700">{{ (logsCurrentPage - 1) * logsPerPage + 1 }}</span> to 
                                <span class="font-semibold text-slate-700">{{ Math.min(logsCurrentPage * logsPerPage, filteredSystemLogs.length) }}</span> of 
                                <span class="font-semibold text-slate-700">{{ filteredSystemLogs.length }}</span> logs
                            </div>
                            <div class="flex items-center gap-1">
                                <button 
                                    @click="logsCurrentPage--" 
                                    :disabled="logsCurrentPage === 1"
                                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                    </svg>
                                </button>
                                <button 
                                    v-for="page in logsTotalPages" 
                                    :key="page"
                                    @click="logsCurrentPage = page"
                                    :class="[
                                        'px-3 py-1 text-xs font-bold rounded-lg transition-all',
                                        logsCurrentPage === page 
                                            ? 'bg-[#0D9488] text-white shadow-sm' 
                                            : 'border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-700'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                                <button 
                                    @click="logsCurrentPage++" 
                                    :disabled="logsCurrentPage === logsTotalPages"
                                    class="p-2 border border-slate-200 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-700 transition disabled:opacity-40 disabled:hover:bg-transparent disabled:cursor-not-allowed"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- User Create/Edit Modal -->
        <UserFormModal
            :show="isUserModalOpen"
            :mode="userModalMode"
            :form="userForm"
            :roles="roles"
            :member-roles="memberRoles"
            :sections="availableSections"
            :employee-types="employeeTypes"
            @close="closeUserModal"
            @submit="submitUserForm"
        />

        <!-- Section Create/Edit Modal -->
        <SectionFormModal
            :show="isSectionModalOpen"
            :mode="sectionModalMode"
            :form="sectionForm"
            :departments="departments"
            :members-list="membersList"
            @close="closeSectionModal"
            @submit="submitSectionForm"
        />

        <!-- Workflow Create/Edit Modal -->
        <WorkflowFormModal
            :show="isWorkflowModalOpen"
            :mode="workflowModalMode"
            :form="workflowForm"
            :workflow-types="workflowTypes"
            @close="closeWorkflowModal"
            @submit="submitWorkflowForm"
            @add-row="addWorkflowRow"
            @remove-row="removeWorkflowRow"
        />

        <!-- Workflow Type Create/Edit Modal -->
        <WorkflowTypeFormModal
            :show="isWorkflowTypeModalOpen"
            :mode="workflowTypeModalMode"
            :form="workflowTypeForm"
            @close="closeWorkflowTypeModal"
            @submit="submitWorkflowTypeForm"
        />

        <!-- Manage Functional Roles Modal -->
        <RoleFormModal
            :show="isRoleModalOpen"
            :mode="roleModalMode"
            :form="roleForm"
            :member-roles="memberRoles"
            @close="closeRoleModal"
            @submit="submitRoleForm"
            @edit-role="openEditRoleModal"
            @delete-role="deleteRole"
            @cancel-edit="cancelEditRoleMode"
        />

        <!-- Department Create/Edit Modal -->
        <DepartmentFormModal
            :show="isDepartmentModalOpen"
            :mode="departmentModalMode"
            :form="departmentForm"
            :members-list="membersList"
            @close="closeDepartmentModal"
            @submit="submitDepartmentForm"
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
    </AppLayout>
</template>
