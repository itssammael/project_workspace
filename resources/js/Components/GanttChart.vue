<script setup>
import { computed } from 'vue';

const props = defineProps({
    project: Object,
    phases: Array,
    canManageTasks: Boolean,
});

const emit = defineEmits(['edit-task', 'add-task']);

// Parse dates range for timeline columns
const timelineDates = computed(() => {
    if (!props.project.start_date || !props.project.end_date) return [];
    
    const dates = [];
    const curr = new Date(props.project.start_date);
    const last = new Date(props.project.end_date);
    
    // Prevent infinite loop & cap at 60 days
    let count = 0;
    while (curr <= last && count < 60) {
        dates.push(new Date(curr));
        curr.setDate(curr.getDate() + 1);
        count++;
    }
    return dates;
});

const totalDays = computed(() => timelineDates.value.length);

// Calculate task column offset (1-based index)
const getTaskColumnStart = (taskStartDate) => {
    if (!taskStartDate) return 1;
    const taskStart = new Date(taskStartDate);
    const projStart = new Date(props.project.start_date);
    
    const diffTime = taskStart - projStart;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    
    // Grid columns are 1-indexed. If it starts before project, clamp to 1.
    return diffDays < 0 ? 1 : diffDays + 1;
};

// Calculate today's column offset for the vertical line indicator
const todayColumn = computed(() => {
    const today = new Date('2026-07-01'); // Fixed system date for demo consistency
    const projStart = new Date(props.project.start_date);
    const projEnd = new Date(props.project.end_date);
    
    if (today < projStart || today > projEnd) return null;
    
    const diffTime = today - projStart;
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    return diffDays + 1;
});

// Format dates nicely
const formatDateShort = (date) => {
    return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
};

// CSS class mapper for task bars
const getTaskBarClass = (task) => {
    const isOverdue = task.status !== 'completed' && new Date('2026-07-01') > new Date(new Date(task.start_date).getTime() + task.duration * 24 * 60 * 60 * 1000);
    
    if (isOverdue) {
        return 'from-[#EA580C] to-[#c2410c] shadow-orange-100/50 hover:shadow-orange-200/50 text-white';
    }
    
    switch (task.status) {
        case 'completed':
            return 'from-[#16A34A] to-[#15803d] shadow-green-100/50 hover:shadow-green-200/50 text-white';
        case 'in_progress':
            return 'from-[#0D9488] to-[#0f766e] shadow-teal-100/50 hover:shadow-teal-200/50 text-white animate-pulse-subtle';
        default:
            return 'from-[#64748B] to-[#475569] shadow-slate-100/50 hover:shadow-slate-200/50 text-white';
    }
};

const getInitials = (name) => {
    return name ? name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : '??';
};
</script>

