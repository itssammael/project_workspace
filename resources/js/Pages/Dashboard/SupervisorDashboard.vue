<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { getInitials } from '@/Utils/helpers';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
    userInfo: {
        type: Object,
        default: () => ({}),
    },
});

const teamWorkload = computed(() => props.data?.team_workload || []);
const pendingApprovals = computed(() => props.data?.pending_approvals || []);
const teamMilestones = computed(() => props.data?.team_milestones || []);
const escalationAlerts = computed(() => props.data?.escalation_alerts || []);
const supervisedSections = computed(() => props.data?.supervised_sections || []);

// State for active workload filter
const workloadFilter = ref('all'); // 'all', 'overloaded', 'high_load', 'available'

const filteredWorkload = computed(() => {
    if (workloadFilter.value === 'overloaded') {
        return teamWorkload.value.filter(m => m.workload_state === 'Overloaded');
    } else if (workloadFilter.value === 'high_load') {
        return teamWorkload.value.filter(m => m.workload_state === 'High Load' || m.workload_state === 'Overloaded');
    } else if (workloadFilter.value === 'available') {
        return teamWorkload.value.filter(m => m.workload_state === 'Available');
    }
    return teamWorkload.value;
});

// Capacity color helper
const getLoadBarClass = (state) => {
    switch (state) {
        case 'Overloaded':
            return 'bg-rose-500';
        case 'High Load':
            return 'bg-amber-500';
        case 'Normal':
            return 'bg-[#0D9488]';
        case 'Available':
        default:
            return 'bg-emerald-500';
    }
};

