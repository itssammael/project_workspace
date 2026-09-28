<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    mode: {
        type: String,
        default: 'create'
    },
    form: {
        type: Object,
        required: true
    },
    pages: {
        type: Array,
        default: () => []
    },
    roles: {
        type: Array,
        default: () => []
    },
    memberRoles: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'submit', 'import-rules']);

const fileInput = ref(null);
const isDragging = ref(false);
const importError = ref('');
const importSuccess = ref('');
const importedRulesList = ref([]);
const selectedRuleIndex = ref(0);

const clearImportFeedback = () => {
    importError.value = '';
    importSuccess.value = '';
    importedRulesList.value = [];
    selectedRuleIndex.value = 0;
    isDragging.value = false;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

watch(() => props.show, (newVal) => {
    if (!newVal) {
        clearImportFeedback();
    }
});

const triggerFileInput = () => {
    importError.value = '';
    fileInput.value?.click();
};

const onFileChange = (e) => {
    const file = e.target.files?.[0];
    if (file) {
        handleFile(file);
    }
};

const onDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) {
        handleFile(file);
    }
};

const handleFile = (file) => {
    importError.value = '';
    importSuccess.value = '';

    if (!file.name.toLowerCase().endsWith('.json') && file.type !== 'application/json') {
        importError.value = 'Please select a valid .json file.';
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const content = e.target.result;
            const parsed = JSON.parse(content);
            processParsedJSON(parsed, file.name);
        } catch (err) {
            importError.value = 'Failed to parse JSON file: ' + err.message;
        }
    };
    reader.onerror = () => {
        importError.value = 'Failed to read the selected file.';
    };
    reader.readAsText(file);
};

const processParsedJSON = (parsed, fileName) => {
    let rules = [];
    if (Array.isArray(parsed)) {
        rules = parsed;
    } else if (parsed && typeof parsed === 'object') {
        if (Array.isArray(parsed.rules)) {
            rules = parsed.rules;
        } else {
            rules = [parsed];
        }
    }

    // Filter valid rule objects
    const validRules = rules.filter(r => r && typeof r === 'object' && (r.name || r.type || r.rule_logic));

    if (validRules.length === 0) {
        importError.value = 'No valid rule definitions found in the JSON file. Ensure each rule contains at least a name or type.';
        return;
    }

    importedRulesList.value = validRules;
    selectedRuleIndex.value = 0;
    applyRuleToForm(validRules[0]);

    if (validRules.length === 1) {
        importSuccess.value = `Successfully imported "${validRules[0].name || 'Rule'}" from ${fileName}. The form fields below have been populated.`;
    } else {
        importSuccess.value = `Loaded ${validRules.length} rules from ${fileName}. Rule 1 ("${validRules[0].name || 'Rule 1'}") is currently populated in the builder below.`;
    }
};

const selectImportedRule = (index) => {
    if (importedRulesList.value[index]) {
        selectedRuleIndex.value = index;
        applyRuleToForm(importedRulesList.value[index]);
    }
};

const applyRuleToForm = (rule) => {
    if (!rule || typeof rule !== 'object') return;

    if (rule.name !== undefined) props.form.name = rule.name;
    if (rule.type !== undefined) props.form.type = rule.type;
    if (rule.status !== undefined) props.form.status = rule.status;
    if (rule.enabled !== undefined) props.form.enabled = Boolean(rule.enabled);
    if (rule.description !== undefined) props.form.description = rule.description || '';
    if (Array.isArray(rule.scope)) props.form.scope = [...rule.scope];
    if (Array.isArray(rule.actions)) props.form.actions = [...rule.actions];

    if (rule.rule_logic && typeof rule.rule_logic === 'object') {
        props.form.rule_logic = JSON.parse(JSON.stringify(rule.rule_logic));
    } else if (!props.form.rule_logic) {
        props.form.rule_logic = {};
    }

    if (props.form.rule_logic.allowed_system_roles && !Array.isArray(props.form.rule_logic.allowed_system_roles)) {
        props.form.rule_logic.allowed_system_roles = [props.form.rule_logic.allowed_system_roles];
    }
    if (props.form.rule_logic.allowed_functional_roles && !Array.isArray(props.form.rule_logic.allowed_functional_roles)) {
        props.form.rule_logic.allowed_functional_roles = [props.form.rule_logic.allowed_functional_roles];
    }
};

