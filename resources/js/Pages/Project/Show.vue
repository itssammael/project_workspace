<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import GanttChart from '@/Components/GanttChart.vue';

const props = defineProps({
    project: Object,
    phases: Array,
    teamMembers: Array,
    canManageTasks: Boolean,
});

// Modal state
const isModalOpen = ref(false);
const modalMode = ref('create'); // 'create', 'edit', 'view'
const selectedTask = ref(null);

const form = useForm({
    id: null,
    name: '',
    details: '',
    deliverables: '',
    duration: 1,
    development_phase_id: '',
    member_id: '',
    start_date: '',
    status: 'pending',
});

const openAddTaskModal = (phaseId) => {
    form.reset();
    form.development_phase_id = phaseId;
    form.start_date = props.project.start_date ? props.project.start_date.split('T')[0] : '';
    modalMode.value = 'create';
    isModalOpen.value = true;
};

const openEditTaskModal = (task) => {
    selectedTask.value = task;
    form.id = task.id;
    form.name = task.name;
    form.details = task.details || '';
    form.deliverables = task.deliverables || '';
    form.duration = task.duration;
    form.development_phase_id = task.development_phase_id;
    form.member_id = task.member_id || '';
    form.start_date = task.start_date ? task.start_date.split('T')[0] : '';
    form.status = task.status;

    // Check permissions
    const currentMemberId = props.teamMembers.find(m => m.email === props.$page?.props?.auth?.user?.email)?.id;
    const isAssignee = task.member_id && task.member_id === currentMemberId;

    if (props.canManageTasks) {
        modalMode.value = 'edit';
    } else if (isAssignee) {
        modalMode.value = 'status_only';
    } else {
        modalMode.value = 'view';
    }
    
    isModalOpen.value = true;
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

const deleteTask = () => {
    if (confirm('Are you sure you want to delete this task?')) {
        form.delete(route('tasks.destroy', form.id), {
            onSuccess: () => closeModal(),
        });
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    selectedTask.value = null;
};
</script>

<template>
    <AppLayout :title="project.name">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600">
                        <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                        <span>&bull;</span>
                        <span class="text-slate-400">Projects</span>
                    </div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        {{ project.name }}
                    </h2>
                </div>
                <div class="flex items-center">
                    <span class="px-3 py-1.5 text-xs font-bold uppercase rounded-xl border bg-indigo-50 text-indigo-700 border-indigo-200">
                        Team: {{ project.team?.name || 'Unassigned' }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-full mx-auto px-8 sm:px-6 lg:px-16 space-y-8 ">
                <!-- Project Details Summary -->
                <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row justify-between gap-8">
                    <div class="space-y-4 max-w-3xl">
                        <h3 class="font-bold text-slate-800 text-lg">Project Summary</h3>
                        <p class="text-slate-600 text-sm leading-relaxed">{{ project.description || 'No description provided.' }}</p>
                    </div>
                    <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-lg">Project Team members</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" v-if="project.team && project.team.members">
                        <div v-for="member in project.team.members" :key="member.id" class="border border-slate-100 rounded-xl p-4 flex items-center gap-3 hover:bg-slate-50 transition">
                            <div class="h-10 w-10 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                                {{ member.user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-bold text-slate-800 text-sm truncate">{{ member.user.name }}</h4>
                                <p class="text-xs text-slate-400 truncate">{{ member.member_role?.name || 'Developer' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-slate-400 text-sm">
                        No team assigned to this project yet.
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
                        <div class="border-t border-slate-200/60 pt-3 flex justify-between" v-if="project.team?.project_manager">
                            <span class="text-slate-400 font-semibold uppercase tracking-wider">Manager:</span>
                            <span class="font-bold text-indigo-600">{{ project.team.project_manager.user.name }}</span>
                        </div>
                    </div>
                </div>
               
                <!-- Gantt Chart Component -->
                <GanttChart 
                    :project="project" 
                    :phases="phases" 
                    :can-manage-tasks="canManageTasks"
                    @add-task="openAddTaskModal"
                    @edit-task="openEditTaskModal"
                />
               
              
            </div>
        </div>

        <!-- Task Creation/Edit Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeModal"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-lg w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ modalMode === 'create' ? 'Add New Task' : modalMode === 'edit' ? 'Edit Task Details' : modalMode === 'status_only' ? 'Update Task Status' : 'Task Details' }}
                    </h3>
                    <button @click="closeModal" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="p-6 space-y-4">
                    <!-- Read-Only View -->
                    <div v-if="modalMode === 'view'" class="space-y-4 text-sm text-slate-600">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Task Name</span>
                            <span class="text-slate-800 font-bold text-base mt-1 block">{{ selectedTask?.name }}</span>
                        </div>
                        <div v-if="selectedTask?.details">
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Details</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ selectedTask?.details }}</p>
                        </div>
                        <div v-if="selectedTask?.deliverables">
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Deliverables</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ selectedTask?.deliverables }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs font-semibold text-slate-400 uppercase">Duration</span>
                                <span class="text-slate-800 font-semibold block mt-1">{{ selectedTask?.duration }} Days</span>
                            </div>
                            <div>
                                <span class="block text-xs font-semibold text-slate-400 uppercase">Start Date</span>
                                <span class="text-slate-800 font-semibold block mt-1">{{ selectedTask?.start_date ? new Date(selectedTask.start_date).toLocaleDateString() : 'N/A' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs font-semibold text-slate-400 uppercase">Status</span>
                                <span class="px-2 py-0.5 inline-block text-[10px] font-bold rounded uppercase mt-1 border bg-slate-50 text-slate-600 border-slate-200">
                                    {{ selectedTask?.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs font-semibold text-slate-400 uppercase">Assignee</span>
                                <span class="text-slate-800 font-semibold block mt-1">{{ selectedTask?.member?.user?.name || 'Unassigned' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Status Only Form -->
                    <div v-else-if="modalMode === 'status_only'" class="space-y-4">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Task Name</span>
                            <span class="text-slate-800 font-bold block">{{ selectedTask?.name }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Update Status</label>
                            <select v-model="form.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Full Create/Edit Form -->
                    <div v-else class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Task Name</label>
                            <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Implement User Authentication" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Details / Description</label>
                            <textarea v-model="form.details" rows="3" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Detail the task scope..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Key Deliverables</label>
                            <input type="text" v-model="form.deliverables" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. AuthController, Unit Tests" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Duration (Days)</label>
                                <input type="number" v-model="form.duration" min="1" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Start Date</label>
                                <input type="date" v-model="form.start_date" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assign Member</label>
                                <select v-model="form.member_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Unassigned</option>
                                    <option v-for="member in teamMembers" :key="member.id" :value="member.id">
                                        {{ member.name }} ({{ member.role }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                                <select v-model="form.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-between items-center pt-4 border-t border-slate-100 mt-6">
                        <div>
                            <button 
                                v-if="modalMode === 'edit'" 
                                type="button" 
                                @click="deleteTask" 
                                class="inline-flex items-center px-4 py-2 border border-rose-200 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition"
                            >
                                Delete Task
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <button 
                                type="button" 
                                @click="closeModal" 
                                class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Close
                            </button>
                            <button 
                                v-if="modalMode !== 'view'" 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition shadow-sm"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
