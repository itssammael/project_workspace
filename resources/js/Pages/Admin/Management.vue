<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    users: Array,
    teams: Array,
    roles: Array,
    memberRoles: Array,
    membersList: Array,
    phases: Array,
    developmentTypes: Array,
});

// Active tab
const activeTab = ref('users'); // 'users', 'teams', or 'phases'

// Search & filter states
const userSearch = ref('');
const roleFilter = ref('');
const teamSearch = ref('');
const phaseSearch = ref('');
const projectTypeFilter = ref('');

// Modals state
const isUserModalOpen = ref(false);
const userModalMode = ref('create'); // 'create', 'edit'
const isTeamModalOpen = ref(false);
const teamModalMode = ref('create'); // 'create', 'edit'
const isPhaseModalOpen = ref(false);
const phaseModalMode = ref('create'); // 'create', 'edit'
const isDevTypeModalOpen = ref(false);
const devTypeModalMode = ref('create'); // 'create', 'edit'

// Bulk phase assignment selection
const selectedPhaseIds = ref([]);
const bulkDevTypeId = ref('');

// Forms
const userForm = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    role_id: '',
    member_role_id: '',
});

const teamForm = useForm({
    id: null,
    name: '',
    member_id: '', // PM
    member_ids: [], // Assigned members
});

const phaseForm = useForm({
    id: null,
    name: '',
    order: 0,
    development_type_id: '',
});

const devTypeForm = useForm({
    id: null,
    name: '',
});

// Compute stats reactively
const stats = computed(() => {
    const totalUsers = props.users.length;
    const totalAdmins = props.users.filter(u => u.system_role_slug === 'admin').length;
    const totalPMs = props.users.filter(u => u.member_role === 'Project Manager').length;
    const totalDevs = props.users.filter(u => u.member_role === 'Developer' || u.member_role === 'Lead Developer').length;
    
    const totalTeams = props.teams.length;
    
    // Count unique members assigned to at least one team
    const assignedMemberIds = new Set();
    props.teams.forEach(t => {
        t.members.forEach(m => assignedMemberIds.add(m.id));
    });
    const assignedMembers = assignedMemberIds.size;
    const unassignedMembers = props.membersList.length - assignedMembers;

    return {
        totalUsers,
        totalAdmins,
        totalPMs,
        totalDevs,
        totalTeams,
        assignedMembers,
        unassignedMembers
    };
});

// Filter users
const filteredUsers = computed(() => {
    return props.users.filter(u => {
        const matchesSearch = u.name.toLowerCase().includes(userSearch.value.toLowerCase()) || 
                              u.email.toLowerCase().includes(userSearch.value.toLowerCase());
        const matchesRole = !roleFilter.value || u.member_role_id == roleFilter.value || u.role_id == roleFilter.value;
        return matchesSearch && matchesRole;
    });
});

// Filter teams
const filteredTeams = computed(() => {
    if (!teamSearch.value) return props.teams;
    return props.teams.filter(t => 
        t.name.toLowerCase().includes(teamSearch.value.toLowerCase()) ||
        (t.project_manager && t.project_manager.name.toLowerCase().includes(teamSearch.value.toLowerCase()))
    );
});

// Filter phases
const filteredPhases = computed(() => {
    return props.phases.filter(p => {
        const nameMatch = p.name.toLowerCase().includes(phaseSearch.value.toLowerCase());
        const typeSearchMatch = p.project_type && p.project_type.toLowerCase().includes(phaseSearch.value.toLowerCase());
        return nameMatch || typeSearchMatch;
    });
});

// Group phases by development type
const groupedPhases = computed(() => {
    const groups = {};
    filteredPhases.value.forEach(p => {
        const typeName = p.project_type || 'General/Uncategorized';
        if (!groups[typeName]) {
            groups[typeName] = [];
        }
        groups[typeName].push(p);
    });
    return groups;
});

// User Actions
const openAddUserModal = () => {
    userForm.reset();
    userModalMode.value = 'create';
    isUserModalOpen.value = true;
};

