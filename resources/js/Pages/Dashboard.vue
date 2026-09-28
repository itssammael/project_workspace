<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RoleSwitcher from '@/Components/RoleSwitcher.vue';
import StaffDashboard from '@/Pages/Dashboard/StaffDashboard.vue';
import SupervisorDashboard from '@/Pages/Dashboard/SupervisorDashboard.vue';
import DepartmentHeadDashboard from '@/Pages/Dashboard/DepartmentHeadDashboard.vue';
import AdminDashboard from '@/Pages/Dashboard/AdminDashboard.vue';
import { getInitials } from '@/Utils/helpers';

const props = defineProps({
    // Role-based architecture props
    activeView: {
        type: String,
        default: 'staff',
    },
    availableViews: {
        type: Array,
        default: () => [],
    },
    userInfo: {
        type: Object,
        default: () => ({}),
    },
    viewData: {
        type: Object,
        default: () => ({}),
    },

    // Backward-compatible props
    taskBoards: Array,
    projects: Array,
    pendingTasks: Array,
    undeliveredTasks: Array,
    isDeptHead: Boolean,
    isProjectManager: Boolean,
    memberRole: String,
    systemRole: String,
});

const currentView = computed(() => props.activeView || 'staff');

// Dynamic view title and description
const viewTitle = computed(() => {
    switch (currentView.value) {
        case 'admin':
            return 'System Administration & Governance';
        case 'department_head':
            return 'Department Executive Overview';
        case 'supervisor':
            return 'Supervisor Operational Command';
        case 'staff':
        default:
            return 'Staff Operational Workspace';
    }
});

const viewSubtitle = computed(() => {
    switch (currentView.value) {
        case 'admin':
            return 'Infrastructure health, user provisioning, security audit trail, and global taxonomies';
        case 'department_head':
            return 'Strategic progress, section performance benchmarks, macro timeline, and audit reporting';
        case 'supervisor':
            return 'Team workload capacity, bottleneck escalations, pending approvals, and milestone delivery';
        case 'staff':
        default:
            return 'Priority task queue, personal action items, active timer, and collaborative updates';
    }
});

// Role-adaptive sub-navigation anchors
const adaptiveNavItems = computed(() => {
    switch (currentView.value) {
        case 'admin':
            return [
                { label: 'System Health', href: '#system-health' },
                { label: 'User Directory', href: '#user-management' },
                { label: 'Audit Logs', href: '#audit-logs' },
                { label: 'System Config', href: '#system-configuration' },
            ];
        case 'department_head':
            return [
                { label: 'Executive Summary', href: '#summary' },
                { label: 'Section Performance', href: '#section-performance' },
                { label: 'Roadmap & Gantt', href: '#department-roadmap' },
            ];
        case 'supervisor':
            return [
                { label: 'Escalations', href: '#escalation-alerts' },
                { label: 'Team Workload', href: '#team-workload' },
                { label: 'Pending Approvals', href: '#pending-approvals' },
                { label: 'Team Milestones', href: '#team-milestones' },
            ];
        case 'staff':
        default:
            return [
                { label: 'Action Items', href: '#action-items' },
                { label: 'Active Timer', href: '#active-timer' },
                { label: 'Recent Activity', href: '#recent-activity' },
                { label: 'Board Shortcuts', href: '#shortcuts' },
            ];
    }
});

const scrollToAnchor = (href) => {
    if (!href.startsWith('#')) return;
    const target = document.querySelector(href);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};
</script>