<template>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden flex flex-col">
        <!-- Gantt Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50/50">
            <div>
                <h3 class="font-bold text-slate-800 text-lg">Project Gantt Timeline</h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Timeline Span: {{ new Date(project.start_date).toLocaleDateString() }} - {{ new Date(project.end_date).toLocaleDateString() }} ({{ totalDays }} days)
                </p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-gradient-to-r from-[#16A34A] to-[#15803d] inline-block"></span>
                    <span class="text-slate-600">Completed</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-gradient-to-r from-[#0D9488] to-[#0f766e] inline-block"></span>
                    <span class="text-slate-600">In Progress</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-gradient-to-r from-[#64748B] to-[#475569] inline-block"></span>
                    <span class="text-slate-600">Pending</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded bg-gradient-to-r from-[#EA580C] to-[#c2410c] inline-block"></span>
                    <span class="text-slate-600">Overdue</span>
                </div>
            </div>
        </div>

        <!-- Gantt Canvas -->
        <div class="flex overflow-x-auto min-w-full">
            <!-- Sidebar (Phases and Tasks) -->
            <div class="w-80 shrink-0 border-r border-slate-100 select-none">
                <!-- Empty Header spacer -->
                <div class="h-[60px] border-b border-slate-100 bg-slate-50/20"></div>
                
                <div v-for="phase in phases" :key="phase.id" class="divide-y divide-slate-100 border-b border-slate-100">
                    <div class="px-4 py-3 bg-slate-50/40 flex justify-between items-center font-bold text-xs text-slate-600 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <span>{{ phase.name }}</span>
                            <span v-if="phase.total_tasks > 0" class="text-[10px] text-slate-400 normal-case font-semibold">
                                ({{ phase.progress }}%)
                            </span>
                        </div>
                        <button 
                            v-if="canManageTasks" 
                            @click="emit('add-task', phase.id)"
                            class="text-[#0D9488] hover:text-[#0f766e] normal-case font-bold flex items-center gap-0.5"
                        >
                            + Add
                        </button>
                    </div>
                    
                    <!-- Phase Tasks -->
                    <div v-if="phase.tasks.length === 0" class="px-4 py-3 text-xs text-slate-400 italic">
                        No tasks in this phase.
                    </div>
                    <div v-else>
                        <div 
                            v-for="task in phase.tasks" 
                            :key="task.id" 
                            @click="canManageTasks ? emit('edit-task', task) : null"
                            class="px-4 h-[60px] flex items-center justify-between gap-3 text-slate-700 hover:bg-slate-50/50 transition cursor-pointer"
                            :class="{'cursor-default': !canManageTasks}"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-800 truncate" :title="task.name">{{ task.name }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Duration: {{ task.duration }} days</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="task.member" class="h-6 w-6 rounded-full bg-[#F0FDFA] text-[#0D9488] font-bold text-[9px] flex items-center justify-center border border-teal-100" :title="task.member.user.name">
                                    {{ getInitials(task.member.user.name) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline (Grid representation) -->
            <div class="grow min-w-[700px] relative">
                <!-- Timeline Header (Days) -->
                <div 
                    class="h-[60px] border-b border-slate-100 grid relative bg-slate-50/20"
                    :style="`grid-template-columns: repeat(${totalDays}, minmax(55px, 1fr))`"
                >
                    <div 
                        v-for="(date, idx) in timelineDates" 
                        :key="idx" 
                        class="border-r border-slate-100/60 flex flex-col items-center justify-center text-[10px] font-semibold text-slate-500"
                    >
                        <span>Day {{ idx + 1 }}</span>
                        <span class="text-[9px] text-slate-400 font-normal mt-0.5">{{ formatDateShort(date) }}</span>
                    </div>
                </div>

                <!-- Grid Tracks overlay for visual guides -->
                <div class="absolute inset-x-0 bottom-0 top-[60px] pointer-events-none grid" :style="`grid-template-columns: repeat(${totalDays}, minmax(55px, 1fr))`">
                    <div v-for="idx in totalDays" :key="idx" class="border-r border-slate-100/40 h-full"></div>
                </div>

                <!-- Today vertical line indicator -->
                <div 
                    v-if="todayColumn" 
                    class="absolute bottom-0 top-[30px] w-0.5 bg-rose-500/80 z-20 pointer-events-none flex flex-col items-center"
                    :style="`left: calc((100% / ${totalDays}) * ${todayColumn - 0.5})`"
                >
                    <span class="bg-rose-500 text-white text-[8px] font-extrabold px-1 rounded-sm shadow-sm select-none -translate-y-4">TODAY</span>
                </div>

                <!-- Timeline Rows for each task -->
                <div v-for="phase in phases" :key="'tl-'+phase.id" class="border-b border-slate-100">
                    <!-- Phase Spacer Row -->
                    <div class="h-8 bg-slate-50/10"></div>
                    
                    <!-- Tasks Rows -->
                    <div v-if="phase.tasks.length === 0" class="h-10"></div>
                    <div v-else>
                        <div 
                            v-for="task in phase.tasks" 
                            :key="'tlt-'+task.id" 
                            class="h-[60px] py-2.5 relative grid items-center"
                            :style="`grid-template-columns: repeat(${totalDays}, minmax(55px, 1fr))`"
                        >
                            <!-- Task Bar plotted dynamically -->
                            <div 
                                class="h-8 rounded-lg shadow-sm bg-gradient-to-r p-2 flex items-center justify-between text-xs font-semibold cursor-pointer z-10 transition-all select-none border border-black/5 hover:-translate-y-0.5 hover:shadow group relative"
                                :style="`grid-column: ${getTaskColumnStart(task.start_date)} / span ${task.duration}`"
                                :class="getTaskBarClass(task)"
                                @click="canManageTasks ? emit('edit-task', task) : null"
                            >
                                <span class="truncate pr-2 select-none">{{ task.name }}</span>
                                <span class="text-[9px] shrink-0 opacity-80 select-none">{{ task.duration }}d</span>

                                <!-- Gorgeous hovering tooltip -->
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-72 bg-slate-900 text-slate-100 p-4 rounded-xl shadow-xl border border-slate-800 text-xs font-normal z-30 space-y-2 pointer-events-none">
                                    <div class="flex justify-between items-start border-b border-slate-800 pb-1.5">
                                        <p class="font-bold text-white text-sm truncate max-w-[200px]">{{ task.name }}</p>
                                        <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-slate-800" :class="task.status === 'completed' ? 'text-green-400' : task.status === 'in_progress' ? 'text-teal-400' : 'text-slate-450'">
                                            {{ task.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p v-if="task.details" class="text-slate-300 leading-relaxed">{{ task.details }}</p>
                                    <p v-if="task.deliverables" class="text-slate-400"><span class="font-semibold text-slate-300">Deliverables:</span> {{ task.deliverables }}</p>
                                    <div class="flex justify-between items-center text-[10px] text-slate-400 pt-1 border-t border-slate-800">
                                        <span>Starts: {{ new Date(task.start_date).toLocaleDateString() }}</span>
                                        <span v-if="task.member">Assignee: {{ task.member.user.name.split(' ')[0] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-pulse-subtle {
    animation: pulse-subtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse-subtle {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}
</style>
