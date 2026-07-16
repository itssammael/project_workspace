<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    projects: Array,
    canCreateProjects: Boolean,
    canDeleteProjects: Boolean,
});

// Search & filter states
const search = ref('');
const statusFilter = ref('');

// Computed stats
const stats = computed(() => {
    const total = props.projects.length;
    const active = props.projects.filter(p => p.status === 'active').length;
    const completed = props.projects.filter(p => p.status === 'completed').length;
    const planning = props.projects.filter(p => p.status === 'planning' || p.status === 'on_hold').length;

    return {
        total,
        active,
        completed,
        planning,
    };
});

// Filter projects
const filteredProjects = computed(() => {
    return props.projects.filter(p => {
        const matchesSearch = p.name.toLowerCase().includes(search.value.toLowerCase()) ||
                              (p.description && p.description.toLowerCase().includes(search.value.toLowerCase()));
        const matchesStatus = !statusFilter.value || p.status === statusFilter.value;
        return matchesSearch && matchesStatus;
    });
});

const getStatusStyles = (status) => {
    switch (status) {
        case 'active':
        case 'completed':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'planning':
        case 'on_hold':
            return 'bg-orange-50 text-orange-700 border-orange-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const confirmModalState = ref({
    show: false,
    title: '',
    message: '',
    onConfirm: null,
});

const triggerConfirm = (title, message, callback) => {
    confirmModalState.value = {
        show: true,
        title,
        message,
        onConfirm: () => {
            callback();
            confirmModalState.value.show = false;
        }
    };
};

const deleteProject = (project) => {
    triggerConfirm(
        'Delete Project',
        `Are you sure you want to delete the project "${project.name}"? This action is permanent and will delete all tasks and phase associations.`,
        () => {
            router.delete(route('projects.destroy', project.id));
        }
    );
};
</script>