const getWorkloadBadgeClass = (state) => {
    switch (state) {
        case 'Overloaded':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'High Load':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'Normal':
            return 'bg-teal-50 text-[#0D9488] border-teal-200';
        case 'Available':
        default:
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
};

// Approval processing
const processingApprovalId = ref(null);

const handleApproval = (item, newStatus) => {
    processingApprovalId.value = item.id;
    router.put(route('subtasks.update-status', item.id), {
        status: newStatus,
    }, {
        preserveScroll: true,
        onFinish: () => {
            processingApprovalId.value = null;
        }
    });
};
</script>

<template>
    <div class="space-y-8">
        <!-- 3.4 Escalation Alert Box (Prominent top banner when there are issues) -->
        <div v-if="escalationAlerts.length > 0" class="bg-rose-50/70 border border-rose-200/80 rounded-xl p-5 shadow-xs" id="escalation-alerts">
            <div class="flex items-center justify-between pb-3 border-b border-rose-200/60 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-600"></span>
                    </span>
                    <h3 class="font-bold text-rose-900 text-sm flex items-center gap-2">
                        Escalation Alerts & Bottlenecks
                        <span class="bg-rose-200 text-rose-900 text-[10px] font-extrabold px-2 py-0.5 rounded-full">
                            {{ escalationAlerts.length }} requiring attention
                        </span>
                    </h3>
                </div>
                <span class="text-xs text-rose-700 font-medium hidden sm:inline">Requires supervisor intervention</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <div
                    v-for="alert in escalationAlerts"
                    :key="alert.id"
                    class="bg-white p-3.5 rounded-lg border border-rose-200/80 shadow-xs flex flex-col justify-between"
                >
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded"
                                :class="alert.is_blocked ? 'bg-rose-100 text-rose-800 font-extrabold' : 'bg-amber-100 text-amber-800'">
                                {{ alert.is_blocked ? 'BLOCKED' : `${alert.days_overdue}d OVERDUE` }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium truncate max-w-[120px]">
                                {{ alert.task_board_name }}
                            </span>
                        </div>

                        <h5 class="text-xs font-bold text-slate-800 line-clamp-2">
                            {{ alert.name }}
                        </h5>

                        <p class="text-[11px] text-rose-700 bg-rose-50 p-1.5 rounded border border-rose-100 leading-snug">
                            <strong>Reason:</strong> {{ alert.blocked_reason }}
                        </p>
                    </div>

                    <div class="mt-3 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <div class="flex items-center gap-1.5">
                            <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[9px] font-bold text-slate-700">
                                {{ getInitials(alert.owner_name) }}
                            </div>
                            <span class="text-slate-600 font-medium truncate max-w-[110px]">{{ alert.owner_name }}</span>
                        </div>
                        <Link
                            v-if="alert.task_board_id"
                            :href="route('task-boards.show', alert.task_board_id)"
                            class="text-rose-700 hover:text-rose-900 font-bold hover:underline"
                        >
                            View Task &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="bg-emerald-50/60 border border-emerald-200/70 rounded-xl p-4 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-1 rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-emerald-900">No Escalations Detected</h4>
                    <p class="text-[11px] text-emerald-700">Your team currently has no stalled, blocked, or critical overdue items.</p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-emerald-800 bg-white px-2.5 py-1 rounded-md border border-emerald-200 shadow-xs hidden sm:inline">
                Section Flow Optimal
            </span>
        </div>

        <!-- 3.1 Team(Section) Workload Matrix -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5" id="team-workload">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-1.5 h-6 bg-[#0D9488] rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">Team(Section) Workload Matrix</h3>
                        <p class="text-xs text-slate-400">
                            Monitor section capacity, reallocate tasks, and prevent overload imbalance
                        </p>
                    </div>
                </div>

                <!-- Workload state filters -->
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50 text-xs">
                    <button
                        type="button"
                        @click="workloadFilter = 'all'"
                        class="px-2.5 py-1 rounded-md font-medium transition"
                        :class="workloadFilter === 'all' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    >
                        All ({{ teamWorkload.length }})
                    </button>
                    <button
                        type="button"
                        @click="workloadFilter = 'overloaded'"
                        class="px-2.5 py-1 rounded-md font-medium transition"
                        :class="workloadFilter === 'overloaded' ? 'bg-white text-rose-700 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    >
                        Overloaded
                    </button>
                    <button
                        type="button"
                        @click="workloadFilter = 'high_load'"
                        class="px-2.5 py-1 rounded-md font-medium transition"
                        :class="workloadFilter === 'high_load' ? 'bg-white text-amber-700 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    >
                        High Load
                    </button>
                    <button
                        type="button"
                        @click="workloadFilter = 'available'"
                        class="px-2.5 py-1 rounded-md font-medium transition"
                        :class="workloadFilter === 'available' ? 'bg-white text-emerald-700 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                    >
                        Available
                    </button>
                </div>
            </div>

            <!-- Empty Workload State -->
            <div v-if="filteredWorkload.length === 0" class="py-12 text-center text-slate-400 text-xs">
                No team members match the selected workload filter.
            </div>

            <!-- Team Workload Cards Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-5">
                <div
                    v-for="member in filteredWorkload"
                    :key="member.id"
                    class="p-4 rounded-xl border transition-all duration-200 flex flex-col justify-between"
                    :class="member.workload_state === 'Overloaded' 
                        ? 'border-rose-200 bg-rose-50/20' 
                        : member.workload_state === 'High Load' 
                            ? 'border-amber-200 bg-amber-50/20' 
                            : 'border-slate-200/80 bg-white hover:border-slate-300'"
                >
                    <div>
                        <!-- Member Header -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-700 shrink-0">
                                    {{ getInitials(member.name) }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-slate-800 text-xs truncate" :title="member.name">
                                        {{ member.name }}
                                    </h4>
                                    <p class="text-[11px] text-slate-400 truncate">{{ member.role }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border shrink-0" :class="getWorkloadBadgeClass(member.workload_state)">
                                {{ member.workload_state }}
                            </span>
                        </div>

                        <!-- Stats Row -->
                        <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                            <div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
                                <span class="block text-xs font-bold text-slate-800">{{ member.active_task_count }}</span>
                                <span class="text-[10px] text-slate-400 font-medium">Active</span>
                            </div>
                            <div class="p-2 rounded-lg border" :class="member.urgent_task_count > 0 ? 'bg-rose-50 text-rose-800 border-rose-200' : 'bg-slate-50 border-slate-100 text-slate-400'">
                                <span class="block text-xs font-bold">{{ member.urgent_task_count }}</span>
                                <span class="text-[10px] font-medium">Urgent</span>
                            </div>
                            <div class="p-2 rounded-lg border" :class="member.overdue_task_count > 0 ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-50 border-slate-100 text-slate-400'">
                                <span class="block text-xs font-bold">{{ member.overdue_task_count }}</span>
                                <span class="text-[10px] font-medium">Overdue</span>
                            </div>
                        </div>

                        <!-- Capacity Bar -->
                        <div class="mt-4 space-y-1.5">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-500 font-medium">Capacity Utilized</span>
                                <span class="font-bold text-slate-800">{{ member.load_percentage }}%</span>
                            </div>
                            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="getLoadBarClass(member.workload_state)"
                                    :style="{ width: `${Math.min(100, member.load_percentage)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100 text-[10px] text-slate-400 flex items-center justify-between">
                        <span class="truncate max-w-[150px]">{{ member.sections?.join(', ') || 'Team Section' }}</span>
                        <span>{{ member.completed_task_count }} completed</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lower Grid: Pending Approvals & Milestones -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 3.2 Pending Approvals / Reviews -->
            <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5 flex flex-col justify-between" id="pending-approvals">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-1.5 h-6 bg-amber-500 rounded-full"></span>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Pending Approvals & Verification</h3>
                                <p class="text-xs text-slate-400">Tasks submitted by team members requiring sign-off</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
                            {{ pendingApprovals.length }} pending
                        </span>
                    </div>

                    <div v-if="pendingApprovals.length === 0" class="py-12 text-center text-slate-400 text-xs">
                        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-400 mx-auto flex items-center justify-center mb-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        There are currently no items requiring your review or sign-off.
                    </div>

                    <div v-else class="divide-y divide-slate-100 max-h-[460px] overflow-y-auto pr-1">
                        <div
                            v-for="item in pendingApprovals"
                            :key="item.id"
                            class="py-3.5 space-y-2.5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-800">{{ item.name }}</h5>
                                    <p class="text-[11px] text-slate-400">
                                        Board: <span class="font-medium text-slate-600">{{ item.task_board_name }}</span> &bull; 
                                        Phase: <span class="text-slate-500">{{ item.parent_task_name }}</span>
                                    </p>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded border bg-amber-50 text-amber-700 border-amber-200 shrink-0">
                                    Submitted
                                </span>
                            </div>

                            <p v-if="item.deliverables" class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded border border-slate-100">
                                <strong>Deliverable:</strong> {{ item.deliverables }}
                            </p>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <div class="flex items-center gap-1.5 text-slate-500 text-[11px]">
                                    <span class="font-medium text-slate-700">{{ item.submitted_by }}</span>
                                    <span>&bull; {{ item.submitted_date }}</span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="handleApproval(item, 'in_progress')"
                                        :disabled="processingApprovalId === item.id"
                                        class="px-2.5 py-1 rounded text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 border border-slate-200 transition"
                                    >
                                        Revise
                                    </button>
                                    <button
                                        type="button"
                                        @click="handleApproval(item, 'completed')"
                                        :disabled="processingApprovalId === item.id"
                                        class="px-3 py-1 rounded text-xs font-semibold bg-[#0D9488] text-white hover:bg-[#0f766e] transition shadow-xs"
                                    >
                                        Approve
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400 flex items-center justify-between">
                    <span>Approval authority:</span>
                    <span class="font-semibold text-slate-700">Supervisor / Section Head</span>
                </div>
            </div>

            <!-- 3.3 Team(Section) Milestone Tracker -->
            <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5 flex flex-col justify-between" id="team-milestones">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Team Milestones & Boards</h3>
                                <p class="text-xs text-slate-400">Progress against deliverables across active boards</p>
                            </div>
                        </div>
                        <Link :href="route('task-boards.index')" class="text-xs text-[#0D9488] font-semibold hover:underline">
                            View Boards
                        </Link>
                    </div>

                    <div v-if="teamMilestones.length === 0" class="py-12 text-center text-slate-400 text-xs">
                        No active Task Boards associated with your supervised sections.
                    </div>

                    <div v-else class="space-y-4 max-h-[460px] overflow-y-auto pr-1">
                        <div
                            v-for="board in teamMilestones"
                            :key="board.id"
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-slate-300 transition"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-800 hover:text-[#0D9488] transition">
                                        <Link :href="route('task-boards.show', board.id)">{{ board.name }}</Link>
                                    </h5>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        Phase: <span class="font-semibold text-slate-600">{{ board.current_phase }}</span> &bull; 
                                        Section: <span class="text-slate-500">{{ board.section_name }}</span>
                                    </p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border shrink-0"
                                    :class="board.status === 'active' || board.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                    {{ board.status }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mt-3 space-y-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-slate-500">Progress</span>
                                    <span class="font-bold text-slate-800">{{ board.progress }}%</span>
                                </div>
                                <div class="w-full bg-slate-200/80 h-2 rounded-full overflow-hidden">
                                    <div
                                        class="bg-[#0D9488] h-full rounded-full transition-all duration-500"
                                        :style="{ width: `${board.progress}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-3 text-[11px] text-slate-400">
                                <span>{{ board.completed_tasks }} / {{ board.total_tasks }} tasks completed</span>
                                <span :class="board.is_overdue ? 'text-rose-600 font-bold' : 'text-slate-500'">
                                    {{ board.end_date }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400 flex items-center justify-between">
                    <span>Supervised sections count:</span>
                    <span class="font-semibold text-slate-700">{{ supervisedSections.length }} sections</span>
                </div>
            </div>
        </div>
    </div>
</template>