const openEditUserModal = (user) => {
    userForm.reset();
    userForm.id = user.id;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = ''; // leave blank by default
    userForm.role_id = user.role_id;
    userForm.member_role_id = user.member_role_id || '';
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

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete ${user.name}? This will also delete their member profile.`)) {
        userForm.delete(route('admin.users.destroy', user.id));
    }
};

const closeUserModal = () => {
    isUserModalOpen.value = false;
    userForm.reset();
};

// Team Actions
const openAddTeamModal = () => {
    teamForm.reset();
    teamModalMode.value = 'create';
    isTeamModalOpen.value = true;
};

const openEditTeamModal = (team) => {
    teamForm.reset();
    teamForm.id = team.id;
    teamForm.name = team.name;
    teamForm.member_id = team.member_id || '';
    teamForm.member_ids = team.members.map(m => m.id);
    teamModalMode.value = 'edit';
    isTeamModalOpen.value = true;
};

const submitTeamForm = () => {
    if (teamModalMode.value === 'create') {
        teamForm.post(route('admin.teams.store'), {
            onSuccess: () => closeTeamModal(),
        });
    } else {
        teamForm.put(route('admin.teams.update', teamForm.id), {
            onSuccess: () => closeTeamModal(),
        });
    }
};

const deleteTeam = (team) => {
    if (confirm(`Are you sure you want to delete the team "${team.name}"?`)) {
        teamForm.delete(route('admin.teams.destroy', team.id));
    }
};

const closeTeamModal = () => {
    isTeamModalOpen.value = false;
    teamForm.reset();
};

const toggleMemberAssignment = (memberId) => {
    const index = teamForm.member_ids.indexOf(memberId);
    if (index > -1) {
        teamForm.member_ids.splice(index, 1);
    } else {
        teamForm.member_ids.push(memberId);
    }
};

const getInitials = (name) => {
    return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
};

// Phase Actions
const openAddPhaseModal = () => {
    phaseForm.reset();
    phaseModalMode.value = 'create';
    isPhaseModalOpen.value = true;
};

const openEditPhaseModal = (phase) => {
    phaseForm.reset();
    phaseForm.id = phase.id;
    phaseForm.name = phase.name;
    phaseForm.order = phase.order;
    phaseForm.development_type_id = phase.development_type_id || '';
    phaseModalMode.value = 'edit';
    isPhaseModalOpen.value = true;
};

const submitPhaseForm = () => {
    if (phaseModalMode.value === 'create') {
        phaseForm.post(route('admin.phases.store'), {
            onSuccess: () => closePhaseModal(),
        });
    } else {
        phaseForm.put(route('admin.phases.update', phaseForm.id), {
            onSuccess: () => closePhaseModal(),
        });
    }
};

const deletePhase = (phase) => {
    if (confirm(`Are you sure you want to delete the phase "${phase.name}"? This could affect tasks currently in this phase.`)) {
        phaseForm.delete(route('admin.phases.destroy', phase.id));
    }
};

const closePhaseModal = () => {
    isPhaseModalOpen.value = false;
    phaseForm.reset();
};

// Development Type Actions
const openAddDevTypeModal = () => {
    devTypeForm.reset();
    devTypeModalMode.value = 'create';
    isDevTypeModalOpen.value = true;
};

const openEditDevTypeModal = (type) => {
    devTypeForm.reset();
    devTypeForm.id = type.id;
    devTypeForm.name = type.name;
    devTypeModalMode.value = 'edit';
    isDevTypeModalOpen.value = true;
};

const submitDevTypeForm = () => {
    if (devTypeModalMode.value === 'create') {
        devTypeForm.post(route('admin.dev-types.store'), {
            onSuccess: () => closeDevTypeModal(),
        });
    } else {
        devTypeForm.put(route('admin.dev-types.update', devTypeForm.id), {
            onSuccess: () => closeDevTypeModal(),
        });
    }
};

const deleteDevType = (type) => {
    if (confirm(`Are you sure you want to delete the development type "${type.name}"? This will unlink all associated phases.`)) {
        devTypeForm.delete(route('admin.dev-types.destroy', type.id));
    }
};

const closeDevTypeModal = () => {
    isDevTypeModalOpen.value = false;
    devTypeForm.reset();
};

const submitBulkAssign = () => {
    if (selectedPhaseIds.value.length === 0) return;

    const bulkForm = useForm({
        phase_ids: selectedPhaseIds.value,
        development_type_id: bulkDevTypeId.value,
    });

    bulkForm.post(route('admin.phases.bulk-assign'), {
        onSuccess: () => {
            selectedPhaseIds.value = [];
            bulkDevTypeId.value = '';
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
                        @click="activeTab = 'teams'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'teams' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                         ]"
                    >
                        Teams & Assignments
                    </button>
                    <button 
                        @click="activeTab = 'phases'"
                        :class="[
                             'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                             activeTab === 'phases' 
                                 ? 'bg-white text-[#0D9488] shadow-sm border border-slate-200/20' 
                                 : 'text-slate-500 hover:text-slate-800'
                         ]"
                    >
                        Development Phases
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

                    <!-- User Table -->
                    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-[#F0FDFA]/70 border-b border-teal-100 text-[#0f766e] font-bold uppercase text-[10px] tracking-wider">
                                        <th class="py-4 px-6">User details</th>
                                        <th class="py-4 px-6">System Role</th>
                                        <th class="py-4 px-6">Functional Role</th>
                                        <th class="py-4 px-6">Assigned Teams</th>
                                        <th class="py-4 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                    <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-slate-50/50 transition">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-slate-100 border border-slate-200/50 text-slate-600 flex items-center justify-center font-bold text-xs">
                                                    {{ getInitials(user.name) }}
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-slate-800 leading-tight">{{ user.name }}</h4>
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
                                            <div class="flex flex-wrap gap-1.5" v-if="user.teams.length">
                                                <span 
                                                    v-for="t in user.teams" 
                                                    :key="t.id"
                                                    class="px-2 py-0.5 text-xs font-semibold rounded-md border bg-slate-50 text-slate-600 border-slate-150"
                                                >
                                                    {{ t.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-slate-400 italic">No assigned teams</span>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button 
                                                    @click="openEditUserModal(user)"
                                                    class="p-1.5 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded-lg transition"
                                                    title="Edit User"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                    </svg>
                                                </button>
                                                <button 
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
                                        <td colspan="5" class="py-8 text-center text-slate-400 italic">No users found matching your filters.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Teams -->
                <div v-else-if="activeTab === 'teams'" class="space-y-6">
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl bg-[#F0FDFA] border border-teal-100 flex items-center justify-center text-[#0D9488]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Teams</p>
                                <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.totalTeams }}</h3>
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

                    <!-- Teams control header -->
                    <div class="flex flex-col sm:flex-row justify-between gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                        <input 
                            type="text" 
                            v-model="teamSearch"
                            placeholder="Search by team name or manager..."
                            class="max-w-xl flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                        />
                        <button 
                            @click="openAddTeamModal"
                            class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Create New Team
                        </button>
                    </div>

                    <!-- Teams Card Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div 
                            v-for="team in filteredTeams" 
                            :key="team.id"
                            class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition relative group"
                        >
                            <div class="space-y-4">
                                <div class="flex justify-between items-start gap-4">
                                    <h3 class="font-bold text-slate-800 text-lg leading-tight group-hover:text-[#0D9488] transition">{{ team.name }}</h3>
                                    
                                    <div class="flex gap-1.5 opacity-80 group-hover:opacity-100 transition">
                                        <button 
                                            @click="openEditTeamModal(team)"
                                            class="p-1.5 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded-lg transition"
                                            title="Edit Team"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteTeam(team)"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Delete Team"
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
                                        {{ team.project_manager ? getInitials(team.project_manager.name) : 'PM' }}
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Project Manager</p>
                                        <h4 class="font-bold text-slate-700 text-sm mt-0.5">
                                            {{ team.project_manager ? team.project_manager.name : 'Unassigned' }}
                                        </h4>
                                    </div>
                                </div>

                                <!-- Members List -->
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-slate-400 font-bold uppercase tracking-wider">Team Members</span>
                                        <span class="font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">{{ team.members.length }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 max-h-[120px] overflow-y-auto" v-if="team.members.length">
                                        <div 
                                            v-for="m in team.members" 
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
                                        No members assigned to this team yet.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="filteredTeams.length === 0" class="col-span-full py-8 text-center text-slate-400 italic bg-white border border-slate-100 rounded-2xl shadow-sm">
                            No teams found matching your search.
                        </div>
                    </div>
                </div>

                <!-- Tab: Phases -->
                <div v-if="activeTab === 'phases'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Development Types management card -->
                    <div class="lg:col-span-1 space-y-4">
                        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-xs text-slate-400 uppercase tracking-wider">Development Types</h3>
                                <button 
                                    @click="openAddDevTypeModal"
                                    class="p-1.5 bg-[#F0FDFA] hover:bg-teal-100 text-[#0D9488] rounded-lg transition"
                                    title="Add Development Type"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="divide-y divide-slate-100 text-sm text-slate-600">
                                <div v-for="type in props.developmentTypes" :key="type.id" class="py-3 flex items-center justify-between group">
                                    <span class="font-semibold text-slate-700">{{ type.name }}</span>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button 
                                            @click="openEditDevTypeModal(type)"
                                            class="p-1 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded"
                                            title="Edit Type"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteDevType(type)"
                                            class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded"
                                            title="Delete Type"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="props.developmentTypes.length === 0" class="py-4 text-center text-slate-400 italic">
                                    No types configured.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Development Phases grouped list -->
                    <div class="lg:col-span-2 space-y-4">
                        <!-- Bulk Actions Bar -->
                        <div v-if="selectedPhaseIds.length > 0" class="bg-[#F0FDFA] border border-teal-100 rounded-2xl p-4 flex flex-col sm:flex-row justify-between items-center gap-4 transition-all shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="h-6 w-6 rounded-lg bg-[#0D9488] text-white flex items-center justify-center text-xs font-bold shadow-sm">{{ selectedPhaseIds.length }}</span>
                                <span class="text-xs font-bold text-teal-900 uppercase tracking-wider">Phase(s) Selected</span>
                            </div>
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <select 
                                    v-model="bulkDevTypeId" 
                                    class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm bg-white min-w-[180px] py-1.5"
                                >
                                    <option value="" disabled selected>Assign to Type...</option>
                                    <option value="uncategorized">General / Uncategorized</option>
                                    <option v-for="t in props.developmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <button 
                                    @click="submitBulkAssign"
                                    class="px-3.5 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl transition shadow-sm"
                                >
                                    Apply
                                </button>
                                <button 
                                    @click="selectedPhaseIds = []"
                                    class="px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 text-xs font-bold rounded-xl transition shadow-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row justify-between gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                            <input 
                                type="text" 
                                v-model="phaseSearch"
                                placeholder="Search phases..."
                                class="max-w-xl flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                            />
                            <button 
                                @click="openAddPhaseModal"
                                class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                                Add New Phase
                            </button>
                        </div>

                        <!-- Grouped Phases Display -->
                        <div class="space-y-6">
                            <div 
                                v-for="(phasesGroup, typeName) in groupedPhases" 
                                :key="typeName" 
                                class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden"
                            >
                                <div class="bg-slate-50/70 border-b border-slate-100 px-6 py-3 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                                        {{ typeName }}
                                    </span>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded bg-[#F0FDFA] text-[#0D9488] uppercase tracking-wider">
                                        {{ phasesGroup.length }} Phase(s)
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
                                                <th class="py-2.5 px-6">Phase Name</th>
                                                <th class="py-2.5 px-6 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                            <tr v-for="phase in phasesGroup" :key="phase.id" class="hover:bg-slate-50/30 transition">
                                                <td class="py-3 px-6 text-center w-12">
                                                    <input 
                                                        type="checkbox" 
                                                        :value="phase.id"
                                                        v-model="selectedPhaseIds"
                                                        class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                                    />
                                                </td>
                                                <td class="py-3 px-6 font-bold text-slate-500">
                                                    #{{ phase.order }}
                                                </td>
                                                <td class="py-3 px-6 font-bold text-slate-800">
                                                    {{ phase.name }}
                                                </td>
                                                <td class="py-3 px-6 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button 
                                                            @click="openEditPhaseModal(phase)"
                                                            class="p-1.5 text-slate-400 hover:text-[#0D9488] hover:bg-[#F0FDFA] rounded-lg transition"
                                                            title="Edit Phase"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                                            </svg>
                                                        </button>
                                                        <button 
                                                            @click="deletePhase(phase)"
                                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                                            title="Delete Phase"
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
                            
                            <div v-if="Object.keys(groupedPhases).length === 0" class="bg-white border border-slate-100 p-8 text-center text-slate-400 italic rounded-2xl shadow-sm">
                                No development phases found.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- User Create/Edit Modal -->
        <div v-if="isUserModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeUserModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ userModalMode === 'create' ? 'Add New User & Member' : 'Edit User Profile' }}
                    </h3>
                    <button @click="closeUserModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitUserForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Full Name</label>
                        <input type="text" v-model="userForm.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Jane Doe" />
                        <div v-if="userForm.errors.name" class="text-rose-500 text-xs mt-1">{{ userForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" v-model="userForm.email" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. jane@example.com" />
                        <div v-if="userForm.errors.email" class="text-rose-500 text-xs mt-1">{{ userForm.errors.email }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                            Password <span v-if="userModalMode === 'edit'" class="text-slate-400 font-normal">(Leave empty to keep current)</span>
                        </label>
                        <input type="password" v-model="userForm.password" :required="userModalMode === 'create'" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="Minimum 8 characters" />
                        <div v-if="userForm.errors.password" class="text-rose-500 text-xs mt-1">{{ userForm.errors.password }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">System Role</label>
                            <select v-model="userForm.role_id" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="">Select Role</option>
                                <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                            </select>
                            <div v-if="userForm.errors.role_id" class="text-rose-500 text-xs mt-1">{{ userForm.errors.role_id }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Functional Role</label>
                            <select v-model="userForm.member_role_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="">None (Visitor)</option>
                                <option v-for="mr in memberRoles" :key="mr.id" :value="mr.id">{{ mr.name }}</option>
                            </select>
                            <div v-if="userForm.errors.member_role_id" class="text-rose-500 text-xs mt-1">{{ userForm.errors.member_role_id }}</div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closeUserModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="userForm.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Team Create/Edit Modal -->
        <div v-if="isTeamModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeTeamModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-lg w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ teamModalMode === 'create' ? 'Create New Team' : 'Edit Team' }}
                    </h3>
                    <button @click="closeTeamModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitTeamForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Team Name</label>
                        <input type="text" v-model="teamForm.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Beta Development Team" />
                        <div v-if="teamForm.errors.name" class="text-rose-500 text-xs mt-1">{{ teamForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Project Manager / Team Leader</label>
                        <select v-model="teamForm.member_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="m in membersList" :key="m.id" :value="m.id">
                                {{ m.name }} ({{ m.role }})
                            </option>
                        </select>
                        <div v-if="teamForm.errors.member_id" class="text-rose-500 text-xs mt-1">{{ teamForm.errors.member_id }}</div>
                    </div>

                    <!-- Member Assignment List -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Assign Team Members</label>
                        <div class="border border-slate-100 rounded-xl max-h-60 overflow-y-auto p-3 bg-slate-50/50 space-y-2">
                            <div 
                                v-for="m in membersList" 
                                :key="m.id"
                                @click="toggleMemberAssignment(m.id)"
                                :class="[
                                    'flex items-center gap-3 p-2.5 rounded-lg border cursor-pointer select-none transition-all',
                                    teamForm.member_ids.includes(m.id) 
                                        ? 'bg-[#F0FDFA] border-teal-200 text-teal-900 shadow-sm' 
                                        : 'bg-white border-slate-100 text-slate-600 hover:bg-slate-50'
                                ]"
                            >
                                <input 
                                    type="checkbox" 
                                    :checked="teamForm.member_ids.includes(m.id)" 
                                    @click.stop 
                                    @change="toggleMemberAssignment(m.id)"
                                    class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-xs truncate leading-none text-slate-800">{{ m.name }}</p>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase tracking-wider leading-none mt-1 block">
                                        {{ m.role }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closeTeamModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="teamForm.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Phase Create/Edit Modal -->
        <div v-if="isPhaseModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closePhaseModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ phaseModalMode === 'create' ? 'Add Development Phase' : 'Edit Development Phase' }}
                    </h3>
                    <button @click="closePhaseModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitPhaseForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phase Name</label>
                        <input type="text" v-model="phaseForm.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Design & Prototype" />
                        <div v-if="phaseForm.errors.name" class="text-rose-500 text-xs mt-1">{{ phaseForm.errors.name }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Display Order</label>
                        <input type="number" v-model="phaseForm.order" required min="0" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. 1" />
                        <div v-if="phaseForm.errors.order" class="text-rose-500 text-xs mt-1">{{ phaseForm.errors.order }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Development Type</label>
                        <select v-model="phaseForm.development_type_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                            <option value="">Uncategorized / General</option>
                            <option v-for="t in props.developmentTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                        <div v-if="phaseForm.errors.development_type_id" class="text-rose-500 text-xs mt-1">{{ phaseForm.errors.development_type_id }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closePhaseModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="phaseForm.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save Phase
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Development Type Create/Edit Modal -->
        <div v-if="isDevTypeModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeDevTypeModal"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ devTypeModalMode === 'create' ? 'Add Development Type' : 'Edit Development Type' }}
                    </h3>
                    <button @click="closeDevTypeModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitDevTypeForm" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type Name</label>
                        <input type="text" v-model="devTypeForm.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Software Development" />
                        <div v-if="devTypeForm.errors.name" class="text-rose-500 text-xs mt-1">{{ devTypeForm.errors.name }}</div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="closeDevTypeModal" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            :disabled="devTypeForm.processing"
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
