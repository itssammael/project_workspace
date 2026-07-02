<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
    pendingTasks: Array,
    undeliveredTasks: Array,
    isDeptHead: Boolean,
    isProjectManager: Boolean,
    memberRole: String,
    systemRole: String,
});

const updateTaskStatus = (task, newStatus) => {
    router.put(route('tasks.update', task.id), {
        status: newStatus
    }, {
        preserveScroll: true
    });
};

const getStatusClass = (status) => {
    switch (status) {
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'in_progress':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        default:
            return 'bg-amber-50 text-amber-700 border-amber-200';
    }
};

const getInitials = (name) => {
    return name ? name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : '??';
};
</script>

<template>
    <AppLayout title="Project Dashboard">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Project Workspace
                    </h2>
                    <p class="text-slate-500 text-sm mt-1">
                        System Role: <span class="font-medium text-slate-700">{{ systemRole }}</span> &bull; 
                        Functional Role: <span class="font-medium text-indigo-600">{{ memberRole }}</span>
                    </p>
                </div>
                <div v-if="isDeptHead" class="flex items-center">
                    <Link
                        :href="route('projects.create')"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-indigo-600 to-violet-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:from-indigo-700 hover:to-violet-700 active:from-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        New Project
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Metrics Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Projects Count Card -->
                    <div class="bg-white/80 backdrop-blur-md border border-slate-100 rounded-xl p-6 shadow-sm hover:shadow-md transition duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Projects</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2">{{ projects.length }}</h3>
                            </div>
                            <div class="bg-indigo-50 p-2.5 rounded-lg text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Tasks Card -->
                    <div class="bg-white/80 backdrop-blur-md border border-slate-100 rounded-xl p-6 shadow-sm hover:shadow-md transition duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">My Pending Tasks</p>
                                <h3 class="text-3xl font-extrabold text-slate-800 mt-2">{{ pendingTasks.length }}</h3>
                            </div>
                            <div class="bg-sky-50 p-2.5 rounded-lg text-sky-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 11.69a2.625 2.625 0 113.75 3.75L12 18.75l-3.1-3.1a2.625 2.625 0 013.75-3.75h.7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 2.25H15M9 4.5H15M2.25 12a9.75 9.75 0 1119.5 0 9.75 9.75 0 01-19.5 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Overdue Tasks Card -->
                    <div class="bg-white/80 backdrop-blur-md border border-slate-100 rounded-xl p-6 shadow-sm hover:shadow-md transition duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Overdue Tasks</p>
                                <h3 class="text-3xl font-extrabold text-rose-600 mt-2">{{ undeliveredTasks.length }}</h3>
                            </div>
                            <div class="bg-rose-50 p-2.5 rounded-lg text-rose-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- User Persona Card -->
                    <div class="bg-white/80 backdrop-blur-md border border-slate-100 rounded-xl p-6 shadow-sm hover:shadow-md transition duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Operational Status</p>
                                <h3 class="text-lg font-bold text-slate-700 mt-2 truncate">{{ $page.props.auth.user.name }}</h3>
                                <p class="text-xs text-indigo-500 font-semibold mt-1">Logged In</p>
                            </div>
                            <div class="h-11 w-11 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                                {{ getInitials($page.props.auth.user.name) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 1: Assigned Projects Grid -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center">
                            <span class="w-1 h-5 bg-indigo-600 rounded-full mr-2"></span>
                            Assigned Projects
                        </h3>
                    </div>

                    <div v-if="projects.length === 0" class="bg-white border border-slate-100 rounded-xl p-8 text-center text-slate-400">
                        No projects assigned to your teams yet.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="project in projects" :key="project.id" class="bg-white border border-slate-100 rounded-xl shadow-sm hover:shadow-md transition duration-300 flex flex-col justify-between overflow-hidden">
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between items-start gap-4">
                                    <h4 class="font-bold text-slate-800 text-lg hover:text-indigo-600 transition">
                                        <Link :href="route('projects.show', project.id)">{{ project.name }}</Link>
                                    </h4>
                                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-full border"
                                        :class="project.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'">
                                        {{ project.status }}
                                    </span>
                                </div>
                                <p class="text-slate-500 text-sm line-clamp-2">{{ project.description || 'No description provided.' }}</p>
                                
                                <!-- Progress Bar -->
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-xs font-semibold text-slate-500">
                                        <span>Progress</span>
                                        <span>{{ project.progress }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" :style="`width: ${project.progress}%`"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-400">
                                        <span>{{ project.completed_tasks }} / {{ project.total_tasks }} tasks</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="bg-slate-50/50 border-t border-slate-100 px-6 py-4 flex items-center justify-between text-xs text-slate-500">
                                <div class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px]" v-if="project.team?.project_manager">
                                        {{ getInitials(project.team.project_manager.user.name) }}
                                    </div>
                                    <span class="font-medium" v-if="project.team?.project_manager">
                                        PM: {{ project.team.project_manager.user.name.split(' ')[0] }}
                                    </span>
                                </div>
                                <Link :href="route('projects.show', project.id)" class="text-indigo-600 hover:text-indigo-700 font-semibold flex items-center gap-1">
                                    Gantt Chart
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2 & 3 Side by Side / Stacked -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Section 2: Pending/Assigned Tasks -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center">
                            <span class="w-1 h-5 bg-sky-500 rounded-full mr-2"></span>
                            My Pending & Active Tasks
                        </h3>

                        <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
                            <div v-if="pendingTasks.length === 0" class="p-8 text-center text-slate-400">
                                You have no open tasks. Great job!
                            </div>
                            <div v-else class="divide-y divide-slate-100 max-h-[450px] overflow-y-auto">
                                <div v-for="task in pendingTasks" :key="task.id" class="p-5 flex justify-between items-start gap-4 hover:bg-slate-50/50 transition">
                                    <div class="space-y-1">
                                        <h4 class="font-semibold text-slate-800 text-sm">{{ task.name }}</h4>
                                        <p class="text-xs text-slate-400">
                                            {{ task.project?.name }} &bull; <span class="font-medium text-slate-500">{{ task.development_phase?.name }}</span>
                                        </p>
                                        <p class="text-xs text-slate-500">Duration: {{ task.duration }} days &bull; Starts: {{ task.start_date ? new Date(task.start_date).toLocaleDateString() : 'N/A' }}</p>
                                    </div>
                                    <div class="flex items-center">
                                        <!-- Inline status selector -->
                                        <select
                                            @change="updateTaskStatus(task, $event.target.value)"
                                            :value="task.status"
                                            class="text-xs rounded-lg border-slate-200 py-1.5 pl-2.5 pr-8 font-semibold focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                            :class="getStatusClass(task.status)"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Undelivered / Overdue Tasks -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center">
                            <span class="w-1 h-5 bg-rose-600 rounded-full mr-2"></span>
                            Undelivered & Delayed Tasks
                        </h3>

                        <div class="bg-white border border-slate-100 rounded-xl shadow-sm overflow-hidden">
                            <div v-if="undeliveredTasks.length === 0" class="p-8 text-center text-slate-400">
                                No overdue tasks currently. Keep it up!
                            </div>
                            <div v-else class="divide-y divide-slate-100 max-h-[450px] overflow-y-auto">
                                <div v-for="task in undeliveredTasks" :key="task.id" class="p-5 flex justify-between items-start gap-4 hover:bg-slate-50/50 transition">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-semibold text-slate-800 text-sm">{{ task.name }}</h4>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                                Overdue
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400">
                                            {{ task.project?.name }} &bull; <span class="font-medium text-slate-500">{{ task.development_phase?.name }}</span>
                                        </p>
                                        <p class="text-xs text-slate-500">Duration: {{ task.duration }} days &bull; Started: {{ task.start_date ? new Date(task.start_date).toLocaleDateString() : 'N/A' }}</p>
                                        <p class="text-xs text-rose-500 font-semibold">
                                            Should have finished by: 
                                            {{ new Date(new Date(task.start_date).getTime() + task.duration * 24 * 60 * 60 * 1000).toLocaleDateString() }}
                                        </p>
                                    </div>
                                    <div class="flex items-center">
                                        <!-- Inline status selector -->
                                        <select
                                            @change="updateTaskStatus(task, $event.target.value)"
                                            :value="task.status"
                                            class="text-xs rounded-lg border-slate-200 py-1.5 pl-2.5 pr-8 font-semibold focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                            :class="getStatusClass(task.status)"
                                        >
                                            <option value="pending">Pending</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
