<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: Object,
    systemRules: {
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

// Active tab ('rules' or 'general') - Defaults to 'rules' per UI specification
const activeTab = ref('rules');

// System Rules Domain Filter ('all', 'system_wide', 'workspace')
const activeDomainFilter = ref('all');

// General Settings Form
const generalForm = useForm({
    system_name: props.settings.system_name || 'Project Tracker',
    theme: props.settings.theme || 'corporate_teal',
    logo: null,
    remove_logo: false,
});

const previewUrl = ref(props.settings.logo || null);
const successMessage = ref('');
const fileInput = ref(null);

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        generalForm.logo = file;
        previewUrl.value = URL.createObjectURL(file);
        generalForm.remove_logo = false;
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleRemoveLogo = () => {
    generalForm.logo = null;
    generalForm.remove_logo = true;
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submitGeneralSettings = () => {
    generalForm.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess('General settings saved successfully!');
        },
    });
};

const presets = [
    {
        id: 'refined_indigo',
        name: 'Refined Indigo & Slate',
        desc: 'Rich deep indigo buttons, slate dark text, and soft iris active states.',
        colors: { primary: '#4338CA', secondary: '#EEF2FF', text: '#1E293B', bg: '#F8FAFC' }
    },
    {
        id: 'corporate_teal',
        name: 'Corporate Teal & Charcoal',
        desc: 'Highly professional deep teal, soft mint highlights, and dark charcoal text.',
        colors: { primary: '#0D9488', secondary: '#F0FDFA', text: '#0F172A', bg: '#F1F5F9' }
    },
    {
        id: 'modern_midnight',
        name: 'Modern Midnight & Obsidian',
        desc: 'Neon violet accents against Obsidian cards and Midnight dark backgrounds.',
        colors: { primary: '#7C3AED', secondary: '#1E1E2F', text: '#F3F4F6', bg: '#12121A' }
    }
];

// ==========================================
// SYSTEM RULES ENGINE STATE & OPTIONS
// ==========================================

const ruleSearch = ref('');
const ruleTypeFilter = ref('');
const ruleScopeFilter = ref('');
const ruleStatusFilter = ref('');
const selectedRuleIds = ref([]);

const isRuleModalOpen = ref(false);
const ruleModalMode = ref('create'); // 'create' or 'edit'
const isPreviewModalOpen = ref(false);
const previewingRule = ref(null);
const isImportModalOpen = ref(false);
const importJsonText = ref('');
const isDeleteModalOpen = ref(false);
const ruleToDelete = ref(null);

// Rule Domain Categories & Types Specification
const ruleCategories = [
    { id: 'system_wide_access', label: 'System-Wide Access & Security', desc: 'System/Functional role permissions, allowed/prohibited actions, page & route access' },
    { id: 'workspace_governance', label: 'Workspace & Form Governance', desc: 'Conditional logic, validation checks, approval chains, escalation alerts, compliance rules' }
];

const ruleTypeOptions = [
    // System-Wide Access & Security Types
    { value: 'page_access_rule', label: 'Page & Route Access Restriction', category: 'system_wide_access', desc: 'Control page view permissions and route access restrictions' },
    { value: 'role_permission_rule', label: 'System & Functional Role Permissions', category: 'system_wide_access', desc: 'Enforce allowed/prohibited operational actions by role' },
    { value: 'data_integrity_rule', label: 'Data Security & Scope Limits', category: 'system_wide_access', desc: 'Enforce user provisioning limits and profile guards' },
    
    // Workspace & Form Governance Types
    { value: 'conditional_logic', label: 'Conditional Logic', category: 'workspace_governance', desc: 'Show/hide/enable/disable fields dynamically based on criteria' },
    { value: 'validation_rule', label: 'Form Data Validation', category: 'workspace_governance', desc: 'Validate input data format and enforce submission constraints' },
    { value: 'approval_rule', label: 'Approval Hierarchy Chain', category: 'workspace_governance', desc: 'Route requests through role approval chains' },
    { value: 'notification_rule', label: 'Escalation & Notification Alert', category: 'workspace_governance', desc: 'Dispatch in-app or email notifications on system triggers' },
    { value: 'compliance_rule', label: 'Regulatory & Sector Compliance', category: 'workspace_governance', desc: 'Enforce sector-specific compliance standards (ISO, Environmental)' }
];

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

const availableModules = ['Projects', 'Tasks', 'Users', 'Reports', 'Sections', 'System Settings', 'Admin Management'];

// Form fields excluding Budget per user directive
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

const ruleForm = useForm({
    id: null,
    name: '',
    type: 'page_access_rule',
    enabled: true,
    status: 'active',
    description: '',
    scope: ['System Settings'],
    actions: ['reject_submission'],
    rule_logic: {
        category: 'system_wide_access',
        // System-Wide Page Access Fields
        target_route: '/admin/settings',
        allowed_system_roles: ['admin'],
        allowed_functional_roles: ['admin_staff'],
        restriction_action: 'block_and_redirect',
        // System-Wide Role Permission Fields
        operation: 'create_user',
        enforced_for_functional_role: 'admin_staff',
        allowed_system_role_slug: 'user',
        prohibited_system_role_slugs: ['admin', 'viewer'],
        // Workspace Conditional Logic
        operator: 'AND',
        conditions: [
            { field: 'Project Type', operator: 'equals', value: 'Infrastructure' }
        ],
        then_show: ['Final Sign-off Form', 'As-Built Drawings'],
        then_hide: ['Sustainability Report'],
        // Validation Rule fields
        field: 'name',
        validation_type: 'min_length',
        min_length: 5,
        error_message: 'Project Name [Project Name] must be at least 5 characters long.',
        // Approval Rule fields
        approval_chain: ['Project Manager', 'Director', 'Mayor'],
        // Notification Rule fields
        event: 'task_overdue',
        channels: ['in_app', 'email'],
        recipients: ['Project Manager', 'Admins'],
        // Compliance Rule fields
        sector: 'Environment',
        standard: 'ISO 14001',
        requirements: ['Environmental Impact Assessment']
    },
    reason_for_change: ''
});

