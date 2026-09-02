<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ActivityFormModal from '@/Components/Modals/ActivityFormModal.vue';
import DeleteActivityModal from '@/Components/Modals/DeleteActivityModal.vue';

const props = defineProps({
    canEdit: {
        type: Boolean,
        default: false,
    },
    semesters: Array,
    selectedSemesterId: Number,
    selectedYear: Number,
    selectedSemester: Object,
    semesterMonths: Array,
    groupedSections: Array,
    mmpColumns: Array,
    mmpMatrix: Object,
    lguColumns: Array,
    lguMatrix: Object,
    tardyMatrix: Object,
    undertimeMatrix: Object,
});

const activeTab = ref('lgu'); // 'lgu', 'mmp', 'tardy', 'undertime'
const currentSemesterId = ref(props.selectedSemesterId);
const currentYear = ref(props.selectedYear);
const isSaving = ref(false);

const years = [2026, 2025, 2024];

// Modal States for Activity Management
const showActivityModal = ref(false);
const editingActivity = ref(null);
const activityForm = ref({
    name: '',
    date: new Date().toISOString().substring(0, 10),
});

const showDeleteConfirmModal = ref(false);
const activityToDelete = ref(null);

const handleFilterChange = () => {
    router.get(
        route('support-function-reports.index'),
        {
            semester_id: currentSemesterId.value,
            year: currentYear.value,
        },
        { preserveState: true, replace: true }
    );
};

const handleAttendanceChange = (memberId, scheduledActivityId, status) => {
    if (!props.canEdit) return;
    isSaving.value = true;
    router.post(
        route('support-function-reports.update-attendance'),
        {
            member_id: memberId,
            scheduled_activity_id: scheduledActivityId,
            status: status,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false;
            },
        }
    );
};

const handleTardinessChange = (memberId, month, field, value) => {
    if (!props.canEdit) return;
    isSaving.value = true;
    router.post(
        route('support-function-reports.update-tardiness-undertime'),
        {
            member_id: memberId,
            month: month,
            year: currentYear.value,
            semester_id: currentSemesterId.value,
            field: field,
            value: String(value),
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false;
            },
        }
    );
};

const openAddActivityModal = () => {
    if (!props.canEdit) return;
    editingActivity.value = null;
    activityForm.value = {
        name: '',
        date: new Date().toISOString().substring(0, 10),
    };
    showActivityModal.value = true;
};

const openEditActivityModal = (col) => {
    if (!props.canEdit) return;
    editingActivity.value = col;
    activityForm.value = {
        name: col.name,
        date: col.date || new Date().toISOString().substring(0, 10),
    };
    showActivityModal.value = true;
};

const handleActivitySubmit = (data) => {
    activityForm.value.name = data.name;
    activityForm.value.date = data.date;
    submitActivityForm();
};

const submitActivityForm = () => {
    if (!props.canEdit || !activityForm.value.name || !activityForm.value.date) return;

    isSaving.value = true;
    if (editingActivity.value) {
        router.put(
            route('support-function-reports.activities.update', editingActivity.value.id),
            {
                name: activityForm.value.name,
                date: activityForm.value.date,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showActivityModal.value = false;
                },
                onFinish: () => {
                    isSaving.value = false;
                },
            }
        );
    } else {
        router.post(
            route('support-function-reports.activities.store'),
            {
                name: activityForm.value.name,
                date: activityForm.value.date,
                semester_id: currentSemesterId.value,
                year: currentYear.value,
                activity_type_id: 2, // LGU Activity
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showActivityModal.value = false;
                },
                onFinish: () => {
                    isSaving.value = false;
                },
            }
        );
    }
};

const confirmDeleteActivity = (col) => {
    if (!props.canEdit) return;
    activityToDelete.value = col;
    showDeleteConfirmModal.value = true;
};

