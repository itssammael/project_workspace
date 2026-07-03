<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    teams: Array,
    phases: Array,
});

const form = useForm({
    name: '',
    description: '',
    status: 'planning',
    team_id: '',
    start_date: '',
    end_date: '',
    phase_ids: props.phases ? props.phases.map(p => p.id) : [],
});

const groupedPhases = computed(() => {
    const groups = {};
    props.phases.forEach(phase => {
        const type = phase.project_type || 'General';
        if (!groups[type]) {
            groups[type] = [];
        }
        groups[type].push(phase);
    });
    return groups;
});

const togglePhase = (phaseId) => {
    const index = form.phase_ids.indexOf(phaseId);
    if (index > -1) {
        form.phase_ids.splice(index, 1);
    } else {
        form.phase_ids.push(phaseId);
    }
};

const selectAllPhases = () => {
    form.phase_ids = props.phases.map(p => p.id);
};

const deselectAllPhases = () => {
    form.phase_ids = [];
};

const submit = () => {
    form.post(route('projects.store'));
};
</script>

<template>
    <AppLayout title="Create New Project">
        <template #header>
            <div class="flex items-center gap-2 text-xs font-semibold text-[#0D9488] mb-1">
                <Link :href="route('dashboard')" class="hover:underline">Dashboard</Link>
                <span>&bull;</span>
                <span class="text-slate-400">Create Project</span>
            </div>
            <h2 class="font-bold text-2xl text-slate-800 leading-tight">
                Create New Project
            </h2>
        </template>

        <div class="py-8 bg-slate-50/50 min-h-[calc(100vh-140px)]">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 bg-slate-50/50 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-base">Project Details</h3>
                        <p class="text-xs text-slate-500 mt-1">Specify high-level project parameters and team bindings.</p>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Project Name</label>
                            <input 
                                type="text" 
                                v-model="form.name" 
                                required 
                                class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                placeholder="e.g. Enterprise CRM Integration"
                            />
                            <div v-if="form.errors.name" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Description</label>
                            <textarea 
                                v-model="form.description" 
                                rows="4" 
                                class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" 
                                placeholder="Detail the business context, objectives, and deliverables..."
                            ></textarea>
                            <div v-if="form.errors.description" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.description }}</div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Assign Team</label>
                                <select 
                                    v-model="form.team_id" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                >
                                    <option value="" disabled>Select a team...</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">
                                        {{ team.name }} (PM: {{ team.project_manager?.user?.name || 'None' }})
                                    </option>
                                </select>
                                <div v-if="form.errors.team_id" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.team_id }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Initial Status</label>
                                <select 
                                    v-model="form.status" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                >
                                    <option value="planning">Planning</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="on_hold">On Hold</option>
                                </select>
                                <div v-if="form.errors.status" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.status }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Start Date</label>
                                <input 
                                    type="date" 
                                    v-model="form.start_date" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                />
                                <div v-if="form.errors.start_date" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.start_date }}</div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">End Date</label>
                                <input 
                                    type="date" 
                                    v-model="form.end_date" 
                                    required 
                                    class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm"
                                />
                                <div v-if="form.errors.end_date" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.end_date }}</div>
                            </div>
                        </div>

                        <!-- Development Phases Selection -->
                        <div class="border-t border-slate-100 pt-6">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Choose Development Phases</label>
                                    <p class="text-xs text-slate-400 mt-0.5">Select the execution workflow phases that will construct the project timeline.</p>
                                </div>
                                <div class="flex gap-2">
                                    <button 
                                        type="button" 
                                        @click="selectAllPhases" 
                                        class="px-2 py-1 text-[10px] font-bold text-[#0D9488] hover:text-[#0f766e] bg-[#F0FDFA] hover:bg-teal-100 rounded border border-teal-100 transition"
                                    >
                                        Select All
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="deselectAllPhases" 
                                        class="px-2 py-1 text-[10px] font-bold text-slate-500 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded border border-slate-200 transition"
                                    >
                                        Clear All
                                    </button>
                                </div>
                            </div>

                            <div v-if="form.errors.phase_ids" class="text-xs text-rose-500 font-semibold mb-3">{{ form.errors.phase_ids }}</div>

                            <div class="space-y-4">
                                <div v-for="(phasesGroup, groupName) in groupedPhases" :key="groupName" class="bg-slate-50/50 border border-slate-100 rounded-xl p-4">
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">{{ groupName }} Workflow</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        <div 
                                            v-for="phase in phasesGroup" 
                                            :key="phase.id"
                                            @click="togglePhase(phase.id)"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer select-none transition-all',
                                                form.phase_ids.includes(phase.id) 
                                                    ? 'bg-white border-teal-200 text-teal-900 shadow-sm ring-1 ring-teal-100' 
                                                    : 'bg-white/80 border-slate-150 text-slate-600 hover:bg-slate-50'
                                            ]"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :checked="form.phase_ids.includes(phase.id)" 
                                                @click.stop 
                                                @change="togglePhase(phase.id)"
                                                class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-4 w-4"
                                            />
                                            <div class="min-w-0">
                                                <p class="font-bold text-xs leading-none text-slate-800">{{ phase.name }}</p>
                                                <span class="text-[9px] text-slate-400 font-semibold leading-none mt-1.5 block">Order #{{ phase.order }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                            <Link 
                                :href="route('dashboard')" 
                                class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Cancel
                            </Link>
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm disabled:opacity-50"
                            >
                                Create Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