// Filtered system rules
const filteredRules = computed(() => {
    return props.systemRules.filter(r => {
        const isSystemWide = ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(r.type) ||
            r.rule_logic?.category === 'system_wide_access';
        const isWorkspace = !isSystemWide;

        const matchesDomain = activeDomainFilter.value === 'all' ||
            (activeDomainFilter.value === 'system_wide' && isSystemWide) ||
            (activeDomainFilter.value === 'workspace' && isWorkspace);

        const matchesSearch = !ruleSearch.value ||
            r.name.toLowerCase().includes(ruleSearch.value.toLowerCase()) ||
            (r.description && r.description.toLowerCase().includes(ruleSearch.value.toLowerCase())) ||
            r.type.toLowerCase().includes(ruleSearch.value.toLowerCase());

        const matchesType = !ruleTypeFilter.value || r.type === ruleTypeFilter.value;
        const matchesScope = !ruleScopeFilter.value || (r.scope && r.scope.includes(ruleScopeFilter.value));
        const matchesStatus = !ruleStatusFilter.value || r.status === ruleStatusFilter.value;

        return matchesDomain && matchesSearch && matchesType && matchesScope && matchesStatus;
    });
});

const showSuccess = (msg) => {
    successMessage.value = msg;
    setTimeout(() => {
        successMessage.value = '';
    }, 3500);
};

// Collapsible Cards State (Default view: collapsed)
const expandedRulesMap = ref({});

const isRuleExpanded = (ruleId) => Boolean(expandedRulesMap.value[ruleId]);

const toggleRuleCollapse = (ruleId) => {
    expandedRulesMap.value[ruleId] = !expandedRulesMap.value[ruleId];
};

const expandAllRules = () => {
    filteredRules.value.forEach(r => {
        expandedRulesMap.value[r.id] = true;
    });
};

const collapseAllRules = () => {
    expandedRulesMap.value = {};
};

const openAddRuleModal = () => {
    ruleForm.reset();
    ruleForm.id = null;
    ruleForm.rule_logic = {
        allowed_system_roles: ['admin'],
        allowed_functional_roles: ['department_head'],
    };
    ruleModalMode.value = 'create';
    isRuleModalOpen.value = true;
};

const openEditRuleModal = (rule) => {
    ruleForm.reset();
    ruleForm.id = rule.id;
    ruleForm.name = rule.name;
    ruleForm.type = rule.type;
    ruleForm.enabled = Boolean(rule.enabled);
    ruleForm.status = rule.status || 'active';
    ruleForm.description = rule.description || '';
    ruleForm.scope = rule.scope || ['Projects'];
    ruleForm.actions = rule.actions || ['show_field'];
    ruleForm.rule_logic = rule.rule_logic ? JSON.parse(JSON.stringify(rule.rule_logic)) : {};
    
    if (!Array.isArray(ruleForm.rule_logic.allowed_system_roles)) {
        ruleForm.rule_logic.allowed_system_roles = ['admin'];
    }
    if (!Array.isArray(ruleForm.rule_logic.allowed_functional_roles)) {
        ruleForm.rule_logic.allowed_functional_roles = ['department_head'];
    }

    ruleForm.reason_for_change = '';
    ruleModalMode.value = 'edit';
    isRuleModalOpen.value = true;
};

const submitRuleForm = () => {
    if (ruleModalMode.value === 'create') {
        ruleForm.post(route('admin.rules.store'), {
            onSuccess: () => {
                isRuleModalOpen.value = false;
                showSuccess('System rule created successfully.');
            }
        });
    } else {
        ruleForm.put(route('admin.rules.update', ruleForm.id), {
            onSuccess: () => {
                isRuleModalOpen.value = false;
                showSuccess('System rule updated successfully.');
            }
        });
    }
};

const toggleRuleState = (rule) => {
    useForm({}).post(route('admin.rules.toggle', rule.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSuccess(`Rule '${rule.name}' state toggled.`);
        }
    });
};

const cloneRule = (rule) => {
    useForm({}).post(route('admin.rules.clone', rule.id), {
        onSuccess: () => {
            showSuccess(`Rule '${rule.name}' cloned successfully.`);
        }
    });
};

const confirmDeleteRule = (rule) => {
    ruleToDelete.value = rule;
    isDeleteModalOpen.value = true;
};

const executeDeleteRule = () => {
    if (!ruleToDelete.value) return;
    useForm({}).delete(route('admin.rules.destroy', ruleToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            ruleToDelete.value = null;
            showSuccess('System rule deleted successfully.');
        }
    });
};

const openPreviewModal = (rule) => {
    previewingRule.value = rule;
    isPreviewModalOpen.value = true;
};