const deleteActivity = () => {
    if (!props.canEdit || !activityToDelete.value) return;
    isSaving.value = true;
    router.delete(
        route('support-function-reports.activities.destroy', activityToDelete.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteConfirmModal.value = false;
                activityToDelete.value = null;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        }
    );
};

const getNumericOptions = (currentVal) => {
    const opts = Array.from({ length: 31 }, (_, i) => String(i));
    if (currentVal !== undefined && currentVal !== null && currentVal !== 'ON-LEAVE' && !opts.includes(String(currentVal))) {
        opts.push(String(currentVal));
        opts.sort((a, b) => Number(a) - Number(b));
    }
    return opts;
};

// Group MMP columns by month for table header span
const mmpMonthHeaders = computed(() => {
    if (!props.mmpColumns || !props.mmpColumns.length) return [];
    
    const groups = [];
    let currentMonth = null;
    let currentGroup = null;

    props.mmpColumns.forEach((col) => {
        if (col.month_name !== currentMonth) {
            currentMonth = col.month_name;
            currentGroup = {
                name: currentMonth,
                colspan: 1,
            };
            groups.push(currentGroup);
        } else {
            currentGroup.colspan++;
        }
    });

    return groups;
});
</script>

<template>
    <AppLayout title="Support Function Reports">
        <Head title="Support Function Reports" />

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-9xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                
                <!-- Page Header & Filters -->
                <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md bg-teal-50 text-[#0D9488] border border-teal-200/60">
                                Operational Analytics
                            </span>
                            <span v-if="props.canEdit" class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Admin Edit Access
                            </span>
                            <span v-else class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                Read Only View
                            </span>
                            <span v-if="isSaving" class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-md bg-amber-50 text-amber-600 border border-amber-200 animate-pulse">
                                Saving Changes...
                            </span>
                        </div>
                        <h1 class="text-2xl font-bold text-slate-800 mt-1">Support Function Reports</h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Semester attendance tracking, LGU activity compliance, tardiness, absences, and undertime summary.
                            <span v-if="props.canEdit">Click any cell to edit data.</span>
                            <span v-else>Department view access. Only Members under Section "Admin" can edit data.</span>
                        </p>
                    </div>

                    <!-- Filters Bar -->
                    <div class="flex flex-wrap items-center gap-3 bg-slate-50 p-2.5 rounded-xl border border-slate-200/60">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Semester</label>
                            <select 
                                v-model="currentSemesterId" 
                                @change="handleFilterChange"
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs font-bold text-slate-700 py-1.5 px-3 bg-white"
                            >
                                <option v-for="sem in props.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Year</label>
                            <select 
                                v-model="currentYear" 
                                @change="handleFilterChange"
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs font-bold text-slate-700 py-1.5 px-3 bg-white"
                            >
                                <option v-for="y in years" :key="y" :value="y">
                                    {{ y }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex border-b border-slate-200 gap-2 overflow-x-auto pb-0.5">
                    <button
                        @click="activeTab = 'lgu'"
                        :class="[
                            'px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-t border-x flex items-center gap-2 whitespace-nowrap',
                            activeTab === 'lgu'
                                ? 'bg-white border-slate-200 text-[#0D9488] border-b-transparent -mb-px shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100/50'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .407.207.751.53 1.054a1.868 1.868 0 0 1 .53 1.346c0 .548-.24 1.042-.622 1.385l-.01.01A2.25 2.25 0 0 1 10.158 9h-1.35c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125h.375c.621 0 1.125.504 1.125 1.125V15c0 .621-.504 1.125-1.125 1.125h-.375a1.125 1.125 0 0 1-1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H4.875A1.125 1.125 0 0 1 3.75 11.25v-1.5c0-.621.504-1.125 1.125-1.125h.375c.621 0 1.125-.504 1.125-1.125V6c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v.375Z" />
                        </svg>
                        <span>LGU Activities</span>
                    </button>

                    <button
                        @click="activeTab = 'mmp'"
                        :class="[
                            'px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-t border-x flex items-center gap-2 whitespace-nowrap',
                            activeTab === 'mmp'
                                ? 'bg-white border-slate-200 text-[#0D9488] border-b-transparent -mb-px shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100/50'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span>MMP(Monday Morning)</span>
                    </button>

                    <button
                        @click="activeTab = 'tardy'"
                        :class="[
                            'px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-t border-x flex items-center gap-2 whitespace-nowrap',
                            activeTab === 'tardy'
                                ? 'bg-white border-slate-200 text-[#0D9488] border-b-transparent -mb-px shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100/50'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Tardy & Absences</span>
                    </button>

                    <button
                        @click="activeTab = 'undertime'"
                        :class="[
                            'px-5 py-3 text-xs font-bold rounded-t-xl transition-all border-t border-x flex items-center gap-2 whitespace-nowrap',
                            activeTab === 'undertime'
                                ? 'bg-white border-slate-200 text-[#0D9488] border-b-transparent -mb-px shadow-sm'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-100/50'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                        <span>Undertime</span>
                    </button>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 1: LGU ACTIVITIES -->
                <!-- ========================================================= -->
                <div v-if="activeTab === 'lgu'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-wrap justify-between items-center gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800 uppercase tracking-wide">
                                LGU ACTIVITIES {{ props.selectedSemester?.name }} {{ props.selectedYear }}
                            </h2>
                            <span class="text-xs font-semibold text-slate-500">
                                Total Activities: {{ props.lguColumns.length }}
                            </span>
                        </div>
                        <button
                            v-if="props.canEdit"
                            @click="openAddActivityModal"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-white bg-[#0D9488] hover:bg-teal-700 active:bg-teal-800 rounded-xl shadow-2xs transition-all cursor-pointer"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Add Activity</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                                    <th class="p-3 border-r border-slate-200 min-w-[200px]">NAMES</th>
                                    <th 
                                        v-for="col in props.lguColumns" 
                                        :key="col.id" 
                                        class="p-3 text-center border-r border-slate-200 min-w-[150px] uppercase text-[11px] group relative"
                                    >
                                        <div class="flex flex-col items-center justify-between min-h-[50px]">
                                            <span class="font-bold text-slate-800 leading-tight mb-2">{{ col.name }}</span>
                                            
                                            <!-- Action Buttons (Edit & Delete) - Only if canEdit -->
                                            <div v-if="props.canEdit" class="flex items-center justify-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    @click.stop="openEditActivityModal(col)"
                                                    title="Edit Activity"
                                                    class="p-1 rounded-md text-slate-500 hover:text-teal-600 hover:bg-teal-50 border border-transparent hover:border-teal-200 transition-all cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                </button>
                                                <button
                                                    @click.stop="confirmDeleteActivity(col)"
                                                    title="Remove Activity"
                                                    class="p-1 rounded-md text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition-all cursor-pointer"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </th>
                                    <th class="p-3 text-center border-r border-slate-200 w-28">TOTAL NO. OF ACTIVITIES</th>
                                    <th class="p-3 text-center w-24">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="sec in props.groupedSections" :key="sec.id">
                                    <!-- Section Header Row -->
                                    <tr class="bg-slate-200/70 text-slate-800 font-extrabold">
                                        <td :colspan="props.lguColumns.length + 3" class="px-4 py-2 text-indigo-700 bg-indigo-50/60 border-y border-slate-200">
                                            {{ sec.name }}
                                        </td>
                                    </tr>

                                    <!-- Member Rows -->
                                    <tr 
                                        v-for="m in sec.members" 
                                        :key="m.id" 
                                        class="hover:bg-slate-50 border-b border-slate-100 text-slate-700 font-semibold"
                                    >
                                        <td class="p-3 font-bold border-r border-slate-200 bg-slate-50/30">
                                            {{ m.name }}
                                        </td>

                                        <td 
                                            v-for="col in props.lguColumns" 
                                            :key="col.id" 
                                            class="p-2 text-center border-r border-slate-200 font-bold"
                                        >
                                            <select 
                                                v-if="props.canEdit"
                                                :value="props.lguMatrix[m.id]?.cells[col.id] ?? '1'"
                                                @change="handleAttendanceChange(m.id, col.id, $event.target.value)"
                                                :class="[
                                                    'px-2 py-1 text-xs font-extrabold rounded-lg border shadow-2xs cursor-pointer transition-all appearance-none text-center outline-none focus:ring-2 focus:ring-teal-400/50',
                                                    (props.lguMatrix[m.id]?.cells[col.id] ?? '1') === '1' ? 'text-teal-700 bg-teal-50 border-teal-200 hover:bg-teal-100/70' :
                                                    (props.lguMatrix[m.id]?.cells[col.id] === 'A') ? 'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100/70' :
                                                    'text-purple-700 bg-purple-50 border-purple-200 hover:bg-purple-100/70 text-[10px]'
                                                ]"
                                            >
                                                <option value="1">1</option>
                                                <option value="A">A</option>
                                                <option value="ON-LEAVE">ON-LEAVE</option>
                                            </select>
                                            <template v-else>
                                                <span 
                                                    v-if="props.lguMatrix[m.id]?.cells[col.id] === '1' || !props.lguMatrix[m.id]?.cells[col.id]" 
                                                    class="inline-block px-2 py-0.5 rounded text-teal-700 bg-teal-50 border border-teal-200 font-bold"
                                                >
                                                    1
                                                </span>
                                                <span 
                                                    v-else-if="props.lguMatrix[m.id]?.cells[col.id] === 'A'" 
                                                    class="inline-block px-2 py-0.5 rounded text-rose-700 bg-rose-50 border border-rose-200 font-bold"
                                                >
                                                    A
                                                </span>
                                                <span 
                                                    v-else-if="props.lguMatrix[m.id]?.cells[col.id] === 'ON-LEAVE'" 
                                                    class="inline-block px-2 py-0.5 rounded text-purple-700 bg-purple-50 border border-purple-200 text-[10px] font-bold"
                                                >
                                                    ON-LEAVE
                                                </span>
                                            </template>
                                        </td>

                                        <td class="p-3 text-center border-r border-slate-200 font-bold text-slate-900 bg-slate-50/40">
                                            {{ props.lguMatrix[m.id]?.total ?? 0 }}
                                        </td>

                                        <td class="p-3 text-center font-extrabold text-slate-800">
                                            {{ props.lguMatrix[m.id]?.percentage ?? 100 }}%
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 2: MMP (MONDAY MORNING PROGRAM) -->
                <!-- ========================================================= -->
                <div v-if="activeTab === 'mmp'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-800 uppercase tracking-wide">
                            SUMMARY OF MONDAY MORNING PROGRAM {{ props.selectedSemester?.name }} {{ props.selectedYear }}
                        </h2>
                        <span class="text-xs font-semibold text-slate-500">
                            Total Sessions: {{ props.mmpColumns.length }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <!-- Top Month Group Header -->
                                <tr class="bg-emerald-700 text-white font-bold border-b border-emerald-800 text-center">
                                    <th class="p-3 border-r border-emerald-600 text-left min-w-[200px]" rowspan="2">NAME</th>
                                    <th 
                                        v-for="(grp, idx) in mmpMonthHeaders" 
                                        :key="idx" 
                                        :colspan="grp.colspan" 
                                        class="p-2 border-r border-emerald-600 uppercase text-[11px] font-extrabold"
                                    >
                                        {{ grp.name }}
                                    </th>
                                    <th class="p-3 border-r border-emerald-600 w-20" rowspan="2">TOTAL</th>
                                    <th class="p-3 w-20" rowspan="2">%</th>
                                </tr>
                                <!-- Sub Date Columns Header -->
                                <tr class="bg-emerald-800 text-white font-semibold text-center border-b border-emerald-900">
                                    <th 
                                        v-for="col in props.mmpColumns" 
                                        :key="col.id" 
                                        class="px-1.5 py-1 border-r border-emerald-700 text-[10px] whitespace-nowrap group relative hover:bg-emerald-900/80 transition-colors"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold">{{ col.label }}</span>
                                            <button
                                                v-if="props.canEdit"
                                                @click.stop="confirmDeleteActivity(col)"
                                                title="Delete Monday Session"
                                                class="p-0.5 rounded text-emerald-300 hover:text-rose-300 hover:bg-rose-900/50 opacity-70 group-hover:opacity-100 transition-all cursor-pointer"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="sec in props.groupedSections" :key="sec.id">
                                    <!-- Section Header Row -->
                                    <tr class="bg-slate-200/70 text-slate-800 font-extrabold">
                                        <td :colspan="props.mmpColumns.length + 3" class="px-4 py-2 text-indigo-700 bg-indigo-50/60 border-y border-slate-200">
                                            {{ sec.name }}
                                        </td>
                                    </tr>

                                    <!-- Member Rows -->
                                    <tr 
                                        v-for="m in sec.members" 
                                        :key="m.id" 
                                        class="hover:bg-slate-50 border-b border-slate-100 text-slate-700 font-semibold"
                                    >
                                        <td class="p-3 font-bold border-r border-slate-200 bg-slate-50/30">
                                            {{ m.name }}
                                        </td>

                                        <td 
                                            v-for="col in props.mmpColumns" 
                                            :key="col.id" 
                                            class="p-1.5 text-center border-r border-slate-200 font-bold"
                                        >
                                            <select 
                                                v-if="props.canEdit"
                                                :value="props.mmpMatrix[m.id]?.cells[col.id] ?? '1'"
                                                @change="handleAttendanceChange(m.id, col.id, $event.target.value)"
                                                :class="[
                                                    'px-1.5 py-0.5 text-xs font-extrabold rounded-md border shadow-2xs cursor-pointer transition-all appearance-none text-center outline-none focus:ring-2 focus:ring-emerald-400/50',
                                                    (props.mmpMatrix[m.id]?.cells[col.id] ?? '1') === '1' ? 'text-teal-700 bg-teal-50 border-teal-200 hover:bg-teal-100/70' :
                                                    (props.mmpMatrix[m.id]?.cells[col.id] === 'A') ? 'text-rose-700 bg-rose-50 border-rose-200 hover:bg-rose-100/70' :
                                                    'text-purple-700 bg-purple-50 border-purple-200 hover:bg-purple-100/70 text-[9px]'
                                                ]"
                                            >
                                                <option value="1">1</option>
                                                <option value="A">A</option>
                                                <option value="ON-LEAVE">ON-LEAVE</option>
                                            </select>
                                            <template v-else>
                                                <span 
                                                    v-if="props.mmpMatrix[m.id]?.cells[col.id] === '1' || !props.mmpMatrix[m.id]?.cells[col.id]" 
                                                    class="text-teal-700 font-bold"
                                                >
                                                    1
                                                </span>
                                                <span 
                                                    v-else-if="props.mmpMatrix[m.id]?.cells[col.id] === 'A'" 
                                                    class="text-rose-700 font-extrabold"
                                                >
                                                    A
                                                </span>
                                                <span 
                                                    v-else-if="props.mmpMatrix[m.id]?.cells[col.id] === 'ON-LEAVE'" 
                                                    class="text-purple-700 font-bold text-[9px]"
                                                >
                                                    ON-LEAVE
                                                </span>
                                            </template>
                                        </td>

                                        <td class="p-3 text-center border-r border-slate-200 font-extrabold text-slate-900 bg-slate-50/40">
                                            {{ props.mmpMatrix[m.id]?.total ?? 0 }}
                                        </td>

                                        <td class="p-3 text-center font-extrabold text-slate-800">
                                            {{ props.mmpMatrix[m.id]?.percentage ?? 100 }}%
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 3: TARDY & ABSENCES -->
                <!-- ========================================================= -->
                <div v-if="activeTab === 'tardy'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-800 uppercase tracking-wide">
                            ABSENT & TARDY SUMMARY {{ props.selectedSemester?.name }} {{ props.selectedYear }}
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <!-- Top Month Group Header -->
                                <tr class="bg-lime-700 text-white font-bold border-b border-lime-800 text-center">
                                    <th class="p-3 border-r border-lime-600 text-left min-w-[200px]" rowspan="2">NAME</th>
                                    <th 
                                        v-for="mObj in props.semesterMonths" 
                                        :key="mObj.number" 
                                        colspan="2" 
                                        class="p-2 border-r border-lime-600 uppercase text-[11px] font-extrabold"
                                    >
                                        {{ mObj.name }}
                                    </th>
                                    <th colspan="2" class="p-2 bg-orange-700 text-white font-extrabold border-r border-orange-800">TOTAL</th>
                                </tr>
                                <!-- Sub Tardy / Absent Headers -->
                                <tr class="bg-lime-800 text-white font-semibold text-center border-b border-lime-900 text-[10px]">
                                    <template v-for="mObj in props.semesterMonths" :key="mObj.number">
                                        <th class="px-2 py-1.5 border-r border-lime-700">Tardy</th>
                                        <th class="px-2 py-1.5 border-r border-lime-700">Absent</th>
                                    </template>
                                    <th class="px-2 py-1.5 bg-orange-800 border-r border-orange-900">Tardy</th>
                                    <th class="px-2 py-1.5 bg-orange-800">Absent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="sec in props.groupedSections" :key="sec.id">
                                    <!-- Section Header Row -->
                                    <tr class="bg-slate-200/70 text-slate-800 font-extrabold">
                                        <td :colspan="(props.semesterMonths.length + 1) * 2 + 1" class="px-4 py-2 text-indigo-700 bg-indigo-50/60 border-y border-slate-200">
                                            {{ sec.name }}
                                        </td>
                                    </tr>

                                    <!-- Member Rows -->
                                    <tr 
                                        v-for="m in sec.members" 
                                        :key="m.id" 
                                        class="hover:bg-slate-50 border-b border-slate-100 text-slate-700 font-semibold"
                                    >
                                        <td class="p-3 font-bold border-r border-slate-200 bg-slate-50/30">
                                            {{ m.name }}
                                        </td>

                                        <template v-for="mObj in props.semesterMonths" :key="mObj.number">
                                            <td class="p-1.5 text-center border-r border-slate-200 font-bold">
                                                <select 
                                                    v-if="props.canEdit"
                                                    :value="String(props.tardyMatrix[m.id]?.months[mObj.number]?.tardy ?? '0')"
                                                    @change="handleTardinessChange(m.id, mObj.number, 'tardy', $event.target.value)"
                                                    :class="[
                                                        'w-full max-w-[70px] px-1 py-0.5 text-xs font-extrabold rounded border shadow-2xs text-center cursor-pointer transition-all appearance-none outline-none focus:ring-2 focus:ring-lime-400/50',
                                                        props.tardyMatrix[m.id]?.months[mObj.number]?.tardy === 'ON-LEAVE'
                                                            ? 'text-purple-700 bg-purple-50 border-purple-200 text-[9px]'
                                                            : (Number(props.tardyMatrix[m.id]?.months[mObj.number]?.tardy) > 0 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-slate-400 bg-slate-50/50 border-slate-200 hover:border-slate-300')
                                                    ]"
                                                >
                                                    <option v-for="opt in getNumericOptions(props.tardyMatrix[m.id]?.months[mObj.number]?.tardy)" :key="'t-'+opt" :value="opt">{{ opt }}</option>
                                                    <option value="ON-LEAVE">ON-LEAVE</option>
                                                </select>
                                                <span v-else :class="props.tardyMatrix[m.id]?.months[mObj.number]?.tardy === 'ON-LEAVE' ? 'text-purple-700 font-bold text-[9px]' : (Number(props.tardyMatrix[m.id]?.months[mObj.number]?.tardy) > 0 ? 'text-amber-700 font-extrabold' : 'text-slate-400')">
                                                    {{ props.tardyMatrix[m.id]?.months[mObj.number]?.tardy ?? 0 }}
                                                </span>
                                            </td>
                                            <td class="p-1.5 text-center border-r border-slate-200 font-bold">
                                                <select 
                                                    v-if="props.canEdit"
                                                    :value="String(props.tardyMatrix[m.id]?.months[mObj.number]?.absences ?? '0')"
                                                    @change="handleTardinessChange(m.id, mObj.number, 'absences', $event.target.value)"
                                                    :class="[
                                                        'w-full max-w-[70px] px-1 py-0.5 text-xs font-extrabold rounded border shadow-2xs text-center cursor-pointer transition-all appearance-none outline-none focus:ring-2 focus:ring-lime-400/50',
                                                        props.tardyMatrix[m.id]?.months[mObj.number]?.absences === 'ON-LEAVE'
                                                            ? 'text-purple-700 bg-purple-50 border-purple-200 text-[9px]'
                                                            : (Number(props.tardyMatrix[m.id]?.months[mObj.number]?.absences) > 0 ? 'text-rose-700 bg-rose-50 border-rose-200' : 'text-slate-400 bg-slate-50/50 border-slate-200 hover:border-slate-300')
                                                    ]"
                                                >
                                                    <option v-for="opt in getNumericOptions(props.tardyMatrix[m.id]?.months[mObj.number]?.absences)" :key="'a-'+opt" :value="opt">{{ opt }}</option>
                                                    <option value="ON-LEAVE">ON-LEAVE</option>
                                                </select>
                                                <span v-else :class="props.tardyMatrix[m.id]?.months[mObj.number]?.absences === 'ON-LEAVE' ? 'text-purple-700 font-bold text-[9px]' : (Number(props.tardyMatrix[m.id]?.months[mObj.number]?.absences) > 0 ? 'text-rose-700 font-extrabold' : 'text-slate-400')">
                                                    {{ props.tardyMatrix[m.id]?.months[mObj.number]?.absences ?? 0 }}
                                                </span>
                                            </td>
                                        </template>

                                        <!-- Total Tardy & Absent -->
                                        <td class="p-3 text-center border-r border-slate-200 font-extrabold bg-orange-50/50 text-amber-800">
                                            {{ props.tardyMatrix[m.id]?.total_tardy ?? 0 }}
                                        </td>
                                        <td class="p-3 text-center font-extrabold bg-orange-50/50 text-rose-800">
                                            {{ props.tardyMatrix[m.id]?.total_absent ?? 0 }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TAB 4: UNDERTIME -->
                <!-- ========================================================= -->
                <div v-if="activeTab === 'undertime'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-slate-800 uppercase tracking-wide">
                            UNDERTIME SUMMARY {{ props.selectedSemester?.name }} {{ props.selectedYear }}
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <!-- Top Month Group Header -->
                                <tr class="bg-teal-700 text-white font-bold border-b border-teal-800 text-center">
                                    <th class="p-3 border-r border-teal-600 text-left min-w-[200px]" rowspan="2">Names</th>
                                    <th 
                                        v-for="mObj in props.semesterMonths" 
                                        :key="mObj.number" 
                                        class="p-2 border-r border-teal-600 uppercase text-[11px] font-extrabold"
                                    >
                                        {{ mObj.name }}
                                    </th>
                                    <th class="p-2 bg-orange-700 text-white font-extrabold border-r border-orange-800">TOTAL</th>
                                </tr>
                                <!-- Sub UT Headers -->
                                <tr class="bg-teal-800 text-white font-semibold text-center border-b border-teal-900 text-[10px]">
                                    <th 
                                        v-for="mObj in props.semesterMonths" 
                                        :key="mObj.number" 
                                        class="px-2 py-1.5 border-r border-teal-700"
                                    >
                                        UT
                                    </th>
                                    <th class="px-2 py-1.5 bg-orange-800">UNDERTIME</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="sec in props.groupedSections" :key="sec.id">
                                    <!-- Section Header Row -->
                                    <tr class="bg-slate-200/70 text-slate-800 font-extrabold">
                                        <td :colspan="props.semesterMonths.length + 2" class="px-4 py-2 text-indigo-700 bg-indigo-50/60 border-y border-slate-200">
                                            {{ sec.name }}
                                        </td>
                                    </tr>

                                    <!-- Member Rows -->
                                    <tr 
                                        v-for="m in sec.members" 
                                        :key="m.id" 
                                        class="hover:bg-slate-50 border-b border-slate-100 text-slate-700 font-semibold"
                                    >
                                        <td class="p-3 font-bold border-r border-slate-200 bg-slate-50/30">
                                            {{ m.name }}
                                        </td>

                                        <td 
                                            v-for="mObj in props.semesterMonths" 
                                            :key="mObj.number" 
                                            class="p-1.5 text-center border-r border-slate-200 font-bold"
                                        >
                                            <select 
                                                v-if="props.canEdit"
                                                :value="String(props.undertimeMatrix[m.id]?.months[mObj.number] ?? '0')"
                                                @change="handleTardinessChange(m.id, mObj.number, 'undertime', $event.target.value)"
                                                :class="[
                                                    'w-full max-w-[80px] px-1.5 py-0.5 text-xs font-extrabold rounded border shadow-2xs text-center cursor-pointer transition-all appearance-none outline-none focus:ring-2 focus:ring-teal-400/50',
                                                    props.undertimeMatrix[m.id]?.months[mObj.number] === 'ON-LEAVE'
                                                        ? 'text-purple-700 bg-purple-50 border-purple-200 text-[9px]'
                                                        : (Number(props.undertimeMatrix[m.id]?.months[mObj.number]) > 0 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-slate-400 bg-slate-50/50 border-slate-200 hover:border-slate-300')
                                                ]"
                                            >
                                                <option v-for="opt in getNumericOptions(props.undertimeMatrix[m.id]?.months[mObj.number])" :key="'u-'+opt" :value="opt">{{ opt }}</option>
                                                <option value="ON-LEAVE">ON-LEAVE</option>
                                            </select>
                                            <span v-else :class="props.undertimeMatrix[m.id]?.months[mObj.number] === 'ON-LEAVE' ? 'text-purple-700 font-bold text-[9px]' : (Number(props.undertimeMatrix[m.id]?.months[mObj.number]) > 0 ? 'text-amber-700 font-extrabold' : 'text-slate-400')">
                                                {{ props.undertimeMatrix[m.id]?.months[mObj.number] ?? 0 }}
                                            </span>
                                        </td>

                                        <!-- Total Undertime -->
                                        <td class="p-3 text-center font-extrabold bg-orange-50/50 text-amber-900">
                                            {{ props.undertimeMatrix[m.id]?.total_undertime ?? 0 }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Activity Modal (Add / Edit) -->
        <ActivityFormModal
            :show="props.canEdit && showActivityModal"
            :editing-activity="editingActivity"
            :is-saving="isSaving"
            @close="showActivityModal = false"
            @submit="handleActivitySubmit"
        />

        <!-- Delete Activity Confirmation Modal -->
        <DeleteActivityModal
            :show="props.canEdit && showDeleteConfirmModal"
            :activity-name="activityToDelete?.name || ''"
            :is-saving="isSaving"
            @close="showDeleteConfirmModal = false"
            @confirm="deleteActivity"
        />
    </AppLayout>
</template>