<template>
    <AppLayout :title="viewTitle">
        <!-- Page Header -->
        <template #header>
            <div class="space-y-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div
                            class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-base shadow-xs shrink-0"
                            :class="currentView === 'admin'
                                ? 'bg-slate-900 text-white'
                                : currentView === 'department_head'
                                    ? 'bg-blue-600 text-white'
                                    : currentView === 'supervisor'
                                        ? 'bg-amber-600 text-white'
                                        : 'bg-[#0D9488] text-white'"
                        >
                            <svg v-if="currentView === 'admin'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            <svg v-else-if="currentView === 'department_head'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                            </svg>
                            <svg v-else-if="currentView === 'supervisor'" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                            <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="font-extrabold text-xl sm:text-2xl text-slate-800 leading-tight">
                                    {{ viewTitle }}
                                </h2>
                                <span
                                    class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border shadow-2xs"
                                    :class="currentView === 'admin'
                                        ? 'bg-purple-50 text-purple-700 border-purple-200'
                                        : currentView === 'department_head'
                                            ? 'bg-blue-50 text-blue-700 border-blue-200'
                                            : currentView === 'supervisor'
                                                ? 'bg-amber-50 text-amber-700 border-amber-200'
                                                : 'bg-teal-50 text-[#0D9488] border-teal-200'"
                                >
                                    {{ currentView.replace('_', ' ') }}
                                </span>
                            </div>
                            <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
                                {{ viewSubtitle }}
                            </p>
                        </div>
                    </div>

                    <!-- Right Controls: Role Switcher & New Board Action -->
                    <div class="flex flex-wrap items-center gap-3">
                        <RoleSwitcher />

                        <Link
                            v-if="isDeptHead || currentView === 'admin' || currentView === 'department_head'"
                            :href="route('task-boards.create')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#0D9488] border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-wider hover:bg-[#0f766e] active:bg-[#115e59] transition shadow-xs hover:shadow"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            New Board
                        </Link>
                    </div>
                </div>

                <!-- Adaptive Sub-Navigation Bar -->
                <div class="pt-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-slate-400 font-semibold text-[11px] uppercase tracking-wider mr-1">Quick Jump:</span>
                        <a
                            v-for="item in adaptiveNavItems"
                            :key="item.label"
                            :href="item.href"
                            @click.prevent="scrollToAnchor(item.href)"
                            class="px-2.5 py-1 rounded-md font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 hover:text-slate-800 border border-slate-200/80 transition"
                        >
                            {{ item.label }}
                        </a>
                    </div>

                    <div class="text-[11px] text-slate-400 hidden lg:flex items-center gap-2">
                        <span>Authenticated as: <strong class="text-slate-700 font-semibold">{{ userInfo?.name || $page.props.auth?.user?.name }}</strong></span>
                        <span>&bull;</span>
                        <span class="text-[#0D9488] font-medium">{{ userInfo?.functional_roles_string || memberRole }}</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Main Dashboard Workspace Content -->
        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- 1. Staff Dashboard View -->
                <StaffDashboard
                    v-if="currentView === 'staff'"
                    :data="viewData"
                    :user-info="userInfo"
                />

                <!-- 2. Supervisor Dashboard View -->
                <SupervisorDashboard
                    v-else-if="currentView === 'supervisor'"
                    :data="viewData"
                    :user-info="userInfo"
                />

                <!-- 3. Department Head Dashboard View -->
                <DepartmentHeadDashboard
                    v-else-if="currentView === 'department_head'"
                    :data="viewData"
                    :user-info="userInfo"
                />

                <!-- 4. Administrator Dashboard View -->
                <AdminDashboard
                    v-else-if="currentView === 'admin'"
                    :data="viewData"
                    :user-info="userInfo"
                />

                <!-- Fallback / Unrecognized View -->
                <div v-else class="bg-white p-12 text-center rounded-xl border border-slate-200 shadow-xs">
                    <h3 class="text-base font-bold text-slate-800">Perspective Not Found</h3>
                    <p class="text-xs text-slate-500 mt-1">The requested operational view is not recognized.</p>
                    <Link
                        :href="route('dashboard', { view: 'staff' })"
                        class="mt-4 inline-flex items-center px-4 py-2 bg-[#0D9488] text-white rounded-lg text-xs font-semibold"
                    >
                        Return to Staff Workspace
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
