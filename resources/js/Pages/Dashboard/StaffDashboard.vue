<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
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

// 1. Task Filtering & View Mode Toggle
const activeFilter = ref('all'); // 'all', 'urgent', 'due_today', 'upcoming'
const viewMode = ref('list'); // 'list' | 'kanban'
const searchQuery = ref('');

const actionItems = computed(() => props.data?.action_items || []);
const quickMetrics = computed(() => props.data?.quick_metrics || {
    completed_today: 0,
    completed_this_week: 0,
    pending_tasks: 0,
    overdue_tasks: 0,
});
const recentActivity = computed(() => props.data?.recent_activity || []);
const shortcuts = computed(() => props.data?.task_board_shortcuts || []);

const filteredActionItems = computed(() => {
    let items = actionItems.value;

    if (activeFilter.value === 'urgent') {
        items = items.filter(item => item.priority === 'urgent' || item.is_overdue);
    } else if (activeFilter.value === 'due_today') {
        items = items.filter(item => item.is_due_today);
    } else if (activeFilter.value === 'upcoming') {
        items = items.filter(item => item.is_upcoming);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(item => 
            item.name.toLowerCase().includes(q) ||
            (item.task_board_name && item.task_board_name.toLowerCase().includes(q)) ||
            (item.parent_task_name && item.parent_task_name.toLowerCase().includes(q))
        );
    }

    return items;
});

// Kanban columns grouping
const kanbanColumns = computed(() => {
    return [
        { key: 'pending', title: 'Pending', items: filteredActionItems.value.filter(i => i.status === 'pending') },
        { key: 'in_progress', title: 'In Progress', items: filteredActionItems.value.filter(i => i.status === 'in_progress') },
        { key: 'submitted', title: 'Submitted', items: filteredActionItems.value.filter(i => i.status === 'submitted') },
        { key: 'completed', title: 'Completed', items: filteredActionItems.value.filter(i => i.status === 'completed') },
    ];
});

// Status update
const updatingTaskId = ref(null);
const updateTaskStatus = (subtask, newStatus) => {
    updatingTaskId.value = subtask.id;
    router.put(route('subtasks.update-status', subtask.id), {
        status: newStatus,
    }, {
        preserveScroll: true,
        onFinish: () => {
            updatingTaskId.value = null;
        }
    });
};