const importAllDirectly = () => {
    if (importedRulesList.value.length > 0) {
        emit('import-rules', importedRulesList.value);
    }
};

const ruleTypeOptions = [
    { value: 'page_access_rule', label: 'Page & Route Access Restriction', category: 'system_wide_access', desc: 'Control page view permissions and route access restrictions' },
    { value: 'role_permission_rule', label: 'System & Functional Role Permissions', category: 'system_wide_access', desc: 'Enforce allowed/prohibited operational actions by role' },
    { value: 'data_integrity_rule', label: 'Data Security & Scope Limits', category: 'system_wide_access', desc: 'Enforce user provisioning limits and profile guards' },
    { value: 'conditional_logic', label: 'Conditional Logic', category: 'workspace_governance', desc: 'Show/hide/enable/disable fields dynamically based on criteria' },
    { value: 'validation_rule', label: 'Form Data Validation', category: 'workspace_governance', desc: 'Validate input data format and enforce submission constraints' },
    { value: 'approval_rule', label: 'Approval Hierarchy Chain', category: 'workspace_governance', desc: 'Route requests through role approval chains' },
    { value: 'notification_rule', label: 'Escalation & Notification Alert', category: 'workspace_governance', desc: 'Dispatch in-app or email notifications on system triggers' },
    { value: 'compliance_rule', label: 'Regulatory & Sector Compliance', category: 'workspace_governance', desc: 'Enforce sector-specific compliance standards (ISO, Environmental)' }
];

const availableModules = ['Projects', 'Tasks', 'Users', 'Reports', 'Sections', 'System Settings', 'Admin Management'];

const formFields = [
    'Project Name', 'Project Type', 'Status', 'Completion Date', 'Start Date', 'End Date',
    'Priority', 'Department', 'Task Name', 'Due Date', 'Risk Level', 'Assigned Section'
];

const systemRoleOptions = [
    { slug: 'admin', label: 'Administrator' },
    { slug: 'user', label: 'User' },
    { slug: 'viewer', label: 'Viewer' }
];

const functionalRoleOptions = [
    { slug: 'department_head', label: 'Department Head' },
    { slug: 'project_manager', label: 'Project Manager' },
    { slug: 'admin_staff', label: 'Admin Staff' },
    { slug: 'lead_developer', label: 'Lead Developer' },
    { slug: 'developer', label: 'Developer' },
    { slug: 'ui_ux_designer', label: 'UI/UX Designer' }
];

const routeOptions = [
    { route: '/admin/management', label: 'Admin Management (/admin/management)' },
    { route: '/admin/settings', label: 'System Settings & Rules (/admin/settings)' },
    { route: '/projects/create', label: 'Project Creation Form (/projects/create)' },
    { route: '/projects', label: 'Projects Workspace Index (/projects)' },
    { route: '/tasks', label: 'Task Boards & Kanban (/tasks)' }
];

const operatorOptions = ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'greater_than', 'less_than', 'is_after', 'is_before'];

const availableActions = [
    { value: 'show_field', label: 'Show Field', category: 'Conditional Logic' },
    { value: 'hide_field', label: 'Hide Field', category: 'Conditional Logic' },
    { value: 'enable_field', label: 'Enable Field', category: 'Conditional Logic' },
    { value: 'disable_field', label: 'Disable Field', category: 'Conditional Logic' },
    { value: 'show_error_message', label: 'Show Error Message', category: 'Validation' },
    { value: 'reject_submission', label: 'Reject Submission', category: 'Validation / Security' },
    { value: 'route_for_approval', label: 'Route for Approval', category: 'Approval' },
    { value: 'send_email_notification', label: 'Send Email Notification', category: 'Notification' },
    { value: 'send_in_app_notification', label: 'Send In-App Notification', category: 'Notification' },
    { value: 'log_critical_event', label: 'Log Critical Event', category: 'Compliance / Audit' }
];

