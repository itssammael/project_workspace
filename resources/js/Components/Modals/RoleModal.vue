<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    mode: {
        type: String,
        default: 'create' // 'create' or 'edit'
    },
    form: {
        type: Object,
        required: true
    },
    availablePermissions: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'submit']);

const permSearch = ref('');
const customPermInput = ref('');
const customPermError = ref('');
const isSlugManuallyEdited = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        permSearch.value = '';
        customPermInput.value = '';
        customPermError.value = '';
        isSlugManuallyEdited.value = props.mode === 'edit';
    }
});

const handleNameInput = () => {
    if (props.mode === 'create' && !isSlugManuallyEdited.value) {
        props.form.slug = (props.form.name || '')
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '_')
            .replace(/^-+|-+$/g, '');
    }
};

const handleSlugManualEdit = () => {
    isSlugManuallyEdited.value = true;
};

// Check if a permission is selected
const isPermSelected = (permKey) => {
    return Array.isArray(props.form.permissions) && props.form.permissions.includes(permKey);
};

// Toggle a permission
const togglePerm = (permKey) => {
    if (!Array.isArray(props.form.permissions)) {
        props.form.permissions = [];
    }
    const idx = props.form.permissions.indexOf(permKey);
    if (idx > -1) {
        props.form.permissions.splice(idx, 1);
    } else {
        props.form.permissions.push(permKey);
    }
};

// Select all permissions in catalog
const selectAllPermissions = () => {
    const allKeys = props.availablePermissions.map(p => p.key);
    const set = new Set([...props.form.permissions, ...allKeys]);
    props.form.permissions = Array.from(set);
};

// Clear all permissions
const clearAllPermissions = () => {
    props.form.permissions = [];
};

// Category helpers
const categorizedPermissions = computed(() => {
    const search = permSearch.value.trim().toLowerCase();
    const groups = {};

    props.availablePermissions.forEach(perm => {
        const matches = !search || 
            perm.name.toLowerCase().includes(search) || 
            perm.key.toLowerCase().includes(search) || 
            (perm.description && perm.description.toLowerCase().includes(search)) ||
            (perm.category && perm.category.toLowerCase().includes(search));

        if (matches) {
            const cat = perm.category || 'General';
            if (!groups[cat]) {
                groups[cat] = [];
            }
            groups[cat].push(perm);
        }
    });

    return groups;
});

// Category selection toggles
const selectCategory = (categoryName) => {
    const catPerms = props.availablePermissions.filter(p => (p.category || 'General') === categoryName).map(p => p.key);
    const set = new Set([...props.form.permissions, ...catPerms]);
    props.form.permissions = Array.from(set);
};

const deselectCategory = (categoryName) => {
    const catPerms = new Set(props.availablePermissions.filter(p => (p.category || 'General') === categoryName).map(p => p.key));
    props.form.permissions = props.form.permissions.filter(k => !catPerms.has(k));
};

const countSelectedInCategory = (categoryName) => {
    const catPerms = props.availablePermissions.filter(p => (p.category || 'General') === categoryName).map(p => p.key);
    return catPerms.filter(k => isPermSelected(k)).length;
};

const totalInCategory = (categoryName) => {
    return props.availablePermissions.filter(p => (p.category || 'General') === categoryName).length;
};

// Custom permissions not in catalog
const customGrantedPermissions = computed(() => {
    const catalogKeys = new Set(props.availablePermissions.map(p => p.key));
    return (props.form.permissions || []).filter(k => !catalogKeys.has(k));
});

const addCustomPermission = () => {
    const trimmed = customPermInput.value.trim().toLowerCase().replace(/\s+/g, '.');
    if (!trimmed) {
        customPermError.value = 'Please provide a valid permission identifier (e.g., audit.export).';
        return;
    }
    if (props.form.permissions.includes(trimmed)) {
        customPermError.value = 'This permission is already assigned.';
        return;
    }
    props.form.permissions.push(trimmed);
    customPermInput.value = '';
    customPermError.value = '';
};

const removeCustomPermission = (permKey) => {
    togglePerm(permKey);
};