// Priority badge style
const getPriorityBadgeClass = (priority, isOverdue) => {
    if (isOverdue || priority === 'urgent') {
        return 'bg-rose-50 text-rose-700 border-rose-200';
    }
    switch (priority) {
        case 'high':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'medium':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'low':
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'submitted':
            return 'bg-teal-50 text-[#0D9488] border-teal-200';
        case 'in_progress':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'pending':
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

// 2. Active Timer / Time Tracker
const activeTimerTask = ref(null);
const timerSeconds = ref(0);
const isTimerRunning = ref(false);
let timerInterval = null;

const timerFormatted = computed(() => {
    const hrs = Math.floor(timerSeconds.value / 3600);
    const mins = Math.floor((timerSeconds.value % 3600) / 60);
    const secs = timerSeconds.value % 60;
    return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const startTimer = (task = null) => {
    if (task) {
        activeTimerTask.value = task;
    } else if (!activeTimerTask.value && actionItems.value.length > 0) {
        activeTimerTask.value = actionItems.value[0];
    }
    isTimerRunning.value = true;
};

const pauseTimer = () => {
    isTimerRunning.value = false;
};

const stopTimer = () => {
    isTimerRunning.value = false;
    timerSeconds.value = 0;
    activeTimerTask.value = null;
    localStorage.removeItem('project_tracker_active_timer');
};

onMounted(() => {
    // Restore timer from local storage if available
    try {
        const saved = localStorage.getItem('project_tracker_active_timer');
        if (saved) {
            const data = JSON.parse(saved);
            activeTimerTask.value = data.task;
            timerSeconds.value = data.seconds || 0;
            if (data.isRunning) {
                const elapsedSince = Math.floor((Date.now() - data.savedAt) / 1000);
                timerSeconds.value += Math.max(0, elapsedSince);
                isTimerRunning.value = true;
            }
        }
    } catch (e) {
        console.error('Failed to restore timer state:', e);
    }

    timerInterval = setInterval(() => {
        if (isTimerRunning.value) {
            timerSeconds.value++;
            localStorage.setItem('project_tracker_active_timer', JSON.stringify({
                task: activeTimerTask.value,
                seconds: timerSeconds.value,
                isRunning: true,
                savedAt: Date.now(),
            }));
        }
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

// Read state for activity notifications
const readActivityIds = ref(new Set());
const markActivityAsRead = (id) => {
    readActivityIds.value.add(id);
};
</script>

<template>
    <div class="space-y-8">
        <!-- Top Metrics & Active Timer Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Metric 1: Tasks Completed Today -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-slate-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completed Today</span>
                    <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-bold text-slate-800">{{ quickMetrics.completed_today }}</span>
                    <span class="text-xs text-slate-400 font-medium">This week: {{ quickMetrics.completed_this_week }}</span>
                </div>
            </div>

            <!-- Metric 2: Pending Tasks -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-slate-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pending Action</span>
                    <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-bold text-slate-800">{{ quickMetrics.pending_tasks }}</span>
                    <span class="text-xs text-slate-400 font-medium">Assigned items</span>
                </div>
            </div>

            <!-- Metric 3: Overdue Tasks -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-slate-300 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Overdue Items</span>
                    <div class="p-2 rounded-lg" :class="quickMetrics.overdue_tasks > 0 ? 'bg-rose-50 text-rose-600' : 'bg-slate-50 text-slate-400'">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-bold" :class="quickMetrics.overdue_tasks > 0 ? 'text-rose-600' : 'text-slate-800'">
                        {{ quickMetrics.overdue_tasks }}
                    </span>
                    <span class="text-xs font-medium" :class="quickMetrics.overdue_tasks > 0 ? 'text-rose-500' : 'text-slate-400'">
                        {{ quickMetrics.overdue_tasks > 0 ? 'Needs resolution' : 'All on track' }}
                    </span>
                </div>
            </div>

            <!-- Widget 2.2: Active Timer / Time Tracker -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs hover:border-slate-300 transition flex flex-col justify-between" id="active-timer">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" :class="isTimerRunning ? 'bg-[#0D9488] animate-ping' : 'bg-slate-300'"></span>
                        Active Timer
                    </span>
                    <span class="font-mono text-sm font-bold text-slate-800 tracking-wider">
                        {{ timerFormatted }}
                    </span>
                </div>

                <div class="mt-2.5">
                    <div v-if="activeTimerTask" class="text-xs text-slate-700 truncate font-medium flex items-center gap-1" :title="activeTimerTask.name">
                        <span class="text-[#0D9488]">●</span> {{ activeTimerTask.name }}
                    </div>
                    <div v-else class="text-xs text-slate-400 italic">
                        No active task timer
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button
                        v-if="!isTimerRunning"
                        type="button"
                        @click="startTimer()"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[#0D9488] text-white hover:bg-[#0f766e] transition shadow-xs"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                        </svg>
                        Start Timer
                    </button>
                    <template v-else>
                        <button
                            type="button"
                            @click="pauseTimer()"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                            </svg>
                            Pause
                        </button>
                        <button
                            type="button"
                            @click="stopTimer()"
                            class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 transition"
                            title="Stop & Reset"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 017.5 5.25h9a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25h-9a2.25 2.25 0 01-2.25-2.25v-9z" />
                            </svg>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- 2.1 My Action Items / Priority Task Queue -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden" id="action-items">
            <!-- Header bar with filtering & view mode -->
            <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-1.5 h-6 bg-[#0D9488] rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">My Action Items</h3>
                        <p class="text-xs text-slate-400">Directly assigned tasks requiring your focus</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Filter my tasks..."
                            class="text-xs rounded-lg border-slate-200 pl-8 pr-3 py-1.5 focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488] w-40 sm:w-56"
                        />
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                    </div>

                    <!-- Category Pills -->
                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50 text-xs">
                        <button
                            type="button"
                            @click="activeFilter = 'all'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="activeFilter === 'all' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            All ({{ actionItems.length }})
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'urgent'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="activeFilter === 'urgent' ? 'bg-white text-rose-700 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Urgent / Overdue
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'due_today'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="activeFilter === 'due_today' ? 'bg-white text-amber-700 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Due Today
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'upcoming'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="activeFilter === 'upcoming' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Upcoming
                        </button>
                    </div>

                    <!-- List / Kanban Toggle -->
                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                        <button
                            type="button"
                            @click="viewMode = 'list'"
                            class="p-1 rounded-md transition"
                            :class="viewMode === 'list' ? 'bg-white text-[#0D9488] shadow-xs' : 'text-slate-400 hover:text-slate-600'"
                            title="List View"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="viewMode = 'kanban'"
                            class="p-1 rounded-md transition"
                            :class="viewMode === 'kanban' ? 'bg-white text-[#0D9488] shadow-xs' : 'text-slate-400 hover:text-slate-600'"
                            title="Kanban View"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15m-10.875 0h15.75c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625v12.75c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Area: Empty State -->
            <div v-if="filteredActionItems.length === 0" class="p-12 text-center">
                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h4 class="font-bold text-slate-700 text-sm">No action items found</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                    {{ activeFilter === 'urgent' ? "No urgent or overdue tasks. You're all caught up!" : "There are currently no tasks matching your filter." }}
                </p>
            </div>

            <!-- LIST VIEW -->
            <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 max-h-[550px] overflow-y-auto">
                <div
                    v-for="item in filteredActionItems"
                    :key="item.id"
                    class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                    :class="{ 'bg-rose-50/30': item.is_overdue }"
                >
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Priority badge -->
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border" :class="getPriorityBadgeClass(item.priority, item.is_overdue)">
                                {{ item.is_overdue ? 'OVERDUE' : item.priority }}
                            </span>

                            <!-- Blocked badge -->
                            <span v-if="item.is_blocked" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 border border-rose-200 flex items-center gap-1">
                                <span>⚠</span> Blocked: {{ item.blocked_reason || 'Waiting' }}
                            </span>

                            <!-- Title -->
                            <h4 class="font-semibold text-slate-800 text-sm hover:text-[#0D9488] transition truncate">
                                <Link v-if="item.task_board_id" :href="route('task-boards.show', item.task_board_id)">{{ item.name }}</Link>
                                <span v-else>{{ item.name }}</span>
                            </h4>
                        </div>

                        <!-- Meta line -->
                        <div class="flex flex-wrap items-center gap-y-1 gap-x-2 text-xs text-slate-400">
                            <span class="font-medium text-slate-600 truncate max-w-[200px]" :title="item.task_board_name">
                                {{ item.task_board_name || 'Unassigned Board' }}
                            </span>
                            <span>&bull;</span>
                            <span class="text-slate-500">{{ item.workflow_name || 'Default Workflow' }}</span>
                            <span>&bull;</span>
                            <span class="text-slate-400 italic truncate max-w-[180px]">{{ item.parent_task_name }}</span>
                        </div>

                        <!-- Due date / duration info -->
                        <div class="flex flex-wrap items-center gap-x-3 text-xs">
                            <span class="text-slate-500">Duration: <strong class="text-slate-700">{{ item.duration }}d</strong></span>
                            <span v-if="item.due_date" :class="item.is_overdue ? 'text-rose-600 font-semibold' : 'text-slate-500'">
                                Due: {{ item.due_date }}
                            </span>
                            <span v-if="item.deliverables" class="text-slate-400 truncate max-w-[240px]" :title="item.deliverables">
                                Deliverable: {{ item.deliverables }}
                            </span>
                        </div>
                    </div>

                    <!-- Right Controls: Status updater & Timer quick start -->
                    <div class="flex items-center gap-2.5 shrink-0">
                        <!-- Timer Quick Trigger -->
                        <button
                            type="button"
                            @click="startTimer(item)"
                            class="p-1.5 rounded-lg border border-slate-200 text-slate-500 hover:text-[#0D9488] hover:border-[#0D9488] hover:bg-[#F0FDFA] transition"
                            :class="{ 'border-[#0D9488] text-[#0D9488] bg-[#F0FDFA]': activeTimerTask?.id === item.id }"
                            :title="activeTimerTask?.id === item.id ? 'Currently Tracking' : 'Track time for this task'"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>

                        <!-- Quick status selector -->
                        <select
                            :value="item.status"
                            @change="updateTaskStatus(item, $event.target.value)"
                            :disabled="updatingTaskId === item.id"
                            class="text-xs rounded-lg border py-1.5 pl-2.5 pr-8 font-semibold focus:outline-none focus:ring-1 focus:ring-[#0D9488] transition cursor-pointer"
                            :class="getStatusBadgeClass(item.status)"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                            <option value="completed">Completed</option>
                        </select>

                        <!-- Open Board Link -->
                        <Link
                            v-if="item.task_board_id"
                            :href="route('task-boards.show', item.task_board_id)"
                            class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition"
                            title="Open in Board"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- KANBAN VIEW -->
            <div v-else class="p-5 overflow-x-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 min-w-[760px]">
                    <div
                        v-for="col in kanbanColumns"
                        :key="col.key"
                        class="bg-slate-50 rounded-xl p-3 border border-slate-200/60 flex flex-col"
                    >
                        <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-200/60">
                            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">{{ col.title }}</span>
                            <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-white text-slate-600 border border-slate-200">
                                {{ col.items.length }}
                            </span>
                        </div>

                        <div class="space-y-2.5 flex-1 min-h-[220px]">
                            <div v-if="col.items.length === 0" class="h-24 flex items-center justify-center text-[11px] text-slate-400 italic">
                                No items in this stage
                            </div>
                            <div
                                v-for="task in col.items"
                                :key="task.id"
                                class="bg-white p-3 rounded-lg border border-slate-200/80 shadow-xs hover:border-[#0D9488]/60 transition space-y-2"
                            >
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded border" :class="getPriorityBadgeClass(task.priority, task.is_overdue)">
                                        {{ task.is_overdue ? 'Overdue' : task.priority }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">{{ task.duration }}d</span>
                                </div>
                                <h5 class="text-xs font-semibold text-slate-800 line-clamp-2">
                                    {{ task.name }}
                                </h5>
                                <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[10px] text-slate-400">
                                    <span class="truncate max-w-[120px]">{{ task.task_board_name }}</span>
                                    <Link
                                        v-if="task.task_board_id"
                                        :href="route('task-boards.show', task.task_board_id)"
                                        class="text-[#0D9488] font-semibold hover:underline"
                                    >
                                        View
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lower Grid: Recent Activity & Collaboration Shortcuts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- 2.3 Recent Activity / Mentions -->
            <div class="lg:col-span-2 bg-white border border-slate-200/80 rounded-xl shadow-xs p-5" id="recent-activity">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-5 bg-blue-500 rounded-full"></span>
                        <h4 class="font-bold text-slate-800 text-sm">Recent Activity & Collaboration</h4>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">{{ recentActivity.length }} events</span>
                </div>

                <div v-if="recentActivity.length === 0" class="py-10 text-center text-slate-400 text-xs">
                    No recent activity records or mentions.
                </div>

                <div v-else class="space-y-3.5 max-h-[360px] overflow-y-auto pr-1">
                    <div
                        v-for="act in recentActivity"
                        :key="act.id"
                        class="p-3 rounded-lg border border-slate-100 bg-slate-50/40 flex items-start gap-3 hover:bg-slate-50 transition"
                        :class="{ 'opacity-60': readActivityIds.has(act.id) }"
                    >
                        <div class="h-8 w-8 rounded-full bg-[#F0FDFA] text-[#0D9488] flex items-center justify-center font-bold text-xs shrink-0">
                            {{ getInitials(act.actor_name) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-slate-800">{{ act.actor_name }}</span>
                                <span class="text-[10px] text-slate-400">{{ act.created_at }}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5 line-clamp-2">{{ act.text }}</p>
                            <div class="flex items-center justify-between mt-2 pt-1.5 border-t border-slate-200/40 text-[11px]">
                                <span class="text-slate-400 truncate max-w-[200px]">Board: {{ act.task_board_name || 'General' }}</span>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="!readActivityIds.has(act.id)"
                                        type="button"
                                        @click="markActivityAsRead(act.id)"
                                        class="text-[10px] text-slate-400 hover:text-slate-600 underline"
                                    >
                                        Mark as read
                                    </button>
                                    <Link
                                        v-if="act.task_board_id"
                                        :href="route('task-boards.show', act.task_board_id)"
                                        class="text-[#0D9488] hover:text-[#0f766e] font-semibold"
                                    >
                                        Open &rarr;
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2.5 Collaboration Shortcuts / My Boards -->
            <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5 flex flex-col justify-between" id="shortcuts">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-5 bg-[#0D9488] rounded-full"></span>
                            <h4 class="font-bold text-slate-800 text-sm">Task Board Shortcuts</h4>
                        </div>
                        <Link :href="route('task-boards.index')" class="text-xs text-[#0D9488] font-semibold hover:underline">
                            View All
                        </Link>
                    </div>

                    <div v-if="shortcuts.length === 0" class="py-10 text-center text-slate-400 text-xs">
                        No active Task Boards associated with your profile.
                    </div>

                    <div v-else class="space-y-3">
                        <Link
                            v-for="board in shortcuts.slice(0, 4)"
                            :key="board.id"
                            :href="route('task-boards.show', board.id)"
                            class="block p-3 rounded-lg border border-slate-100 hover:border-[#0D9488]/40 hover:bg-[#F0FDFA]/30 transition group"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <h5 class="text-xs font-bold text-slate-800 group-hover:text-[#0D9488] transition truncate">
                                    {{ board.name }}
                                </h5>
                                <span class="text-[10px] font-bold text-slate-500 uppercase">{{ board.progress }}%</span>
                            </div>
                            <!-- Mini progress bar -->
                            <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                                <div class="bg-[#0D9488] h-full rounded-full transition-all" :style="{ width: `${board.progress}%` }"></div>
                            </div>
                            <div class="flex items-center justify-between mt-2 text-[10px] text-slate-400">
                                <span>{{ board.section_name || 'Section' }}</span>
                                <span>{{ board.completed_tasks }} / {{ board.total_tasks }} done</span>
                            </div>
                        </Link>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500 flex items-center justify-between">
                    <span>Active workspace:</span>
                    <span class="font-semibold text-slate-700">{{ userInfo?.sections?.[0] || 'General Staff' }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