<template>
    <AppLayout title="Task Boards">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 text-xs font-semibold text-[#0D9488]">
                        <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                        <span>&bull;</span>
                        <span class="text-slate-400">Projects</span>
                    </div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                        Task Boards
                    </h2>
                </div>

                <div v-if="canCreateProjects">
                    <Link 
                        :href="route('projects.create')"
                        class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Create Board
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-full mx-auto px-8 sm:px-6 lg:px-16 space-y-8">
                
                <!-- Quick Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-[#F0FDFA] border border-teal-100 flex items-center justify-center text-[#0D9488]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.375c1.88 0 3.42-1.59 3.42-3.56c0-1.97-1.54-3.56-3.42-3.56H9v7.12z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h.008v.008H3.75V12zm0 3h.008v.008H3.75V15zm0-6h.008v.008H3.75V9zm3-3H6.758v.008H6.75V6zm0 12h.008v.008H6.75V18z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Boards</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.total }}</h3>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-green-50 border border-green-100 flex items-center justify-center text-[#16A34A]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Active</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.active }}</h3>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-green-50 border border-green-100 flex items-center justify-center text-[#16A34A]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Completed</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.completed }}</h3>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-100 p-5 rounded-2xl shadow-sm flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-orange-50 border border-orange-100 flex items-center justify-center text-[#EA580C]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">Planning / Hold</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-0.5">{{ stats.planning }}</h3>
                        </div>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row gap-4 bg-white border border-slate-100 p-4 rounded-2xl shadow-sm">
                    <input 
                        type="text" 
                        v-model="search"
                        placeholder="Search boards by name or details..."
                        class="flex-1 rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm"
                    />
                    <select 
                        v-model="statusFilter"
                        class="rounded-xl border-slate-200 text-sm focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm min-w-[160px]"
                    >
                        <option value="">All Statuses</option>
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>

                <!-- Projects Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div 
                        v-for="project in filteredProjects" 
                        :key="project.id"
                        class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition relative group"
                    >
                        <div class="space-y-4">
                            <!-- Status & Title -->
                            <div class="flex justify-between items-start gap-4">
                                <h3 class="font-bold text-slate-800 text-lg leading-snug group-hover:text-[#0D9488] transition truncate-2-lines">
                                    {{ project.name }}
                                </h3>
                                <span 
                                    :class="[
                                        'px-2 py-0.5 text-[9px] font-bold uppercase rounded-md border tracking-wider shrink-0',
                                        getStatusStyles(project.status)
                                    ]"
                                >
                                    {{ project.status.replace('_', ' ') }}
                                </span>
                            </div>

                            <!-- Description -->
                            <p class="text-slate-500 text-xs line-clamp-3 leading-relaxed">
                                {{ project.description || 'No description provided.' }}
                            </p>

                            <!-- Date Range -->
                            <div class="flex items-center gap-2 text-[11px] text-slate-400 font-semibold bg-slate-50 border border-slate-100 p-2 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-[#0D9488]">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                </svg>
                                <span>{{ formatDate(project.start_date) }}</span>
                                <span>&rarr;</span>
                                <span>{{ formatDate(project.end_date) }}</span>
                            </div>

                            <!-- Section Details -->
                            <div class="border-t border-slate-100 pt-3 flex justify-between items-center text-xs">
                                <div class="min-w-0">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Assigned Section</span>
                                    <span class="font-bold text-slate-700 truncate block mt-0.5">{{ project.section?.name || 'Unassigned Section' }}</span>
                                </div>
                                <div class="text-right shrink-0" v-if="project.section?.project_manager">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Manager</span>
                                    <span class="font-bold text-[#0D9488] block mt-0.5">{{ project.section.project_manager.user.name }}</span>
                                </div>
                            </div>

                            <!-- Progress Indicator -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex justify-between items-center text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <span>Task Progress</span>
                                    <span class="text-slate-700">{{ project.progress }}%</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-slate-200/20">
                                    <div 
                                        class="bg-[#0D9488] h-full rounded-full transition-all duration-500" 
                                        :style="{ width: project.progress + '%' }"
                                    ></div>
                                </div>
                                <div class="flex justify-between text-[10px] text-slate-400 font-semibold">
                                    <span>{{ project.completed_tasks }} completed</span>
                                    <span>{{ project.total_tasks }} total tasks</span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="pt-5 border-t border-slate-100 mt-5 flex gap-2">
                            <Link 
                                :href="route('projects.show', project.id)"
                                :class="[canDeleteProjects ? 'w-3/4' : 'w-full', 'py-2 bg-slate-50 hover:bg-[#F0FDFA] border border-slate-200 hover:border-[#0D9488]/40 text-slate-700 hover:text-[#0D9488] text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-sm']"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5" />
                                </svg>
                                View Board Details
                            </Link>
                            <button 
                                v-if="canDeleteProjects"
                                @click="deleteProject(project)"
                                class="w-1/4 py-2 bg-rose-50 hover:bg-rose-100 border border-rose-200/50 hover:border-rose-300 text-rose-600 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-sm"
                                title="Delete Project"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div 
                        v-if="filteredProjects.length === 0" 
                        class="col-span-full py-16 text-center bg-white border border-slate-100 rounded-2xl shadow-sm space-y-3"
                    >
                        <div class="h-12 w-12 rounded-full bg-slate-50 border border-slate-150 text-slate-400 flex items-center justify-center mx-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h4 class="font-bold text-slate-700 text-sm">No boards found</h4>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">We couldn't find any boards matching your filters. Try checking a different status or spelling.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <ConfirmationModal :show="confirmModalState.show" @close="confirmModalState.show = false">
            <template #title>
                {{ confirmModalState.title }}
            </template>

            <template #content>
                {{ confirmModalState.message }}
            </template>

            <template #footer>
                <SecondaryButton @click="confirmModalState.show = false">
                    Cancel
                </SecondaryButton>

                <DangerButton
                    class="ms-3"
                    @click="confirmModalState.onConfirm"
                >
                    Confirm
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
