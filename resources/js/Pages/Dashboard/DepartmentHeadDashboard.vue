<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';

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

const summary = computed(() => props.data?.executive_summary || {
    active_task_boards: 0,
    completed_task_boards: 0,
    total_task_boards: 0,
    department_completion_rate: 0,
    timeline_health: { on_track: 0, at_risk: 0, delayed: 0 },
    budget_health: null,
});

const sectionPerformance = computed(() => props.data?.section_performance || []);
const roadmap = computed(() => props.data?.department_roadmap || []);
const departments = computed(() => props.data?.departments || []);

// Roadmap time scale and status filter
const timeScale = ref('month'); // 'week' | 'month' | 'quarter'
const roadmapStatusFilter = ref('all'); // 'all' | 'active' | 'completed' | 'delayed'

const filteredRoadmap = computed(() => {
    let items = roadmap.value;
    if (roadmapStatusFilter.value === 'active') {
        items = items.filter(b => b.status === 'active');
    } else if (roadmapStatusFilter.value === 'completed') {
        items = items.filter(b => b.status === 'completed');
    } else if (roadmapStatusFilter.value === 'delayed') {
        items = items.filter(b => b.is_delayed);
    }
    return items;
});

// Report Generator Modal State
const isReportModalOpen = ref(false);
const selectedReportType = ref('department_summary');
const isExporting = ref(false);

const reportOptions = [
    { id: 'department_summary', name: 'Department Summary', desc: 'High-level executive metrics, completion rates, and timeline health' },
    { id: 'section_performance', name: 'Section Performance Breakdown', desc: 'Factual task completion, active workloads, and section efficiency' },
    { id: 'project_progress', name: 'Task Board Progress Report', desc: 'Detailed milestone status, deadlines, and delivery percentages' },
    { id: 'milestone_status', name: 'Risk & Delay Analysis', desc: 'Critical milestones, overdue flags, and risk assessments' },
];

const downloadReport = (format = 'csv') => {
    isExporting.value = true;
    const url = route('dashboard.export-report', {
        type: selectedReportType.value,
        format: format,
    });
    window.location.href = url;
    setTimeout(() => {
        isExporting.value = false;
        isReportModalOpen.value = false;
    }, 1000);
};

const triggerPrintReport = () => {
    window.print();
};
</script>