const exportSingleRule = (rule) => {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify([rule], null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `system_rule_${rule.name.toLowerCase().replace(/\s+/g, '_')}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
};

const exportAllRulesJSON = () => {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(props.systemRules, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `all_system_rules_${new Date().toISOString().slice(0,10)}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
};

const submitImportJSON = () => {
    try {
        const parsed = JSON.parse(importJsonText.value);
        const payload = Array.isArray(parsed) ? parsed : [parsed];
        useForm({ rules: payload }).post(route('admin.rules.import'), {
            onSuccess: () => {
                isImportModalOpen.value = false;
                importJsonText.value = '';
                showSuccess('System rules imported successfully.');
            }
        });
    } catch (e) {
        alert('Invalid JSON payload format. Please verify JSON syntax.');
    }
};

const getTypeBadgeStyle = (type) => {
    switch (type) {
        case 'page_access_rule': return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'role_permission_rule': return 'bg-indigo-50 text-indigo-700 border-indigo-200';
        case 'data_integrity_rule': return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'conditional_logic': return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'validation_rule': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'approval_rule': return 'bg-[#F0FDFA] text-[#0D9488] border-teal-200';
        case 'notification_rule': return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'compliance_rule': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default: return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};

const getTypeLabel = (type) => {
    const opt = ruleTypeOptions.find(o => o.value === type);
    return opt ? opt.label : type;
};

// Builder Condition Helpers
const addSubCondition = (conditionGroup) => {
    if (!conditionGroup.conditions) conditionGroup.conditions = [];
    conditionGroup.conditions.push({ field: 'Status', operator: 'equals', value: 'In Progress' });
};

const removeCondition = (conditionsArr, index) => {
    conditionsArr.splice(index, 1);
};

const addApprovalStep = () => {
    if (!ruleForm.rule_logic.approval_chain) ruleForm.rule_logic.approval_chain = [];
    ruleForm.rule_logic.approval_chain.push('Department Head');
};

const removeApprovalStep = (index) => {
    ruleForm.rule_logic.approval_chain.splice(index, 1);
};

const insertErrorMessageVar = (varName) => {
    ruleForm.rule_logic.error_message = (ruleForm.rule_logic.error_message || '') + ` [${varName}]`;
};
</script>

<template>
    <AppLayout title="System Settings">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    System Settings
                </h2>
                <!-- Tab Selector Buttons -->
                <div class="flex items-center bg-slate-100 p-1 rounded-xl">
                    <button 
                        @click="activeTab = 'rules'"
                        :class="[
                            'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                            activeTab === 'rules' ? 'bg-[#0D9488] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        System Rules Engine
                    </button>
                    <button 
                        @click="activeTab = 'general'"
                        :class="[
                            'px-4 py-2 text-xs font-bold rounded-lg transition-all',
                            activeTab === 'general' ? 'bg-[#0D9488] text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        General & Branding
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full max-w-none px-4 sm:px-6 lg:px-8">
                
                <!-- Notification Banner -->
                <div v-if="successMessage" class="mb-6 p-4 bg-teal-50 border border-teal-200 text-[#0F766E] rounded-xl shadow-sm flex items-center justify-between animate-fade-in">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#0D9488]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-xs font-bold">{{ successMessage }}</span>
                    </div>
                </div>

                <!-- TAB 1: SYSTEM RULES ENGINE -->
                <div v-if="activeTab === 'rules'" class="space-y-6">
                    
                    <!-- Header Stats & Overview Card -->
                    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-teal-950 p-6 rounded-2xl text-white shadow-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 bg-teal-500/20 text-teal-300 text-[10px] font-bold uppercase tracking-wider rounded-md border border-teal-500/30">System-Wide Governance</span>
                                <h3 class="text-xl font-bold">System Rules Management</h3>
                            </div>
                            <p class="text-xs text-slate-300 mt-1 max-w-2xl">
                                Configure System-Wide Security Rules (Page view access, role permissions, operational limits) & Workspace Governance Rules (conditional logic, validation checks, approval chains, escalation alerts).
                            </p>
                        </div>
                        <div class="flex items-center gap-4 bg-white/10 p-3 rounded-xl backdrop-blur-sm border border-white/10">
                            <div class="text-center px-3 border-r border-white/10">
                                <p class="text-[10px] uppercase text-slate-400 font-bold">Total Rules</p>
                                <p class="text-lg font-bold text-teal-300">{{ props.systemRules.length }}</p>
                            </div>
                            <div class="text-center px-3 border-r border-white/10">
                                <p class="text-[10px] uppercase text-slate-400 font-bold">Active</p>
                                <p class="text-lg font-bold text-emerald-400">{{ props.systemRules.filter(r => r.enabled).length }}</p>
                            </div>
                            <div class="text-center px-3">
                                <p class="text-[10px] uppercase text-slate-400 font-bold">System-Wide</p>
                                <p class="text-lg font-bold text-sky-300">
                                    {{ props.systemRules.filter(r => ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(r.type)).length }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Domain Category Filter Tabs -->
                    <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
                        <button 
                            @click="activeDomainFilter = 'all'"
                            :class="[
                                'px-4 py-2 text-xs font-bold rounded-xl transition',
                                activeDomainFilter === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            All Governance Rules ({{ props.systemRules.length }})
                        </button>
                        <button 
                            @click="activeDomainFilter = 'system_wide'"
                            :class="[
                                'px-4 py-2 text-xs font-bold rounded-xl transition',
                                activeDomainFilter === 'system_wide' ? 'bg-sky-700 text-white shadow-sm' : 'bg-sky-50 text-sky-700 hover:bg-sky-100'
                            ]"
                        >
                            🛡️ System-Wide Access & Security
                        </button>
                        <button 
                            @click="activeDomainFilter = 'workspace'"
                            :class="[
                                'px-4 py-2 text-xs font-bold rounded-xl transition',
                                activeDomainFilter === 'workspace' ? 'bg-[#0D9488] text-white shadow-sm' : 'bg-teal-50 text-[#0D9488] hover:bg-teal-100'
                            ]"
                        >
                            📋 Workspace & Form Governance
                        </button>
                    </div>

                    <!-- Toolbar & Filters -->
                    <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm flex flex-col lg:flex-row justify-between gap-4 items-stretch lg:items-center">
                        <div class="flex flex-wrap items-center gap-3 flex-1">
                            <input 
                                type="text"
                                v-model="ruleSearch"
                                placeholder="Search rules by name, type, logic..."
                                class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm min-w-[240px] flex-1"
                            />
                            
                            <select v-model="ruleTypeFilter" class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm">
                                <option value="">All Rule Types</option>
                                <option v-for="t in ruleTypeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>

                            <select v-model="ruleScopeFilter" class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm">
                                <option value="">All Module Scopes</option>
                                <option v-for="m in availableModules" :key="m" :value="m">{{ m }}</option>
                            </select>

                            <select v-model="ruleStatusFilter" class="rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488] shadow-sm">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 flex-wrap">
                            <button 
                                type="button"
                                @click="expandAllRules"
                                class="px-2.5 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1"
                                title="Expand all system rule cards"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                                Expand All
                            </button>

                            <button 
                                type="button"
                                @click="collapseAllRules"
                                class="px-2.5 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1"
                                title="Collapse all system rule cards"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                                Collapse All
                            </button>

                            <button 
                                @click="exportAllRulesJSON"
                                class="px-3 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5"
                                title="Export rules payload to JSON file"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Export JSON
                            </button>

                            <button 
                                @click="isImportModalOpen = true"
                                class="px-3 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5"
                                title="Import system rules from JSON"
                            >
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Import JSON
                            </button>

                            <button 
                                @click="openAddRuleModal"
                                class="px-4 py-2.5 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-2"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add System Rule
                            </button>
                        </div>
                    </div>

                    <!-- SYSTEM RULE CARDS GRID -->
                    <div class="grid grid-cols-1 gap-6">
                        <div 
                            v-for="rule in filteredRules" 
                            :key="rule.id"
                            :class="[
                                'bg-white border rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col',
                                rule.enabled ? 'border-slate-200' : 'border-slate-200 bg-slate-50/40 opacity-80'
                            ]"
                        >
                            <!-- Card Header (Clickable to Expand/Collapse) -->
                            <div 
                                @click="toggleRuleCollapse(rule.id)"
                                :class="[
                                    'px-6 py-4 border-b flex flex-wrap items-center justify-between gap-3 cursor-pointer select-none transition-colors duration-150 hover:bg-slate-100/70',
                                    ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(rule.type) ? 'bg-sky-50/70 border-sky-100' : 'bg-slate-50/80 border-slate-100'
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <button 
                                        type="button"
                                        class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition"
                                        :title="isRuleExpanded(rule.id) ? 'Collapse rule' : 'Expand rule'"
                                    >
                                        <svg 
                                            class="w-4 h-4 transition-transform duration-200" 
                                            :class="{ 'rotate-180': isRuleExpanded(rule.id) }" 
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <!-- <span class="text-xs font-bold text-slate-400">#{{ rule.id }}</span> -->
                                    <h4 class="text-base font-bold text-slate-800 leading-snug">
                                        <span class="text-[#0D9488]">{{ rule.name }}</span>
                                    </h4>
                                </div>

                                <div class="flex items-center gap-3" @click.stop>
                                    <!-- Domain Tag -->
                                    <span :class="[
                                        'px-2.5 py-1 text-[9px] font-bold uppercase tracking-wider rounded-lg border',
                                        ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(rule.type)
                                            ? 'bg-sky-100 text-sky-900 border-sky-300'
                                            : 'bg-teal-100 text-teal-900 border-teal-300'
                                    ]">
                                        {{ ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(rule.type) ? 'System-Wide Access' : 'Workspace Governance' }}
                                    </span>

                                    <!-- Status Badge -->
                                    <!-- <span :class="[
                                        'px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg border',
                                        rule.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                        rule.status === 'draft' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                        'bg-slate-100 text-slate-500 border-slate-200'
                                    ]">
                                        Status: {{ rule.status }}
                                    </span> -->

                                    <!-- Type Badge -->
                                    <span :class="['px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded-lg border', getTypeBadgeStyle(rule.type)]">
                                        {{ getTypeLabel(rule.type) }}
                                    </span>

                                    <!-- Quick Enable Toggle Switch -->
                                    <label class="relative inline-flex items-center cursor-pointer select-none" @click.stop>
                                        <input type="checkbox" :checked="rule.enabled" @change="toggleRuleState(rule)" class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#0D9488]"></div>
                                        <span class="ms-2 text-xs font-semibold text-slate-700">{{ rule.enabled ? 'Enabled ✓' : 'Disabled' }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Collapsible Content Wrapper -->
                            <div v-show="isRuleExpanded(rule.id)" class="border-t border-slate-100 flex-1 flex flex-col transition-all duration-300">
                                <!-- Card Body: Rule Logic Visualization -->
                                <div class="p-6 space-y-4 flex-1">
                                    
                                    <!-- PAGE ACCESS RULE VISUALIZER -->
                                    <div v-if="rule.type === 'page_access_rule'" class="bg-sky-50/70 border border-sky-200 p-4 rounded-xl text-xs space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-sky-900 uppercase tracking-wider text-[10px]">Restricted Page Route:</span>
                                            <span class="font-mono bg-white px-2.5 py-1 border border-sky-300 text-sky-800 font-bold rounded-md text-xs">
                                                {{ rule.rule_logic?.target_route || '/admin/settings' }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                                            <div>
                                                <span class="font-bold text-sky-900 uppercase tracking-wider text-[10px] block mb-1">Permitted System Roles:</span>
                                                <div class="flex flex-wrap gap-1">
                                                    <span v-for="r in (rule.rule_logic?.allowed_system_roles || ['admin'])" :key="r" class="px-2 py-0.5 bg-sky-200 text-sky-900 font-bold rounded uppercase text-[10px]">{{ r }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="font-bold text-sky-900 uppercase tracking-wider text-[10px] block mb-1">Permitted Functional Roles:</span>
                                                <div class="flex flex-wrap gap-1">
                                                    <span v-for="r in (rule.rule_logic?.allowed_functional_roles || ['admin_staff'])" :key="r" class="px-2 py-0.5 bg-white border border-sky-300 text-sky-800 font-semibold rounded text-[10px]">{{ r }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ROLE PERMISSION RULE VISUALIZER -->
                                    <div v-else-if="rule.type === 'role_permission_rule'" class="bg-indigo-50/70 border border-indigo-200 p-4 rounded-xl text-xs space-y-2">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-indigo-900 uppercase tracking-wider text-[10px]">Operational Action:</span>
                                            <span class="font-mono bg-indigo-100 text-indigo-900 px-2.5 py-0.5 font-bold rounded uppercase">
                                                {{ rule.rule_logic?.operation || 'Role Operational Rule' }}
                                            </span>
                                        </div>
                                        <p class="text-indigo-800 font-medium mt-1">
                                            {{ rule.rule_logic?.error_message || 'Enforces System & Functional Role operational permissions.' }}
                                        </p>
                                    </div>

                                    <!-- CONDITIONAL LOGIC VISUALIZER -->
                                    <div v-else-if="rule.type === 'conditional_logic' && rule.rule_logic" class="space-y-3">
                                        <div class="bg-slate-900 text-slate-100 p-4 rounded-xl font-mono text-xs border border-slate-800 space-y-2">
                                            <div class="text-teal-400 font-bold tracking-wider uppercase">IF CONDITIONS:</div>
                                            <div v-if="rule.rule_logic.conditions" class="pl-4 space-y-1.5 border-l-2 border-teal-500/50">
                                                <div v-for="(cond, idx) in rule.rule_logic.conditions" :key="idx">
                                                    <div v-if="cond.field" class="flex items-center gap-2">
                                                        <span class="text-amber-300 font-semibold">[{{ cond.field }}]</span>
                                                        <span class="text-sky-300">{{ cond.operator }}</span>
                                                        <span class="text-emerald-300">[{{ cond.value }}]</span>
                                                    </div>
                                                    <div v-else-if="cond.conditions" class="pl-3 py-1 bg-slate-800/80 rounded border border-slate-700 space-y-1">
                                                        <span class="text-teal-300 font-bold uppercase text-[10px]">{{ cond.operator || 'AND' }}</span>
                                                        <div v-for="(nc, nidx) in cond.conditions" :key="nidx" class="flex items-center gap-2">
                                                            <span class="text-amber-300 font-semibold">[{{ nc.field }}]</span>
                                                            <span class="text-sky-300">{{ nc.operator }}</span>
                                                            <span class="text-emerald-300">[{{ nc.value }}]</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="text-teal-400 font-bold tracking-wider uppercase mt-3">THEN:</div>
                                            <div class="pl-4 space-y-1 text-slate-300">
                                                <div v-if="rule.rule_logic.then_show && rule.rule_logic.then_show.length">
                                                    <span class="text-emerald-400 font-bold">Show the following fields:</span>
                                                    <ul class="list-disc list-inside text-slate-200 pl-2">
                                                        <li v-for="f in rule.rule_logic.then_show" :key="f">{{ f }}</li>
                                                    </ul>
                                                </div>
                                                <div v-if="rule.rule_logic.then_hide && rule.rule_logic.then_hide.length" class="mt-1">
                                                    <span class="text-rose-400 font-bold">Hide the following fields:</span>
                                                    <ul class="list-disc list-inside text-slate-400 pl-2">
                                                        <li v-for="f in rule.rule_logic.then_hide" :key="f">{{ f }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- VALIDATION RULE VISUALIZER -->
                                    <div v-else-if="rule.type === 'validation_rule'" class="bg-amber-50/60 border border-amber-200 p-4 rounded-xl text-xs space-y-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-amber-900 uppercase tracking-wider text-[10px]">Target Field:</span>
                                            <span class="px-2 py-0.5 bg-amber-100 text-amber-900 font-mono font-bold rounded">[{{ rule.rule_logic?.field || 'name' }}]</span>
                                            <span class="text-amber-800 font-medium">Type: {{ rule.rule_logic?.validation_type }}</span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-amber-900 uppercase tracking-wider text-[10px] block mb-1">Validation Error Message Template:</span>
                                            <p class="font-mono bg-white p-2.5 rounded border border-amber-200 text-slate-800">
                                                {{ rule.rule_logic?.error_message }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- APPROVAL RULE VISUALIZER -->
                                    <div v-else-if="rule.type === 'approval_rule'" class="bg-[#F0FDFA] border border-teal-200 p-4 rounded-xl text-xs space-y-2">
                                        <div>
                                            <span class="font-bold text-teal-900 uppercase tracking-wider text-[10px] block mb-1">Approval Hierarchy Chain:</span>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <template v-for="(step, idx) in (rule.rule_logic?.approval_chain || ['Department Head', 'Administrator'])" :key="idx">
                                                    <span class="px-3 py-1 bg-[#0D9488] text-white font-bold rounded-lg shadow-sm">{{ step }}</span>
                                                    <span v-if="idx < (rule.rule_logic?.approval_chain?.length - 1)" class="text-teal-500 font-bold text-base">→</span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- NOTIFICATION RULE VISUALIZER -->
                                    <div v-else-if="rule.type === 'notification_rule'" class="bg-blue-50/60 border border-blue-200 p-4 rounded-xl text-xs space-y-2">
                                        <div class="flex items-center gap-4">
                                            <div>
                                                <span class="font-bold text-blue-900 uppercase tracking-wider text-[10px] block">Trigger Event:</span>
                                                <span class="font-mono font-bold text-blue-800">{{ rule.rule_logic?.event }}</span>
                                            </div>
                                            <div>
                                                <span class="font-bold text-blue-900 uppercase tracking-wider text-[10px] block">Channels:</span>
                                                <div class="flex items-center gap-1">
                                                    <span v-for="ch in (rule.rule_logic?.channels || [])" :key="ch" class="px-2 py-0.5 bg-blue-100 text-blue-800 font-bold rounded uppercase text-[9px]">{{ ch }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- COMPLIANCE RULE VISUALIZER -->
                                    <div v-else-if="rule.type === 'compliance_rule'" class="bg-emerald-50/60 border border-emerald-200 p-4 rounded-xl text-xs space-y-2">
                                        <div class="flex items-center gap-4">
                                            <span class="font-bold text-emerald-900 uppercase tracking-wider text-[10px]">Sector: {{ rule.rule_logic?.sector || 'General' }}</span>
                                            <span class="font-bold text-emerald-900 uppercase tracking-wider text-[10px]">Standard: {{ rule.rule_logic?.standard || 'ISO' }}</span>
                                        </div>
                                    </div>

                                    <!-- Rule Description -->
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Description:</span>
                                        <p class="text-xs text-slate-600 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
                                            {{ rule.description || 'No description provided for this system rule.' }}
                                        </p>
                                    </div>

                                    <!-- Scope Modules & Actions Badges -->
                                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Applicable Scope:</span>
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="sc in (rule.scope || [])" :key="sc" class="px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 text-[10px] font-bold rounded-md">
                                                    {{ sc }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Actions Executed:</span>
                                            <div class="flex flex-wrap gap-1">
                                                <span v-for="act in (rule.actions || [])" :key="act" class="px-2 py-0.5 bg-teal-50 border border-teal-200 text-[#0D9488] text-[10px] font-bold rounded-md uppercase">
                                                    {{ act.replace(/_/g, ' ') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Metadata Footer -->
                                <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-400 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-4">
                                        <span>Created: <strong class="text-slate-600">{{ rule.created_by || 'Admin' }}</strong> | {{ new Date(rule.created_at).toLocaleDateString() }}</span>
                                        <span>Last Modified: <strong class="text-slate-600">{{ rule.last_modified_by || 'Admin' }}</strong> | {{ new Date(rule.updated_at).toLocaleDateString() }}</span>
                                    </div>
                                </div>

                                <!-- Footer Action Buttons ([Edit] [Clone] [Export] [Preview] [Delete]) -->
                                <div class="px-6 py-3 bg-white border-t border-slate-100 flex items-center justify-end gap-2">
                                    <button 
                                        @click="openEditRuleModal(rule)"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-[#0D9488] hover:text-white text-slate-700 text-xs font-bold rounded-lg transition shadow-sm flex items-center gap-1"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>

                                    <button 
                                        @click="cloneRule(rule)"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition shadow-sm flex items-center gap-1"
                                        title="Clone system rule"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                        </svg>
                                        Clone
                                    </button>

                                    <button 
                                        @click="exportSingleRule(rule)"
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition shadow-sm flex items-center gap-1"
                                        title="Export rule to JSON"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                        </svg>
                                        Export
                                    </button>

                                    <button 
                                        @click="openPreviewModal(rule)"
                                        class="px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-[#0D9488] text-xs font-bold rounded-lg transition shadow-sm flex items-center gap-1"
                                        title="Simulate rule in preview mode"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Preview
                                    </button>

                                    <button 
                                        @click="confirmDeleteRule(rule)"
                                        class="px-3 py-1.5 bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 text-xs font-bold rounded-lg transition shadow-sm flex items-center gap-1"
                                        title="Delete system rule"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="filteredRules.length === 0" class="bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-400">
                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h4 class="font-bold text-slate-700 text-base">No system rules found</h4>
                            <p class="text-xs text-slate-500 mt-1">Try adjusting your category filter or search terms, or click "+ Add System Rule".</p>
                        </div>
                    </div>

                </div>

                <!-- TAB 2: GENERAL & BRANDING SETTINGS -->
                <div v-else-if="activeTab === 'general'" class="bg-white border border-slate-200 overflow-hidden shadow-sm rounded-2xl p-6 lg:p-8">
                    <form @submit.prevent="submitGeneralSettings" class="space-y-8">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 border-b pb-2 mb-6">General Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">System Name</label>
                                    <input 
                                        type="text" 
                                        v-model="generalForm.system_name" 
                                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] sm:text-sm"
                                        placeholder="e.g. Project Tracker"
                                        required
                                    />
                                    <p class="mt-1 text-xs text-gray-500">This name will be displayed in browser tabs and across the app layout headers.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">System Logo</label>
                                    <div class="flex items-center space-x-6 mt-1">
                                        <div class="h-16 w-16 rounded-xl border border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden shadow-sm">
                                            <img v-if="previewUrl" :src="previewUrl" class="h-full w-full object-contain" />
                                            <span v-else class="text-xs text-gray-400 font-bold">No Logo</span>
                                        </div>

                                        <div class="space-y-2">
                                            <input 
                                                type="file" 
                                                ref="fileInput"
                                                @change="handleLogoChange" 
                                                class="hidden" 
                                                accept="image/*"
                                            />
                                            <button 
                                                type="button" 
                                                @click="triggerFileInput"
                                                class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 shadow-sm"
                                            >
                                                Upload Logo
                                            </button>
                                            <button 
                                                v-if="previewUrl"
                                                type="button" 
                                                @click="handleRemoveLogo"
                                                class="ml-2 px-4 py-2 border border-transparent rounded-xl text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 shadow-sm"
                                            >
                                                Remove
                                            </button>
                                            <p class="text-xs text-gray-500">Supports PNG, JPG, or SVG up to 2MB.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold text-gray-900 border-b pb-2 mb-6">Preset Color Palette</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div 
                                    v-for="preset in presets" 
                                    :key="preset.id"
                                    @click="generalForm.theme = preset.id"
                                    :class="[
                                        'relative flex flex-col p-5 border rounded-2xl cursor-pointer transition duration-200 select-none shadow-sm hover:shadow-md',
                                        generalForm.theme === preset.id 
                                            ? 'border-[#0D9488] ring-2 ring-[#0D9488] bg-teal-50/20' 
                                            : 'border-gray-200 bg-white hover:border-gray-300'
                                    ]"
                                >
                                    <div v-if="generalForm.theme === preset.id" class="absolute top-3 right-3 text-[#0D9488]">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                    <span class="font-bold text-gray-900 text-base">{{ preset.name }}</span>
                                    <span class="text-xs text-gray-500 mt-1 mb-4 leading-relaxed">{{ preset.desc }}</span>
                                    <div class="flex items-center space-x-2 mt-auto">
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border shadow-sm" :style="{ backgroundColor: preset.colors.primary }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Primary</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border shadow-sm" :style="{ backgroundColor: preset.colors.secondary }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Secondary</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border shadow-sm" :style="{ backgroundColor: preset.colors.text }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Text</span>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border shadow-sm" :style="{ backgroundColor: preset.colors.bg }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Bg</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 border-t flex justify-end">
                            <button 
                                type="submit" 
                                :disabled="generalForm.processing"
                                class="inline-flex items-center px-6 py-3 text-xs font-bold uppercase tracking-wider rounded-xl shadow-sm text-white bg-[#0D9488] hover:bg-[#0f766e] transition duration-150"
                            >
                                <span>Save General Settings</span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL 1: WYSIWYG RULE BUILDER (CREATE / EDIT) -->
        <!-- ========================================== -->
        <div v-if="isRuleModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity" @click="isRuleModalOpen = false"></div>

            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden max-w-3xl w-full z-10 transform transition-all flex flex-col max-h-[90vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">
                            {{ ruleModalMode === 'create' ? 'Add New System Rule' : 'Edit System Rule' }}
                        </h3>
                        <p class="text-xs text-slate-500">Configure System-Wide Access & Security or Workspace Governance Rule parameters.</p>
                    </div>
                    <button @click="isRuleModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form @submit.prevent="submitRuleForm" class="p-6 space-y-6 overflow-y-auto flex-1">
                    
                    <!-- Basic Meta & Category -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Rule Name</label>
                            <input type="text" v-model="ruleForm.name" required class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]" placeholder="e.g. Admin Management Page Access Restriction" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Rule Type</label>
                            <select v-model="ruleForm.type" required class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]">
                                <option v-for="t in ruleTypeOptions" :key="t.value" :value="t.value">
                                    {{ ['page_access_rule', 'role_permission_rule', 'data_integrity_rule'].includes(t.value) ? '🛡️ [System-Wide]' : '📋 [Workspace]' }} {{ t.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                            <select v-model="ruleForm.status" class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Applicable Module Scope</label>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <label v-for="mod in availableModules" :key="mod" class="inline-flex items-center gap-1.5 text-xs text-slate-700 cursor-pointer">
                                    <input type="checkbox" :value="mod" v-model="ruleForm.scope" class="rounded text-[#0D9488] focus:ring-[#0D9488]" />
                                    {{ mod }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Description</label>
                        <textarea v-model="ruleForm.description" rows="2" class="w-full rounded-xl border-slate-200 text-xs focus:border-[#0D9488] focus:ring-[#0D9488]" placeholder="Detailed explanation of rule purpose and operational enforcement..."></textarea>
                    </div>

                    <!-- DYNAMIC BUILDER PANELS BY TYPE -->

                    <!-- 1. PAGE ACCESS RULE PANEL -->
                    <div v-if="ruleForm.type === 'page_access_rule'" class="border border-sky-200 rounded-2xl p-5 bg-sky-50/40 space-y-4">
                        <h4 class="text-xs font-bold text-sky-900 uppercase tracking-wider">🛡️ System-Wide Page View & Route Restriction Config</h4>
                        <div>
                            <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Target Page Route</label>
                            <select v-model="ruleForm.rule_logic.target_route" class="w-full rounded-xl border-sky-200 text-xs">
                                <option v-for="ro in routeOptions" :key="ro.route" :value="ro.route">{{ ro.label }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Permitted System Roles</label>
                                <div class="space-y-1">
                                    <label v-for="sr in systemRoleOptions" :key="sr.slug" class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" :value="sr.slug" v-model="ruleForm.rule_logic.allowed_system_roles" class="rounded text-sky-600" />
                                        {{ sr.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-sky-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-28 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs">
                                        <input type="checkbox" :value="fr.slug" v-model="ruleForm.rule_logic.allowed_functional_roles" class="rounded text-sky-600" />
                                        {{ fr.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ROLE PERMISSION RULE PANEL -->
                    <div v-else-if="ruleForm.type === 'role_permission_rule'" class="border border-indigo-200 rounded-2xl p-5 bg-indigo-50/40 space-y-4">
                        <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider">🛡️ System & Functional Role Operational Action Permissions</h4>
                        <div>
                            <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Operational Action</label>
                            <select v-model="ruleForm.rule_logic.operation" class="w-full rounded-xl border-indigo-200 text-xs">
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
                                        <input type="checkbox" :value="sr.slug" v-model="ruleForm.rule_logic.allowed_system_roles" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        <span>{{ sr.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="fr.slug" v-model="ruleForm.rule_logic.allowed_functional_roles" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        <span>{{ fr.label }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-indigo-800 uppercase mb-1">Target Error / Restriction Message</label>
                            <input type="text" v-model="ruleForm.rule_logic.error_message" class="w-full rounded-xl border-indigo-200 text-xs" placeholder="e.g. Only authorized roles can perform this action." />
                        </div>
                    </div>

                    <!-- 3. CONDITIONAL LOGIC BUILDER -->
                    <div v-else-if="ruleForm.type === 'conditional_logic'" class="border border-teal-200 rounded-2xl p-5 bg-teal-50/20 space-y-4">
                        <h4 class="text-xs font-bold text-[#0F766E] uppercase tracking-wider">📋 Form Conditional Logic Builder</h4>
                        <div class="bg-slate-900 p-4 rounded-xl text-white text-xs space-y-3 font-mono">
                            <div class="flex items-center justify-between text-teal-300 font-bold">
                                <span>IF CONDITIONS:</span>
                                <button type="button" @click="addSubCondition(ruleForm.rule_logic)" class="px-2 py-1 bg-teal-600 text-white rounded text-[10px] uppercase font-sans">+ Add Condition</button>
                            </div>
                            
                            <div v-for="(cond, cidx) in (ruleForm.rule_logic.conditions || [])" :key="cidx" class="pl-2 border-l-2 border-teal-500 space-y-2">
                                <div v-if="cond.field" class="flex flex-wrap items-center gap-2">
                                    <select v-model="cond.field" class="bg-slate-800 border-slate-700 text-amber-300 text-xs rounded p-1.5">
                                        <option v-for="f in formFields" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                    <select v-model="cond.operator" class="bg-slate-800 border-slate-700 text-sky-300 text-xs rounded p-1.5">
                                        <option v-for="op in operatorOptions" :key="op" :value="op">{{ op }}</option>
                                    </select>
                                    <input type="text" v-model="cond.value" class="bg-slate-800 border-slate-700 text-emerald-300 text-xs rounded p-1.5 flex-1" placeholder="Value..." />
                                    <button type="button" @click="removeCondition(ruleForm.rule_logic.conditions, cidx)" class="text-rose-400">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. VALIDATION RULE BUILDER -->
                    <div v-else-if="ruleForm.type === 'validation_rule'" class="border border-amber-200 rounded-2xl p-5 bg-amber-50/40 space-y-3">
                        <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider">📋 Form Validation Rule Builder</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Field to Validate</label>
                                <select v-model="ruleForm.rule_logic.field" class="w-full rounded-xl border-slate-200 text-xs">
                                    <option v-for="f in formFields" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase">Validation Constraint</label>
                                <select v-model="ruleForm.rule_logic.validation_type" class="w-full rounded-xl border-slate-200 text-xs">
                                    <option value="required">Required Field</option>
                                    <option value="min_length">Minimum Character Length</option>
                                    <option value="numeric">Must be Numeric</option>
                                    <option value="date_range">Valid Date Range</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Custom Error Message Template</label>
                            <textarea v-model="ruleForm.rule_logic.error_message" rows="2" class="w-full rounded-xl border-slate-200 font-mono text-xs" placeholder="e.g. Project Name [Project Name] must be at least 5 characters long."></textarea>
                            <div class="flex items-center gap-2 mt-1">
                                <button type="button" @click="insertErrorMessageVar('Project Name')" class="px-2 py-0.5 bg-slate-200 text-[10px] font-bold rounded">[Project Name]</button>
                                <button type="button" @click="insertErrorMessageVar('Field Name')" class="px-2 py-0.5 bg-slate-200 text-[10px] font-bold rounded">[Field Name]</button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. APPROVAL RULE BUILDER -->
                    <div v-else-if="ruleForm.type === 'approval_rule'" class="border border-teal-200 rounded-2xl p-5 bg-teal-50/40 space-y-4">
                        <div>
                            <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Target Operational Action</label>
                            <select v-model="ruleForm.rule_logic.operation" class="w-full rounded-xl border-teal-200 text-xs">
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
                            <template v-for="(step, sidx) in (ruleForm.rule_logic.approval_chain || [])" :key="sidx">
                                <div class="flex items-center gap-1.5 bg-[#F0FDFA] border border-teal-300 pl-3 pr-1 py-1 rounded-xl shadow-xs">
                                    <select 
                                        v-model="ruleForm.rule_logic.approval_chain[sidx]" 
                                        class="bg-transparent border-none text-[#0D9488] font-bold text-xs p-0 pe-6 focus:ring-0 cursor-pointer"
                                    >
                                        <optgroup label="System Roles">
                                            <option v-for="r in (props.roles.length ? props.roles : [{name: 'Administrator'}, {name: 'User'}, {name: 'Viewer'}])" :key="r.name" :value="r.name">{{ r.name }}</option>
                                        </optgroup>
                                        <optgroup label="Functional Roles">
                                            <option v-for="mr in (props.memberRoles.length ? props.memberRoles : [{name: 'Department Head'}, {name: 'Project Manager'}, {name: 'Admin Staff'}, {name: 'Lead Developer'}, {name: 'Developer'}, {name: 'UI/UX Designer'}])" :key="mr.name" :value="mr.name">{{ mr.name }}</option>
                                        </optgroup>
                                    </select>
                                    <button type="button" @click="removeApprovalStep(sidx)" class="p-1 text-teal-600 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Remove Step">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <span v-if="sidx < (ruleForm.rule_logic.approval_chain?.length - 1)" class="text-teal-400 font-bold text-base">→</span>
                            </template>
                            <div v-if="!ruleForm.rule_logic.approval_chain || ruleForm.rule_logic.approval_chain.length === 0" class="text-xs text-slate-400 italic">
                                No approval steps configured. Click "+ Add Step" to add a role to the chain.
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-2 border-t border-teal-200/60">
                            <div>
                                <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Permitted System Roles</label>
                                <div class="space-y-1">
                                    <label v-for="sr in systemRoleOptions" :key="sr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="sr.slug" v-model="ruleForm.rule_logic.allowed_system_roles" class="rounded text-teal-600 focus:ring-teal-500" />
                                        <span>{{ sr.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-teal-800 uppercase mb-1">Permitted Functional Roles</label>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    <label v-for="fr in functionalRoleOptions" :key="fr.slug" class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                        <input type="checkbox" :value="fr.slug" v-model="ruleForm.rule_logic.allowed_functional_roles" class="rounded text-teal-600 focus:ring-teal-500" />
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
                                <input type="checkbox" :value="act.value" v-model="ruleForm.actions" class="rounded text-[#0D9488] focus:ring-[#0D9488]" />
                                <span>{{ act.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" @click="isRuleModalOpen = false" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-xl hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="ruleForm.processing" class="px-5 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-xl shadow-sm transition">
                            Save System Rule
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: INTERACTIVE PREVIEW -->
        <div v-if="isPreviewModalOpen && previewingRule" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="isPreviewModalOpen = false"></div>
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-xl w-full z-10 p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-lg">Rule Simulation Preview</h3>
                    <button @click="isPreviewModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                    <p class="font-bold text-slate-700">System Rule: <span class="text-[#0D9488]">{{ previewingRule.name }}</span></p>
                    <p class="text-slate-500">{{ previewingRule.description }}</p>
                </div>
                <div class="p-4 bg-teal-50 border border-teal-200 text-[#0F766E] rounded-xl text-xs space-y-1">
                    <p class="font-bold uppercase tracking-wider text-[10px]">Simulation Result:</p>
                    <p>Enforcement Status: <strong class="text-emerald-700">Active & Enforced System-Wide</strong></p>
                    <p>Configured Actions: <strong>{{ (previewingRule.actions || []).join(', ') }}</strong></p>
                </div>
                <div class="flex justify-end">
                    <button @click="isPreviewModalOpen = false" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl">Close Simulation</button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: IMPORT JSON -->
        <div v-if="isImportModalOpen" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="isImportModalOpen = false"></div>
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-xl w-full z-10 p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-lg">Import System Rules from JSON</h3>
                    <button @click="isImportModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Paste JSON Array Payload</label>
                    <textarea v-model="importJsonText" rows="8" class="w-full rounded-xl border-slate-200 font-mono text-xs" placeholder='[ { "name": "Rule 1", "type": "page_access_rule", ... } ]'></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="isImportModalOpen = false" class="px-4 py-2 border text-xs font-semibold text-slate-700 rounded-xl">Cancel</button>
                    <button @click="submitImportJSON" class="px-4 py-2 bg-[#0D9488] text-white text-xs font-bold rounded-xl">Import Payload</button>
                </div>
            </div>
        </div>

        <!-- MODAL 4: DELETE CONFIRMATION -->
        <div v-if="isDeleteModalOpen && ruleToDelete" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="isDeleteModalOpen = false"></div>
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-md w-full z-10 p-6 space-y-4">
                <h3 class="font-bold text-slate-800 text-lg">Delete System Rule</h3>
                <p class="text-xs text-slate-600">Are you sure you want to permanently delete rule <strong>"{{ ruleToDelete.name }}"</strong>? This action cannot be undone.</p>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="isDeleteModalOpen = false" class="px-4 py-2 border text-xs font-semibold text-slate-700 rounded-xl">Cancel</button>
                    <button @click="executeDeleteRule" class="px-4 py-2 bg-rose-600 text-white text-xs font-bold rounded-xl">Delete Permanently</button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