const getCategoryBadgeClass = (category) => {
    switch (category) {
        case 'Administration & Security':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'Dashboard & Workspaces':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'Tasks & Workflows':
            return 'bg-teal-50 text-teal-700 border-teal-200';
        case 'Reports & Activities':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const isCoreAdmin = computed(() => {
    return props.mode === 'edit' && props.form.slug === 'admin';
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

        <!-- Modal Container -->
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden max-w-4xl w-full z-10 transform transition-all flex flex-col max-h-[92vh]">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-100/80 text-[#0D9488] flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">
                            {{ mode === 'create' ? 'Create New System Role' : `Edit Role: ${form.name}` }}
                        </h3>
                        <p class="text-xs text-slate-500">Configure role definition, internal identifier, and assigned functional permissions.</p>
                    </div>
                </div>
                <button 
                    @click="$emit('close')" 
                    class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                    aria-label="Close modal"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form @submit.prevent="$emit('submit')" class="p-6 space-y-6 overflow-y-auto flex-1 text-slate-800">
                
                <!-- Errors Summary -->
                <div v-if="Object.keys(form.errors).length > 0" class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Please correct the following errors:
                    </p>
                    <ul class="list-disc list-inside ps-2 space-y-0.5">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <!-- Basic Info Card -->
                <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-200/60 pb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#0D9488]"></span>
                            Role Identification
                        </h4>
                        <span class="text-[11px] text-slate-500 font-medium">* Required fields</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Role Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Role Display Name <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                v-model="form.name"
                                @input="handleNameInput"
                                type="text"
                                placeholder="e.g., Compliance Auditor, Project Lead"
                                class="w-full text-xs font-medium px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#0D9488] focus:border-transparent transition"
                                required
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Human-readable name displayed across the system.</p>
                        </div>

                        <!-- Role Slug -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-700">
                                    Role Slug / Key <span class="text-rose-500">*</span>
                                </label>
                                <span v-if="isCoreAdmin" class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200">
                                    Core Admin (Protected)
                                </span>
                            </div>
                            <input 
                                v-model="form.slug"
                                @input="handleSlugManualEdit"
                                :disabled="isCoreAdmin"
                                type="text"
                                placeholder="e.g., compliance_auditor"
                                :class="[
                                    'w-full text-xs font-mono font-medium px-3.5 py-2.5 border rounded-xl transition',
                                    isCoreAdmin 
                                        ? 'bg-slate-100 border-slate-200 text-slate-500 cursor-not-allowed' 
                                        : 'bg-white border-slate-200 text-slate-800 focus:ring-2 focus:ring-[#0D9488] focus:border-transparent'
                                ]"
                                required
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Unique snake_case identifier used in route and policy checks.</p>
                        </div>
                    </div>
                </div>

                <!-- Header Role Access Switcher Option -->
                <div class="bg-gradient-to-r from-teal-50/70 via-slate-50/50 to-white border border-teal-200/80 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-teal-100/80 text-[#0D9488] flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h5 class="text-xs font-bold text-slate-800">Header Role Access Switcher Menu</h5>
                                <span :class="['px-2 py-0.5 text-[10px] font-bold rounded-md uppercase tracking-wider', form.show_role_switcher ? 'bg-teal-100 text-[#0F766E] border border-teal-200' : 'bg-slate-200 text-slate-600 border border-slate-300']">
                                    {{ form.show_role_switcher ? 'Visible in Header' : 'Hidden in Header' }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                When enabled, users assigned to this role can view and access the Role switcher menu in the top navigation header.
                            </p>
                        </div>
                    </div>

                    <!-- Switcher Toggle -->
                    <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                        <input 
                            type="checkbox" 
                            v-model="form.show_role_switcher" 
                            class="sr-only peer"
                        />
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#0D9488]"></div>
                    </label>
                </div>

                <!-- Granular Permissions Matrix -->
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Access Permissions Matrix</h4>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-[#0F766E] border border-teal-200">
                                    {{ form.permissions ? form.permissions.length : 0 }} Assigned
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Toggle granular operational abilities granted to this system role.</p>
                        </div>

                        <!-- Global Matrix Controls -->
                        <div class="flex items-center gap-2">
                            <button 
                                type="button" 
                                @click="selectAllPermissions"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
                            >
                                Select All
                            </button>
                            <button 
                                type="button" 
                                @click="clearAllPermissions"
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
                            >
                                Clear All
                            </button>
                        </div>
                    </div>

                    <!-- Search Filter -->
                    <div class="relative">
                        <svg class="w-4 h-4 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            v-model="permSearch"
                            type="text"
                            placeholder="Search permissions by keyword or action (e.g. provision, export, tasks)..."
                            class="w-full text-xs pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#0D9488] focus:border-transparent transition"
                        />
                    </div>

                    <!-- Permissions by Category -->
                    <div class="space-y-4">
                        <div 
                            v-for="(perms, categoryName) in categorizedPermissions" 
                            :key="categoryName"
                            class="border border-slate-200/90 rounded-2xl overflow-hidden bg-white shadow-xs"
                        >
                            <!-- Category Header -->
                            <div class="px-4 py-2.5 bg-slate-50/80 border-b border-slate-200/80 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span :class="['px-2.5 py-0.5 text-[11px] font-bold rounded-md border uppercase tracking-wider', getCategoryBadgeClass(categoryName)]">
                                        {{ categoryName }}
                                    </span>
                                    <span class="text-xs text-slate-500 font-medium">
                                        ({{ countSelectedInCategory(categoryName) }} / {{ totalInCategory(categoryName) }} enabled)
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button 
                                        type="button" 
                                        @click="selectCategory(categoryName)"
                                        class="px-2 py-1 text-[11px] font-semibold text-teal-700 hover:bg-teal-50 rounded-lg transition cursor-pointer"
                                    >
                                        Enable All
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button 
                                        type="button" 
                                        @click="deselectCategory(categoryName)"
                                        class="px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>

                            <!-- Category Perms Grid -->
                            <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2.5">
                                <div 
                                    v-for="perm in perms" 
                                    :key="perm.key"
                                    @click="togglePerm(perm.key)"
                                    :class="[
                                        'p-3 rounded-xl border text-left cursor-pointer transition select-none flex items-start gap-3',
                                        isPermSelected(perm.key)
                                            ? 'bg-teal-50/50 border-teal-300 ring-1 ring-teal-300 shadow-xs'
                                            : 'bg-white border-slate-200/70 hover:border-slate-300 hover:bg-slate-50/40'
                                    ]"
                                >
                                    <input 
                                        type="checkbox"
                                        :checked="isPermSelected(perm.key)"
                                        class="mt-0.5 rounded text-[#0D9488] focus:ring-[#0D9488] border-slate-300 cursor-pointer"
                                        @click.stop="togglePerm(perm.key)"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs font-bold text-slate-800 truncate">{{ perm.name }}</span>
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded border border-slate-200/80 shrink-0">
                                                {{ perm.key }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-1 leading-snug line-clamp-2">
                                            {{ perm.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Search Results -->
                        <div v-if="Object.keys(categorizedPermissions).length === 0" class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                            <p class="text-xs font-bold text-slate-600">No permissions match your filter keyword.</p>
                            <p class="text-[11px] text-slate-400 mt-1">Try another term or clear the search field.</p>
                        </div>
                    </div>

                    <!-- Custom Permissions Adder -->
                    <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <h5 class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Custom Permission Key
                                </h5>
                                <p class="text-[11px] text-slate-500">Need a unique permission string not in the standard catalog? Type it below to assign.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <input 
                                v-model="customPermInput"
                                @keydown.enter.prevent="addCustomPermission"
                                type="text"
                                placeholder="e.g. audit.export, telemetry.view"
                                class="flex-1 text-xs font-mono px-3.5 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#0D9488] focus:border-transparent transition"
                            />
                            <button 
                                type="button" 
                                @click="addCustomPermission"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition shadow-xs cursor-pointer shrink-0"
                            >
                                + Add Permission
                            </button>
                        </div>

                        <p v-if="customPermError" class="text-[11px] font-semibold text-rose-600">{{ customPermError }}</p>

                        <!-- Custom granted pills list -->
                        <div v-if="customGrantedPermissions.length > 0" class="pt-2 border-t border-slate-200/60">
                            <span class="text-[11px] font-bold text-slate-600 block mb-1.5">Custom Permissions Assigned:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <span 
                                    v-for="customKey in customGrantedPermissions" 
                                    :key="customKey"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-mono font-medium"
                                >
                                    {{ customKey }}
                                    <button 
                                        type="button" 
                                        @click="removeCustomPermission(customKey)"
                                        class="text-indigo-400 hover:text-indigo-600 hover:bg-indigo-100 rounded p-0.5 transition cursor-pointer"
                                        title="Remove custom permission"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 sticky bottom-0 bg-white py-2">
                    <button 
                        type="button" 
                        @click="$emit('close')"
                        class="px-5 py-2.5 text-xs font-bold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        :disabled="form.processing"
                        class="px-6 py-2.5 text-xs font-bold text-white bg-[#0D9488] hover:bg-[#0f766e] disabled:opacity-50 rounded-xl shadow-sm transition flex items-center gap-2 cursor-pointer"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ mode === 'create' ? 'Create System Role' : 'Save Changes' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