<template>
    <div class="space-y-8">
        <!-- 4.1 Executive Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Active Boards -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Boards</span>
                    <div class="p-2 rounded-lg bg-teal-50 text-[#0D9488]">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-slate-800">{{ summary.active_task_boards }}</span>
                    <span class="text-xs text-slate-400 font-medium">{{ summary.total_task_boards }} total boards</span>
                </div>
            </div>

            <!-- Completed Boards -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Completed Boards</span>
                    <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-slate-800">{{ summary.completed_task_boards }}</span>
                    <span class="text-xs text-emerald-600 font-semibold">Delivered</span>
                </div>
            </div>

            <!-- Department Task Completion Rate -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dept Completion Rate</span>
                    <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A2.25 2.25 0 013 18.75v-5.625zM10.5 9.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v10.125c0 .621-.504 1.125-1.125 1.125h-2.25a2.25 2.25 0 01-2.25-2.25V9.75zM18 5.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v14.25c0 .621-.504 1.125-1.125 1.125h-2.25a2.25 2.25 0 01-2.25-2.25V5.625z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <span class="text-3xl font-extrabold text-[#0D9488]">{{ summary.department_completion_rate }}%</span>
                    <span class="text-xs text-slate-400 font-medium">{{ summary.completed_subtasks }} / {{ summary.total_subtasks }} tasks</span>
                </div>
            </div>

            <!-- Timeline Health -->
            <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs flex flex-col justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Timeline Health</span>
                <div class="grid grid-cols-3 gap-1 mt-2 text-center">
                    <div class="bg-emerald-50/70 p-2 rounded border border-emerald-100">
                        <span class="block text-sm font-extrabold text-emerald-700">{{ summary.timeline_health?.on_track || 0 }}</span>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-emerald-800">On Track</span>
                    </div>
                    <div class="bg-amber-50/70 p-2 rounded border border-amber-100">
                        <span class="block text-sm font-extrabold text-amber-700">{{ summary.timeline_health?.at_risk || 0 }}</span>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-amber-800">At Risk</span>
                    </div>
                    <div class="bg-rose-50/70 p-2 rounded border border-rose-100">
                        <span class="block text-sm font-extrabold text-rose-700">{{ summary.timeline_health?.delayed || 0 }}</span>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-rose-800">Delayed</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4.2 Section-by-Section Performance Table -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden" id="section-performance">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-1.5 h-6 bg-[#0D9488] rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">Section-by-Section Performance</h3>
                        <p class="text-xs text-slate-400">Operational comparison across department units</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="isReportModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#0D9488] text-white hover:bg-[#0f766e] transition shadow-xs self-start sm:self-auto"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Generate Report
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="py-3 px-5">Section</th>
                            <th class="py-3 px-4">Section Head / PM</th>
                            <th class="py-3 px-4 text-center">Active Boards</th>
                            <th class="py-3 px-4 text-center">Active Tasks</th>
                            <th class="py-3 px-4 text-center">Overdue</th>
                            <th class="py-3 px-4 w-44">Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <tr v-if="sectionPerformance.length === 0">
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No sections found in this department.
                            </td>
                        </tr>
                        <tr
                            v-for="sec in sectionPerformance"
                            :key="sec.id"
                            class="hover:bg-slate-50/60 transition"
                        >
                            <td class="py-3.5 px-5">
                                <span class="font-bold text-slate-800">{{ sec.name }}</span>
                                <span class="block text-[10px] text-slate-400">{{ sec.department_name }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 font-medium">
                                {{ sec.manager_name }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700">
                                {{ sec.task_boards_count }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-bold text-slate-700">
                                {{ sec.active_tasks }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span v-if="sec.overdue_tasks > 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    {{ sec.overdue_tasks }}
                                </span>
                                <span v-else class="text-slate-400">0</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-bold text-slate-800">{{ sec.completion_rate }}%</span>
                                        <span class="text-slate-400">{{ sec.completed_tasks }}/{{ sec.total_tasks }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-[#0D9488] h-full rounded-full" :style="{ width: `${sec.completion_rate}%` }"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4.3 Department Milestone Roadmap / Macro Gantt View -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5" id="department-roadmap">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-slate-100 mb-5">
                <div class="flex items-center gap-2.5">
                    <span class="w-1.5 h-6 bg-blue-600 rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">Department Roadmap</h3>
                        <p class="text-xs text-slate-400">Macro timeline across key department projects & milestones</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Status Filter -->
                    <select
                        v-model="roadmapStatusFilter"
                        class="text-xs rounded-lg border-slate-200 py-1.5 pl-2.5 pr-8 font-medium focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488]"
                    >
                        <option value="all">All Boards</option>
                        <option value="active">Active Only</option>
                        <option value="delayed">Delayed Only</option>
                        <option value="completed">Completed Only</option>
                    </select>

                    <!-- Time scale toggle -->
                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50 text-xs">
                        <button
                            type="button"
                            @click="timeScale = 'week'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="timeScale === 'week' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Week
                        </button>
                        <button
                            type="button"
                            @click="timeScale = 'month'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="timeScale === 'month' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Month
                        </button>
                        <button
                            type="button"
                            @click="timeScale = 'quarter'"
                            class="px-2.5 py-1 rounded-md font-medium transition"
                            :class="timeScale === 'quarter' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'"
                        >
                            Quarter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Roadmap timeline bars -->
            <div v-if="filteredRoadmap.length === 0" class="py-12 text-center text-slate-400 text-xs">
                No roadmap items found for this filter.
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="item in filteredRoadmap"
                    :key="item.id"
                    class="p-4 rounded-xl border border-slate-200/70 bg-slate-50/40 hover:bg-white hover:border-slate-300 transition"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <div>
                            <h4 class="font-bold text-slate-800 text-sm hover:text-[#0D9488] transition">
                                <Link :href="route('task-boards.show', item.id)">{{ item.name }}</Link>
                            </h4>
                            <span class="text-xs text-slate-400">Section: {{ item.section_name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500 font-medium">
                                {{ item.start_date || 'No start' }} &rarr; {{ item.end_date || 'No deadline' }}
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                :class="item.is_delayed ? 'bg-rose-50 text-rose-700 border-rose-200 font-extrabold' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                {{ item.is_delayed ? 'Delayed' : item.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Macro Gantt bar representation -->
                    <div class="w-full bg-slate-200/80 h-3 rounded-full overflow-hidden mt-3 relative">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="item.is_delayed ? 'bg-rose-500' : 'bg-[#0D9488]'"
                            :style="{ width: `${item.progress}%` }"
                        ></div>
                    </div>

                    <div class="flex items-center justify-between mt-2 text-[11px] text-slate-400 font-medium">
                        <span>{{ item.completed_tasks }} / {{ item.total_tasks }} tasks finished</span>
                        <span class="font-bold text-slate-700">{{ item.progress }}% completion</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4.4 Report Generator Modal -->
        <div v-if="isReportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Department Report Generator</h3>
                        <p class="text-xs text-slate-400">Export operational data for meetings and audits</p>
                    </div>
                    <button type="button" @click="isReportModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Select Report Type -->
                <div class="space-y-2.5">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Select Report Scope</label>
                    <div class="space-y-2">
                        <label
                            v-for="rep in reportOptions"
                            :key="rep.id"
                            class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                            :class="selectedReportType === rep.id ? 'border-[#0D9488] bg-[#F0FDFA]' : 'border-slate-200 hover:bg-slate-50'"
                        >
                            <input
                                type="radio"
                                v-model="selectedReportType"
                                :value="rep.id"
                                class="mt-0.5 text-[#0D9488] focus:ring-[#0D9488]"
                            />
                            <div>
                                <span class="block text-xs font-bold text-slate-800">{{ rep.name }}</span>
                                <span class="block text-[11px] text-slate-500 mt-0.5 leading-snug">{{ rep.desc }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Export Action Buttons -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        @click="triggerPrintReport()"
                        class="px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition"
                    >
                        Print-Friendly View
                    </button>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="isReportModalOpen = false"
                            class="px-3.5 py-2 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="downloadReport('csv')"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold bg-[#0D9488] text-white hover:bg-[#0f766e] transition shadow-xs"
                        >
                            <span v-if="isExporting" class="animate-spin text-white">⏳</span>
                            Download CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
