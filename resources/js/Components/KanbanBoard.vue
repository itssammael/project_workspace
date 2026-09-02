<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ConfirmationModal from './ConfirmationModal.vue';
import SecondaryButton from './SecondaryButton.vue';

const props = defineProps({
    project: Object,
    workflows: Array,
    canManageTasks: Boolean,
    showAllTasks: Boolean,
    canToggleAllTasks: Boolean,
    currentMemberId: Number,
});

const emit = defineEmits(['add-task', 'edit-task', 'toggle-all-tasks']);

const activeDropZoneId = ref(null);
const showWarningModal = ref(false);
const warningMessage = ref('');

const showWarning = (message) => {
    warningMessage.value = message;
    showWarningModal.value = true;
};

const canDrag = (task) => {
    // If the task is completed, only PMs can drag it
    if (task.status === 'completed') {
        return props.canManageTasks;
    }
    
    // Project Managers can drag any task
    if (props.canManageTasks) return true;
    
    // Assignees of subtasks can drag
    return task.sub_tasks?.some(st => st.member_id === props.currentMemberId);
};

const onDragStart = (event, task, sourceWorkflowId) => {
    event.dataTransfer.setData('text/plain', JSON.stringify({
        taskId: task.id,
        sourceWorkflowId: sourceWorkflowId,
        currentWorkflowName: getWorkflowName(sourceWorkflowId),
    }));
    event.dataTransfer.effectAllowed = 'move';
};

const onDragEnd = (event) => {
    activeDropZoneId.value = null;
};

const onDragOver = (event, workflowId) => {
    event.preventDefault();
    activeDropZoneId.value = workflowId;
};

const onDragLeave = (event) => {
    activeDropZoneId.value = null;
};

const onDrop = (event, targetWorkflowId) => {
    activeDropZoneId.value = null;
    try {
        const rawData = event.dataTransfer.getData('text/plain');
        if (!rawData) return;
        const data = JSON.parse(rawData);
        const { taskId, sourceWorkflowId } = data;
        
        if (targetWorkflowId === sourceWorkflowId) return;

        // Look up the task object to get its current status
        let taskObj = null;
        for (const w of props.workflows) {
            const found = (w.tasks || []).find(t => t.id === taskId);
            if (found) {
                taskObj = found;
                break;
            }
        }
        if (!taskObj) return;

        const targetWorkflow = props.workflows.find(w => w.id === targetWorkflowId);
        if (!targetWorkflow) return;
        
        const targetName = targetWorkflow.name.toLowerCase().trim();
        const currentStatus = taskObj.status;
        
        // 1. Move FROM Completed: Only PM can move, and only to Submitted stage
        if (currentStatus === 'completed') {
            if (!props.canManageTasks) {
                showWarning('Only Project Managers can move completed tasks.');
                return;
            }
            if (targetName !== 'submitted') {
                showWarning('Completed tasks can only be moved to the Submitted stage.');
                return;
            }
        }
        
        // 2. Move TO Completed: Only PM, and only from Submitted stage
        const isTargetCompleted = targetName === 'completed' || targetName === 'done';
        if (isTargetCompleted) {
            if (!props.canManageTasks) {
                showWarning('Only Project Managers can move tasks to Completed.');
                return;
            }
            if (currentStatus !== 'submitted') {
                showWarning('Tasks can only be moved to Completed from the Submitted stage.');
                return;
            }
        }
        
        router.put(route('tasks.move', taskId), {
            workflow_id: targetWorkflowId
        }, {
            preserveScroll: true
        });
    } catch (e) {
        console.error(e);
    }
};

const getWorkflowName = (workflowId) => {
    const workflow = props.workflows.find(w => w.id === workflowId);
    return workflow ? workflow.name : '';
};