const addSubCondition = (conditionGroup) => {
    if (!conditionGroup.conditions) conditionGroup.conditions = [];
    conditionGroup.conditions.push({ field: 'Status', operator: 'equals', value: 'In Progress' });
};

const removeCondition = (conditionsArr, index) => {
    conditionsArr.splice(index, 1);
};

const addApprovalStep = () => {
    if (!props.form.rule_logic.approval_chain) props.form.rule_logic.approval_chain = [];
    props.form.rule_logic.approval_chain.push('Department Head');
};

const removeApprovalStep = (index) => {
    props.form.rule_logic.approval_chain.splice(index, 1);
};

const insertErrorMessageVar = (varName) => {
    props.form.rule_logic.error_message = (props.form.rule_logic.error_message || '') + ` [${varName}]`;
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden max-w-3xl w-full z-10 transform transition-all flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">
                            {{ mode === 'create' ? 'Add New System Rule' : 'Edit System Rule' }}
                        </h3>
                        <p class="text-xs text-slate-500">Configure System-Wide Access & Security or Workspace Governance Rule parameters.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button 
                            v-if="mode === 'create'"
                            type="button" 
                            @click="triggerFileInput"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 text-xs font-bold rounded-xl transition shadow-xs cursor-pointer"
                            title="Import rule configuration from a JSON file"
                        >
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>Import JSON File</span>
                        </button>
                        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition cursor-pointer">✕</button>
                    </div>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-6 overflow-y-auto flex-1">
                    <!-- Hidden file input -->
                    <input 
                        ref="fileInput" 
                        type="file" 
                        accept=".json,application/json" 
                        class="hidden" 
                        @change="onFileChange" 
                    />

                    <!-- JSON Import Dropzone / Banner (Shown in Create mode) -->
                    <div 
                        v-if="mode === 'create'"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                        :class="[
                            'border-2 border-dashed rounded-2xl p-4 transition-all',
                            isDragging ? 'border-emerald-500 bg-emerald-50/70 scale-[1.01]' : 'border-slate-200 bg-slate-50/60 hover:bg-slate-50 hover:border-emerald-300'
                        ]"
                    >
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-100/80 text-emerald-700 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-800">Import Rule Configuration from JSON</h4>
                                    <p class="text-[11px] text-slate-500">Drop a JSON rule file here or browse to auto-fill all rule parameters.</p>
                                </div>
                            </div>
                            <button 
                                type="button" 
                                @click="triggerFileInput" 
                                class="shrink-0 px-3.5 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 hover:border-emerald-400 text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
                            >
                                Browse JSON File
                            </button>
                        </div>

                        <!-- Success Alert & Multi-rule selector -->
                        <div v-if="importSuccess" class="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2 text-xs text-emerald-800 font-semibold">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>{{ importSuccess }}</span>
                                </div>
                                <button type="button" @click="clearImportFeedback" class="text-emerald-500 hover:text-emerald-700 text-xs cursor-pointer">✕</button>
                            </div>

                            <div v-if="importedRulesList.length > 1" class="flex flex-wrap items-center gap-2 pt-2 border-t border-emerald-200/60">
                                <span class="text-[11px] font-bold text-emerald-800">Available Rules ({{ importedRulesList.length }}):</span>
                                <button 
                                    v-for="(r, idx) in importedRulesList" 
                                    :key="idx" 
                                    type="button" 
                                    @click="selectImportedRule(idx)"
                                    :class="[
                                        'px-2.5 py-1 text-[11px] font-bold rounded-lg transition border cursor-pointer',
                                        selectedRuleIndex === idx ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-emerald-800 border-emerald-300 hover:bg-emerald-100'
                                    ]"
                                >
                                    {{ r.name || `Rule #${idx + 1}` }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="importAllDirectly"
                                    class="ms-auto px-3 py-1 bg-teal-600 hover:bg-teal-700 text-white text-[11px] font-bold rounded-lg shadow-xs transition flex items-center gap-1 cursor-pointer"
                                >
                                    Import All {{ importedRulesList.length }} Rules Directly
                                </button>
                            </div>
                        </div>

                        <!-- Error Alert -->
                        <div v-if="importError" class="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-xl flex items-center justify-between gap-2 text-xs text-rose-700 font-medium">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ importError }}</span>
                            </div>
                            <button type="button" @click="importError = ''" class="text-rose-500 hover:text-rose-700 text-xs cursor-pointer">✕</button>
                        </div>
                    </div>
                    
                    <!-- Basic Meta & Category -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Rule Name</label>
                            <input type="text" v-model="form.name" required class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]" placeholder="e.g. Admin Management Page Access Restriction" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Rule Type</label>
                            <select v-model="form.type" required class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]">
                                <option v-for="t in ruleTypeOptions" :key="t.value" :value="t.value">
                                    {{ ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(t.value) ? '🛡️ [System-Wide]' : '📋 [Workspace]' }} {{ t.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                            <select v-model="form.status" class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Applicable Module Scope</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <label v-for="mod in availableModules" :key="mod" class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                    <input type="checkbox" :value="mod" v-model="form.scope" class="rounded text-[#0D9488] focus:ring-[#0D9488]" />
                                    {{ mod }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Description</label>
                        <textarea v-model="form.description" rows="2" class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]" placeholder="Detailed explanation of rule purpose and operational enforcement..."></textarea>
                    </div>

                    <!-- DYNAMIC BUILDER PANELS BY TYPE -->

                    <!-- 1. PAGE ACCESS RULE PANEL -->
                    <div v-if="form.type === 'page_access_rule'" class="border border-sky-200 rounded-2xl p-5 bg-sky-50/40 space-y-4">
                        <h4 class="text-xs font-bold text-sky-900 uppercase tracking-wider">🛡️ System-Wide Page View & Route Restriction Config</h4>
                        <div>
                            <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Target Page Route</label>
                            <select v-model="form.rule_logic.target_route" class="w-full rounded-xl border-sky-200 text-xs">
                                <option v-for="ro in routeOptions" :key="ro.route" :value="ro.route">{{ ro.label }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Permitted System Roles</label>
                                <div class="space-y-1">
                                    <label v-for="sr in systemRoleOptions" :key="sr.slug" class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" :value="sr.slug" v-model="form.rule_logic.allowed_system_roles" class="rounded text-sky-600" />
                                        {{ sr.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-28 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" :value="fr.slug" v-model="form.rule_logic.allowed_functional_roles" class="rounded text-sky-600" />
                                        {{ fr.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ROLE PERMISSION RULE PANEL -->
                    <div v-else-if="form.type === 'role_permission_rule'" class="border border-indigo-200 rounded-2xl p-5 bg-indigo-50/40 space-y-4">
                        <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">🛡️ System & Functional Role Operational Action Permissions</h4>
                        <div>
                            <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Operational Action</label>
                            <select v-model="form.rule_logic.operation" class="w-full rounded-xl border-indigo-200 text-xs">
                                <option value="create_user">Create User Accounts</option>
                                <option value="update_user">Update User Accounts & Roles</option>
                                <option value="delete_user">Delete User Accounts</option>
                                <option value="create_project">Create Task Boards</option>
                                <option value="update_project">Update Task Boards</option>
                                <option value="delete_project">Delete Task Boards</option>
                                <option value="manage_tasks">Allocate Tasks & Workflows</option>
                                <option value="manage_sections">Manage Sections</option>
                                <option value="manage_workflows">Manage Workflows</option>
                                <option value="manage_functional_roles">Manage Functional Roles</option>
                                <option value="view_all_projects_dashboard">View All Task Boards on Dashboard</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-2 border-t border-indigo-200/60">
                            <div>
                                <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Permitted System Roles</label>
                                <div class="space-y-1">
                                    <label v-for="sr in systemRoleOptions" :key="sr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="sr.slug" v-model="form.rule_logic.allowed_system_roles" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        <span>{{ sr.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="fr.slug" v-model="form.rule_logic.allowed_functional_roles" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        <span>{{ fr.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Target Error / Restriction Message</label>
                            <input type="text" v-model="form.rule_logic.error_message" class="w-full rounded-xl border-indigo-200 text-xs" placeholder="e.g. Only authorized roles can perform this action." />
                        </div>
                    </div>

                    <!-- 3. CONDITIONAL LOGIC BUILDER -->
                    <div v-else-if="form.type === 'conditional_logic'" class="border border-teal-200 rounded-2xl p-5 bg-teal-50/20 space-y-4">
                        <h4 class="text-xs font-bold text-[#0F766E] uppercase tracking-wider">📋 Form Conditional Logic Builder</h4>
                        <div class="bg-slate-900 p-4 rounded-xl text-white text-xs space-y-3 font-mono">
                            <div class="flex items-center justify-between text-teal-300 font-bold">
                                <span>IF CONDITIONS:</span>
                                <button type="button" @click="addSubCondition(form.rule_logic)" class="px-2 py-1 bg-teal-600 text-white rounded text-[10px] uppercase font-sans">+ Add Condition</button>
                            </div>
                            
                            <div v-for="(cond, cidx) in (form.rule_logic.conditions || [])" :key="cidx" class="pl-2 border-l-2 border-teal-500 space-y-2">
                                <div v-if="cond.field" class="flex flex-wrap items-center gap-2">
                                    <select v-model="cond.field" class="bg-slate-800 border-slate-700 text-amber-300 text-xs rounded p-1.5">
                                        <option v-for="f in formFields" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                    <select v-model="cond.operator" class="bg-slate-800 border-slate-700 text-sky-300 text-xs rounded p-1.5">
                                        <option v-for="op in operatorOptions" :key="op" :value="op">{{ op }}</option>
                                    </select>
                                    <input type="text" v-model="cond.value" class="bg-slate-800 border-slate-700 text-emerald-300 text-xs rounded p-1.5 flex-1" placeholder="Value..." />
                                    <button type="button" @click="removeCondition(form.rule_logic.conditions, cidx)" class="text-rose-400">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. VALIDATION RULE BUILDER -->
                    <div v-else-if="form.type === 'validation_rule'" class="border border-amber-200 rounded-2xl p-5 bg-amber-50/40 space-y-3">
                        <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">📋 Form Validation Rule Builder</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Field to Validate</label>
                                <select v-model="form.rule_logic.field" class="w-full rounded-xl border-slate-200 text-xs">
                                    <option v-for="f in formFields" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Validation Constraint</label>
                                <select v-model="form.rule_logic.validation_type" class="w-full rounded-xl border-slate-200 text-xs">
                                    <option value="required">Required Field</option>
                                    <option value="min_length">Minimum Character Length</option>
                                    <option value="numeric">Must be Numeric</option>
                                    <option value="date_range">Valid Date Range</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Custom Error Message Template</label>
                            <textarea v-model="form.rule_logic.error_message" rows="2" class="w-full rounded-xl border-slate-200 font-mono text-xs" placeholder="e.g. Project Name [Project Name] must be at least 5 characters long."></textarea>
                            <div class="flex items-center gap-2 mt-1">
                                <button type="button" @click="insertErrorMessageVar('Project Name')" class="px-2 py-0.5 bg-slate-200 text-[10px] font-bold rounded">[Project Name]</button>
                                <button type="button" @click="insertErrorMessageVar('Field Name')" class="px-2 py-0.5 bg-slate-200 text-[10px] font-bold rounded">[Field Name]</button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. APPROVAL RULE BUILDER -->
                    <div v-else-if="form.type === 'approval_rule'" class="border border-teal-200 rounded-2xl p-5 bg-teal-50/40 space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Target Operational Action</label>
                            <select v-model="form.rule_logic.operation" class="w-full rounded-xl border-teal-200 text-xs">
                                <option value="create_project">Create Task Boards (Task Board Creation Authority)</option>
                                <option value="manage_tasks">Allocate Tasks & Workflows (Section Manager Authority)</option>
                                <option value="update_project">Update Task Boards</option>
                                <option value="create_user">Create User Accounts</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-teal-900 uppercase tracking-wider">📋 Approval Hierarchy Chain</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5">Select and sequence System Roles & Functional Roles required for approval routing.</p>
                            </div>
                            <button type="button" @click="addApprovalStep" class="px-3 py-1.5 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Step
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-2 flex-wrap bg-white p-4 rounded-xl border border-slate-200 shadow-sm min-h-[56px]">
                            <template v-for="(step, sidx) in (form.rule_logic.approval_chain || [])" :key="sidx">
                                <div class="flex items-center gap-1.5 bg-[#F0FDFA] border border-teal-300 pl-3 pr-1 py-1 rounded-xl shadow-xs">
                                    <select 
                                        v-model="form.rule_logic.approval_chain[sidx]" 
                                        class="bg-transparent border-none text-[#0D9488] font-bold text-xs p-0 pe-6 focus:ring-0 cursor-pointer"
                                    >
                                        <optgroup label="System Roles">
                                            <option v-for="r in (roles.length ? roles : [{name: 'Administrator'}, {name: 'User'}, {name: 'Viewer'}])" :key="r.name" :value="r.name">{{ r.name }}</option>
                                        </optgroup>
                                        <optgroup label="Functional Roles">
                                            <option v-for="mr in (memberRoles.length ? memberRoles : [{name: 'Department Head'}, {name: 'Project Manager'}, {name: 'Admin Staff'}, {name: 'Lead Developer'}, {name: 'Developer'}, {name: 'UI/UX Designer'}])" :key="mr.name" :value="mr.name">{{ mr.name }}</option>
                                        </optgroup>
                                    </select>
                                    <button type="button" @click="removeApprovalStep(sidx)" class="p-1 text-teal-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Remove Step">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <span v-if="sidx < (form.rule_logic.approval_chain?.length - 1)" class="text-teal-400 font-bold text-base">→</span>
                            </template>
                            <div v-if="!form.rule_logic.approval_chain || form.rule_logic.approval_chain.length === 0" class="text-xs text-slate-400 italic">
                                No approval steps configured. Click "+ Add Step" to add a role to the chain.
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-2 border-t border-teal-200/60">
                            <div>
                                <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Permitted System Roles</label>
                                <div class="space-y-1">
                                    <label v-for="sr in systemRoleOptions" :key="sr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="sr.slug" v-model="form.rule_logic.allowed_system_roles" class="rounded text-teal-600 focus:ring-teal-500" />
                                        <span>{{ sr.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="fr.slug" v-model="form.rule_logic.allowed_functional_roles" class="rounded text-teal-600 focus:ring-teal-500" />
                                        <span>{{ fr.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Checkboxes -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Actions Performed When Rule Applies</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 border border-slate-200 rounded-xl p-3 bg-slate-50 max-h-36 overflow-y-auto">
                            <label v-for="act in availableActions" :key="act.value" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                <input type="checkbox" :value="act.value" v-model="form.actions" class="rounded text-[#0D9488] focus:ring-[#0D9488]" />
                                <span>{{ act.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="$emit('close')" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-5 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl shadow-sm transition">
                            Save System Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
