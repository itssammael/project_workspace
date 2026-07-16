<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    project: Object,
    workflows: Array,
    canManageTasks: Boolean,
});

const emit = defineEmits(['edit-task', 'add-task']);

const collapsedTasks = ref({});

const toggleTask = (taskId) => {
    collapsedTasks.value[taskId] = !collapsedTasks.value[taskId];
};

const isTaskCollapsed = (taskId) => {
    return !!collapsedTasks.value[taskId];
};

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

// Get system date (current date) set to midnight local time
const getSystemToday = () => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
};

const today = getSystemToday();

// Calculate today's column offset for the vertical line indicator
const todayColumn = computed(() => {
    const projStart = new Date(props.project.start_date);
    const projEnd = new Date(props.project.end_date);
    
    // Set time to midnight for consistency in date-only comparison
    projStart.setHours(0, 0, 0, 0);
    projEnd.setHours(0, 0, 0, 0);
    
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
    if (!task.start_date) {
        return 'from-[#64748B] to-[#475569] shadow-slate-100/50 hover:shadow-slate-200/50 text-white';
    }
    const taskStart = new Date(task.start_date);
    taskStart.setHours(0, 0, 0, 0);
    const dueDate = new Date(taskStart.getTime() + task.duration * 24 * 60 * 60 * 1000);
    dueDate.setHours(0, 0, 0, 0);
    
    const isOverdue = task.status !== 'completed' && today > dueDate;
    
    if (isOverdue) {
        return 'from-[#EA580C] to-[#c2410c] shadow-orange-100/50 hover:shadow-orange-200/50 text-white';
    }
    
    switch (task.status) {
        case 'completed':
            return 'from-[#16A34A] to-[#15803d] shadow-green-100/50 hover:shadow-green-200/50 text-white';
        case 'in_progress':
            return 'from-[#0D9488] to-[#0f766e] shadow-teal-100/50 hover:shadow-teal-200/50 text-white animate-pulse-subtle';
        case 'submitted':
            return 'from-[#6366F1] to-[#4F46E5] shadow-indigo-100/50 hover:shadow-indigo-200/50 text-white';
        default:
            return 'from-[#64748B] to-[#475569] shadow-slate-100/50 hover:shadow-slate-200/50 text-white';
    }
};

const getInitials = (name) => {
    return name ? name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : '??';
};

const getMemberName = (member) => {
    if (!member) return '';
    return member.user?.name || member.name || '';
};

const getMemberInitials = (member) => {
    return getInitials(getMemberName(member));
};

const isSubTaskModalOpen = ref(false);
const selectedSubTask = ref(null);
const selectedParentTask = ref(null);

const openSubTaskModal = (subtask, parentTask) => {
    selectedSubTask.value = subtask;
    selectedParentTask.value = parentTask;
    isSubTaskModalOpen.value = true;
};

const closeSubTaskModal = () => {
    isSubTaskModalOpen.value = false;
    selectedSubTask.value = null;
    selectedParentTask.value = null;
};

const getTooltipPositionClass = (startDate) => {
    if (!startDate) return 'left-1/2 -translate-x-1/2';
    const startCol = getTaskColumnStart(startDate);
    if (startCol <= 4) {
        return 'left-0 translate-x-0';
    }
    if (totalDays.value - startCol <= 4) {
        return 'right-0 translate-x-0';
    }
    return 'left-1/2 -translate-x-1/2';
};

const getTooltipClass = (task, isSubtask = false, subtaskId = null) => {
    const base = 'absolute hidden group-hover:block w-72 bg-slate-900 text-slate-100 p-4 rounded-xl shadow-xl border border-slate-800 text-xs font-normal z-35 space-y-2 pointer-events-none flex flex-col gap-2';
    
    const date = isSubtask ? task.sub_tasks.find(st => st.id === subtaskId)?.start_date : task.start_date;
    const horizClass = getTooltipPositionClass(date);
    
    let visibleRows = [];
    props.workflows.forEach(w => {
        w.tasks.forEach(t => {
            visibleRows.push({ type: 'task', id: t.id });
            if (!isTaskCollapsed(t.id) && t.sub_tasks) {
                t.sub_tasks.forEach(st => {
                    visibleRows.push({ type: 'subtask', id: st.id });
                });
            }
        });
    });
    
    const targetId = isSubtask ? subtaskId : task.id;
    const rowIdx = visibleRows.findIndex(r => r.id === targetId);
    
    let vertClass = 'bottom-full mb-2';
    if (rowIdx >= 0 && rowIdx <= 1) {
        vertClass = 'top-full mt-2';
    }
    
    return `${base} ${horizClass} ${vertClass}`;
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
            <div class="w-80 shrink-0 border-r border-slate-100 select-none sticky left-0 bg-white z-20">
                <!-- Empty Header spacer -->
                <div class="h-[60px] border-b border-slate-100 bg-slate-50/20"></div>
                
                <div v-for="workflow in workflows" :key="workflow.id" class="border-b border-slate-100">
                    <div class="px-4 h-8 bg-gray-300/70 border-b border-slate-200/60 flex justify-between items-center font-bold text-[10px] text-slate-600 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <span>{{ workflow.name }}</span>
                            <span v-if="workflow.total_tasks > 0" class="text-[10px] text-slate-400 normal-case font-semibold">
                                ({{ workflow.progress }}%)
                            </span>
                        </div>
                        <button 
                            v-if="canManageTasks" 
                            @click="emit('add-task', workflow.id)"
                            class="text-[#0D9488] hover:text-[#0f766e] normal-case font-bold flex items-center gap-0.5"
                        >
                            + Add
                        </button>
                    </div>
                    
                    <!-- Workflow Tasks -->
                    <div v-if="workflow.tasks.length === 0" class="px-4 h-10 flex items-center text-xs text-slate-400 italic bg-slate-50/30">
                        No tasks in this workflow.
                    </div>
                    <div v-else>
                        <template v-for="task in workflow.tasks" :key="task.id">
                            <!-- Main Task Row -->
                            <div 
                                @click="toggleTask(task.id)"
                                class="px-4 h-[60px] flex items-center justify-between gap-3 text-slate-700 border-b border-slate-100 bg-white hover:bg-slate-50/50 transition cursor-pointer"
                            >
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <!-- Collapse/Expand Toggle Button -->
                                    <button 
                                        v-if="task.sub_tasks && task.sub_tasks.length > 0"
                                        @click.stop="toggleTask(task.id)"
                                        class="p-0.5 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition shrink-0"
                                    >
                                        <svg 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            viewBox="0 0 20 20" 
                                            fill="currentColor" 
                                            class="w-4 h-4 transform transition-transform duration-200"
                                            :class="isTaskCollapsed(task.id) ? '-rotate-90' : ''"
                                        >
                                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <!-- Spacer for alignment if no subtasks -->
                                    <div v-else class="w-5 h-5 shrink-0"></div>
                                    
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-slate-800 truncate" :title="task.name">{{ task.name }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Duration: {{ task.duration }} days</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div v-if="task.member" class="h-6 w-6 rounded-full bg-[#F0FDFA] text-[#0D9488] font-bold text-[9px] flex items-center justify-center border border-teal-100" :title="getMemberName(task.member)">
                                        {{ getMemberInitials(task.member) }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Subtask Rows -->
                            <template v-if="!isTaskCollapsed(task.id)">
                                <div 
                                    v-for="subtask in task.sub_tasks" 
                                    :key="'sub-'+subtask.id"
                                    @click="openSubTaskModal(subtask, task)"
                                    class="pl-8 pr-4 h-12 flex items-center justify-between gap-3 text-slate-600 border-b border-slate-100/60 bg-slate-50/30 hover:bg-slate-100/30 transition cursor-pointer"
                                >
                                    <div class="min-w-0 flex-1 flex items-center gap-2">
                                        <span class="text-slate-400 font-medium text-xs select-none">↳</span>
                                        <p class="text-xs font-medium text-slate-700 truncate" :title="subtask.name">{{ subtask.name }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] text-slate-400 shrink-0">{{ subtask.duration }}d</span>
                                        <div v-if="subtask.member" class="h-5 w-5 rounded-full bg-slate-100 text-slate-600 font-bold text-[8px] flex items-center justify-center border border-slate-200" :title="getMemberName(subtask.member)">
                                            {{ getMemberInitials(subtask.member) }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
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
                <div v-for="workflow in workflows" :key="'tl-'+workflow.id" class="border-b border-slate-100">
                    <!-- Workflow Spacer Row -->
                    <div class="h-8 bg-slate-50/10 border-b border-slate-200/60"></div>
                    
                    <!-- Tasks Rows -->
                    <div v-if="workflow.tasks.length === 0" class="h-10"></div>
                    <div v-else>
                        <template v-for="task in workflow.tasks" :key="'tl-group-'+task.id">
                            <!-- Main Task Row -->
                            <div 
                                class="h-[60px] relative grid items-center border-b border-slate-100 hover:z-30"
                                :style="`grid-template-columns: repeat(${totalDays}, minmax(55px, 1fr))`"
                            >
                                <!-- Task Bar plotted dynamically -->
                                <div 
                                    v-if="task.start_date && task.duration > 0"
                                    class="h-8 rounded-lg shadow-sm bg-gradient-to-r p-2 flex items-center justify-between text-xs font-semibold cursor-pointer z-10 transition-all select-none border border-black/5 hover:-translate-y-0.5 hover:shadow group relative"
                                    :style="`grid-column: ${getTaskColumnStart(task.start_date)} / span ${task.duration}`"
                                    :class="getTaskBarClass(task)"
                                    @click="emit('edit-task', task)"
                                >
                                    <span class="truncate pr-2 select-none">{{ task.name }}</span>
                                    <span class="text-[9px] shrink-0 opacity-80 select-none">{{ task.duration }}d</span>

                                    <!-- Gorgeous hovering tooltip -->
                                    <div 
                                        :class="getTooltipClass(task)"
                                    >
                                        <div class="flex justify-between items-start border-b border-slate-800 pb-1.5">
                                            <p class="font-bold text-white text-sm truncate max-w-[200px]">{{ task.name }}</p>
                                            <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-slate-800" :class="task.status === 'completed' ? 'text-green-400' : task.status === 'submitted' ? 'text-indigo-400' : task.status === 'in_progress' ? 'text-teal-400' : 'text-slate-400'">
                                                {{ task.status.replace('_', ' ') }}
                                            </span>
                                        </div>
                                        <p v-if="task.details" class="text-slate-300 leading-relaxed">{{ task.details }}</p>
                                        <p v-if="task.deliverables" class="text-slate-400"><span class="font-semibold text-slate-300">Deliverables:</span> {{ task.deliverables }}</p>
                                        <div class="flex justify-between items-center text-[10px] text-slate-400 pt-1 border-t border-slate-800">
                                            <span>Starts: {{ new Date(task.start_date).toLocaleDateString() }}</span>
                                            <span v-if="task.member">Assignee: {{ getMemberName(task.member) ? getMemberName(task.member).split(' ')[0] : 'Unassigned' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Subtask Rows -->
                            <template v-if="!isTaskCollapsed(task.id)">
                                <div 
                                    v-for="subtask in task.sub_tasks" 
                                    :key="'tl-sub-'+subtask.id"
                                    class="h-12 relative grid items-center border-b border-slate-100/60 bg-slate-50/10 hover:z-30"
                                    :style="`grid-template-columns: repeat(${totalDays}, minmax(55px, 1fr))`"
                                >
                                    <!-- Subtask Bar plotted dynamically -->
                                    <div 
                                        v-if="subtask.start_date && subtask.duration > 0"
                                        class="h-7 rounded-md shadow-sm bg-gradient-to-r p-1.5 flex items-center justify-between text-[11px] font-semibold cursor-pointer z-10 transition-all select-none border border-black/5 hover:-translate-y-0.5 hover:shadow group relative"
                                        :style="`grid-column: ${getTaskColumnStart(subtask.start_date)} / span ${subtask.duration}`"
                                        :class="getTaskBarClass(subtask)"
                                        @click="openSubTaskModal(subtask, task)"
                                    >
                                        <span class="truncate pr-1.5 select-none">{{ subtask.name }}</span>
                                        <span class="text-[8px] shrink-0 opacity-80 select-none">{{ subtask.duration }}d</span>

                                        <!-- Gorgeous hovering tooltip for Subtask -->
                                        <div 
                                            :class="getTooltipClass(task, true, subtask.id)"
                                        >
                                            <div class="flex justify-between items-start border-b border-slate-800 pb-1.5">
                                                <p class="font-bold text-white text-sm truncate max-w-[200px]">{{ subtask.name }}</p>
                                                <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-slate-800" :class="subtask.status === 'completed' ? 'text-green-400' : subtask.status === 'submitted' ? 'text-indigo-400' : subtask.status === 'in_progress' ? 'text-teal-400' : 'text-slate-400'">
                                                    {{ subtask.status.replace('_', ' ') }}
                                                </span>
                                            </div>
                                            <p v-if="subtask.details" class="text-slate-300 leading-relaxed">{{ subtask.details }}</p>
                                            <p v-if="subtask.deliverables" class="text-slate-400"><span class="font-semibold text-slate-300">Deliverables:</span> {{ subtask.deliverables }}</p>
                                            <div class="flex justify-between items-center text-[10px] text-slate-400 pt-1 border-t border-slate-800">
                                                <span>Starts: {{ subtask.start_date ? new Date(subtask.start_date).toLocaleDateString() : 'N/A' }}</span>
                                                <span v-if="subtask.member">Assignee: {{ getMemberName(subtask.member) ? getMemberName(subtask.member).split(' ')[0] : 'Unassigned' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subtask Details Modal -->
        <div v-if="isSubTaskModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeSubTaskModal"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-10 transform transition-all max-w-md w-full flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-base">
                        Subtask Details
                    </h3>
                    <button @click="closeSubTaskModal" class="text-slate-400 hover:text-slate-600 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-5 text-sm text-slate-650">
                    <!-- Title & Parent Context -->
                    <div>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-teal-600 uppercase tracking-wider mb-1">
                            <span>Parent Task: {{ selectedParentTask?.name }}</span>
                        </div>
                        <h4 class="text-lg font-bold text-slate-800 leading-snug">{{ selectedSubTask?.name }}</h4>
                    </div>

                    <!-- Details / Description -->
                    <div v-if="selectedSubTask?.details" class="space-y-1">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Details / Description</span>
                        <p class="text-slate-600 bg-slate-50/50 border border-slate-100 rounded-xl p-3 text-xs leading-relaxed max-h-36 overflow-y-auto">{{ selectedSubTask.details }}</p>
                    </div>

                    <!-- Deliverables -->
                    <div v-if="selectedSubTask?.deliverables" class="space-y-1">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Deliverables</span>
                        <p class="text-slate-600 bg-slate-50/50 border border-slate-100 rounded-xl p-3 text-xs leading-relaxed">{{ selectedSubTask.deliverables }}</p>
                    </div>

                    <!-- Timeline Details -->
                    <div class="grid grid-cols-2 gap-4 border-t border-b border-slate-100 py-3.5">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Start Date</span>
                            <span class="font-semibold text-slate-850">{{ selectedSubTask?.start_date ? new Date(selectedSubTask.start_date).toLocaleDateString(undefined, { dateStyle: 'medium' }) : 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Duration</span>
                            <span class="font-semibold text-slate-850">{{ selectedSubTask?.duration }} Days</span>
                        </div>
                    </div>

                    <!-- Assignee & Status -->
                    <div class="flex justify-between items-center gap-4">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Assignee</span>
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center border border-slate-200">
                                    {{ getMemberInitials(selectedSubTask?.member) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 text-xs truncate leading-none mb-0.5">{{ getMemberName(selectedSubTask?.member) || 'Unassigned' }}</p>
                                    <p class="text-[10px] text-slate-400 truncate leading-none">{{ selectedSubTask?.member?.role || selectedSubTask?.member?.memberRole?.name || 'Member' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Status</span>
                            <span class="px-2.5 py-1 text-xs font-bold rounded-lg uppercase tracking-wider border shrink-0" :class="selectedSubTask?.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : selectedSubTask?.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : selectedSubTask?.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 bg-slate-50 border-slate-200'">
                                {{ selectedSubTask?.status?.replace('_', ' ') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 flex justify-end bg-slate-50/50">
                    <button @click="closeSubTaskModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        Close
                    </button>
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