const isOverdue = (task) => {
    if (task.status === 'completed') return false;
    if (!task.start_date) return false;
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const taskStart = new Date(task.start_date);
    taskStart.setHours(0, 0, 0, 0);
    
    const durationDays = Number(task.duration) || 0;
    const dueDate = new Date(taskStart.getTime() + durationDays * 24 * 60 * 60 * 1000);
    dueDate.setHours(0, 0, 0, 0);
    
    return today > dueDate;
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

// Calculate task status color classes for card color coding
const getCardStatusClass = (status) => {
    switch (status) {
        case 'completed':
            return {
                border: 'border-l-4 border-l-green-600',
                badge: 'bg-green-50 text-green-700 border-green-200'
            };
        case 'in_progress':
            return {
                border: 'border-l-4 border-l-teal-600',
                badge: 'bg-teal-50 text-teal-700 border-teal-200'
            };
        case 'submitted':
            return {
                border: 'border-l-4 border-l-blue-600',
                badge: 'bg-blue-50 text-blue-700 border-blue-200'
            };
        default:
            return {
                border: 'border-l-4 border-l-slate-500',
                badge: 'bg-slate-50 text-slate-700 border-slate-200'
            };
    }
};

// Column banner design mapping based on workflow name
const getColumnBannerDetails = (workflowName) => {
    const name = workflowName.toLowerCase();
    if (name === 'to do' || name === 'to-do' || name === 'todo') {
        return {
            bg: 'bg-[#475569]',
            emoji: '🤔',
            title: 'To-Do'
        };
    } else if (name === 'doing' || name === 'in progress' || name === 'in-progress') {
        return {
            bg: 'bg-[#0D9488]',
            emoji: '🤓',
            title: 'Doing'
        };
    } else if (name === 'submitted') {
        return {
            bg: 'bg-[#2563eb]',
            emoji: '😬',
            title: 'Submitted'
        };
    } else if (name === 'completed' || name === 'done') {
        return {
            bg: 'bg-[#16A34A]',
            emoji: '🎉',
            title: 'Done'
        };
    }
    return {
        bg: 'bg-slate-500',
        emoji: '📋',
        title: workflowName
    };
};
</script>

<template>
    <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm space-y-6">
        <div class="flex justify-between items-start gap-4 flex-wrap">
            <div>
                <h3 class="font-bold text-slate-800 text-lg">
                    {{ showAllTasks ? 'All Assigned Tasks' : 'My Assigned Tasks' }}
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ showAllTasks ? 'Viewing all project tasks.' : 'Track and update your assigned project tasks.' }}
                </p>
            </div>
            
            <!-- Toggle switch for PM, Dept Head, and Admin -->
            <div v-if="canToggleAllTasks" class="flex items-center gap-3 bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 select-none">
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Show All Tasks</span>
                <button 
                    type="button"
                    @click="emit('toggle-all-tasks')"
                    :class="[
                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none',
                        showAllTasks ? 'bg-[#0D9488]' : 'bg-slate-200'
                    ]"
                >
                    <span 
                        :class="[
                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                            showAllTasks ? 'translate-x-5' : 'translate-x-0'
                        ]"
                    ></span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div 
                v-for="workflow in workflows" 
                :key="workflow.id" 
                class="flex flex-col rounded-2xl p-4 border min-h-[450px] transition-all duration-200"
                :class="[
                    activeDropZoneId === workflow.id 
                        ? 'bg-stone-300 border-teal-355 ring-2 ring-teal-100/60 shadow-inner' 
                        : 'bg-stone-400/50 border-slate-100/60'
                ]"
                @dragover="onDragOver($event, workflow.id)"
                @dragleave="onDragLeave($event)"
                @drop="onDrop($event, workflow.id)"
            >
                <!-- Column Title -->
                <div class="flex justify-between items-center mb-3 px-1">
                    <h4 class="font-bold text-slate-700 text-sm capitalize">{{ workflow.name }}</h4>
                    <span class="text-xs font-semibold px-2 py-0.5 bg-white border border-slate-200/60 rounded-full text-slate-500">
                        {{ workflow.tasks?.length || 0 }}
                    </span>
                </div>

                <!-- Banner Card -->
                <div :class="[
                    'rounded-xl p-4 mb-4 flex flex-col items-center justify-center text-center shadow-sm select-none',
                    getColumnBannerDetails(workflow.name).bg,
                    'text-white'
                ]">
                    <span class="text-2xl mb-1">
                        {{ getColumnBannerDetails(workflow.name).emoji }}
                    </span>
                    <span class="font-bold text-sm tracking-wide">
                        {{ getColumnBannerDetails(workflow.name).title }}
                    </span>
                </div>

                <!-- Cards List -->
                <div class="space-y-3 flex-1 overflow-y-auto mb-4">
                    <div 
                        v-for="task in workflow.tasks" 
                        :key="task.id"
                        :draggable="canDrag(task)"
                        @dragstart="onDragStart($event, task, workflow.id)"
                        @dragend="onDragEnd($event)"
                        @click="emit('edit-task', task)"
                        :class="[
                            'border rounded-xl p-4 shadow-sm hover:shadow-md transition cursor-pointer flex flex-col gap-2.5 relative',
                            isOverdue(task) 
                                ? 'bg-[#EA580C] border-orange-700 text-white' 
                                : ['bg-white border-slate-100', getCardStatusClass(task.status).border],
                            canDrag(task) ? 'hover:cursor-grab active:cursor-grabbing' : 'opacity-95'
                        ]"
                    >
                        <div class="space-y-1">
                            <div class="flex justify-between items-start gap-2">
                                <h5 :class="['font-bold text-xs leading-snug line-clamp-2', isOverdue(task) ? 'text-white' : 'text-slate-800']">
                                    {{ task.name }}
                                </h5>
                                <span :class="[
                                    'px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0', 
                                    isOverdue(task) 
                                        ? 'bg-orange-700/55 text-white border-orange-500/30' 
                                        : getCardStatusClass(task.status).badge
                                ]">
                                    {{ isOverdue(task) ? 'overdue' : task.status?.replace('_', ' ') }}
                                </span>
                            </div>
                            <p v-if="task.details" :class="['text-[11px] line-clamp-2 leading-relaxed', isOverdue(task) ? 'text-orange-100/90' : 'text-slate-500']">
                                {{ task.details }}
                            </p>
                        </div>

                        <!-- Card Footer -->
                        <div :class="['flex items-center justify-between mt-1 text-[10px] border-t pt-2', isOverdue(task) ? 'border-orange-600 text-orange-200' : 'border-slate-50 text-slate-400']">
                            <div class="flex items-center gap-2">
                                <!-- Details Icon -->
                                <span v-if="task.details" :class="[isOverdue(task) ? 'text-orange-200' : 'text-slate-400']" title="Has details">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                                    </svg>
                                </span>
                                <!-- Subtask Count -->
                                <span v-if="task.sub_tasks?.length" class="flex items-center gap-0.5" title="Subtasks">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                    </svg>
                                    <span class="font-semibold">{{ task.sub_tasks.length }}</span>
                                </span>
                            </div>

                            <!-- Assignee initials -->
                            <div 
                                v-if="task.member" 
                                :class="[
                                    'h-5 w-5 rounded-full font-bold text-[8px] flex items-center justify-center border',
                                    isOverdue(task) 
                                        ? 'bg-orange-700/55 text-white border-orange-500/30' 
                                        : 'bg-slate-100 text-slate-650 border-slate-200'
                                ]"
                                :title="getMemberName(task.member)"
                            >
                                {{ getMemberInitials(task.member) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="!workflow.tasks || workflow.tasks.length === 0" class="text-center py-8 text-xs text-slate-400 italic">
                        No cards in this stage.
                    </div>
                </div>

                <!-- Footer Add Card -->
                <button 
                    v-if="canManageTasks"
                    @click="emit('add-task', workflow.id)"
                    class="w-full py-2 border border-dashed border-slate-200 hover:border-slate-300 hover:bg-white text-slate-500 hover:text-slate-700 text-xs font-semibold rounded-xl transition flex items-center justify-center gap-1.5"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add a card
                </button>
            </div>
        </div>

        <!-- Warning Modal -->
        <ConfirmationModal :show="showWarningModal" @close="showWarningModal = false">
            <template #title>
                Action Restrained
            </template>

            <template #content>
                {{ warningMessage }}
            </template>

            <template #footer>
                <SecondaryButton @click="showWarningModal = false">
                    OK
                </SecondaryButton>
            </template>
        </ConfirmationModal>
    </div>
</template>
