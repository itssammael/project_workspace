<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { getInitials } from '@/Utils/helpers';

const props = defineProps({
    data: {
        type: Object,
        required: true,
    },
    userInfo: {
        type: Object,
        default: () => ({}),
    },
});

const health = computed(() => props.data?.system_health || {});
const users = computed(() => props.data?.user_management || []);
const totalUsersCount = computed(() => props.data?.total_users_count || 0);
const auditLogs = computed(() => props.data?.audit_logs || []);
const totalLogsCount = computed(() => props.data?.total_audit_logs || 0);
const configList = computed(() => props.data?.system_configuration || []);

// User search filter
const userSearch = ref('');
const filteredUsers = computed(() => {
    if (!userSearch.value.trim()) return users.value;
    const q = userSearch.value.toLowerCase();
    return users.value.filter(u =>
        u.name.toLowerCase().includes(q) ||
        u.email.toLowerCase().includes(q) ||
        u.system_role.toLowerCase().includes(q) ||
        u.functional_roles.some(r => r.toLowerCase().includes(q))
    );
});

// Audit Log search filter
const logSearch = ref('');
const filteredAuditLogs = computed(() => {
    if (!logSearch.value.trim()) return auditLogs.value;
    const q = logSearch.value.toLowerCase();
    return auditLogs.value.filter(l =>
        l.action.toLowerCase().includes(q) ||
        l.actor.toLowerCase().includes(q) ||
        l.description.toLowerCase().includes(q)
    );
});

const getHealthStatusBadge = (status) => {
    switch (status) {
        case 'healthy':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'warning':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'critical':
        case 'offline':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <div class="space-y-8">
        <!-- 5.1 System Status & Health Grid -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5" id="system-health">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-1.5 h-6 bg-slate-900 rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">System Status & Environment Health</h3>
                        <p class="text-xs text-slate-400">Real-time infrastructure health and operational metrics</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Systems Operational
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Server -->
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">App Server</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border" :class="getHealthStatusBadge(health.server?.status)">
                            {{ health.server?.status || 'Healthy' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700 space-y-1 pt-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">PHP:</span>
                            <span class="font-semibold">{{ health.server?.php_version }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Laravel:</span>
                            <span class="font-semibold">{{ health.server?.laravel_version }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Memory:</span>
                            <span class="font-semibold text-[#0D9488]">{{ health.server?.memory_usage }}</span>
                        </div>
                    </div>
                </div>

                <!-- Database -->
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Database</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border" :class="getHealthStatusBadge(health.database?.status)">
                            {{ health.database?.status || 'Healthy' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700 space-y-1 pt-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Driver:</span>
                            <span class="font-semibold capitalize">{{ health.database?.driver }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Latency:</span>
                            <span class="font-semibold text-emerald-600">{{ health.database?.latency_ms }} ms</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Catalog:</span>
                            <span class="font-semibold truncate max-w-[100px]">{{ health.database?.database_name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Storage -->
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Storage Volume</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border" :class="getHealthStatusBadge(health.storage?.status)">
                            {{ health.storage?.status || 'Healthy' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700 space-y-1 pt-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Free Space:</span>
                            <span class="font-semibold text-slate-800">{{ health.storage?.free_space }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Total Volume:</span>
                            <span class="font-semibold">{{ health.storage?.total_space }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Writable:</span>
                            <span class="font-semibold text-emerald-600">{{ health.storage?.is_writable ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Security & Auth -->
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Auth & Security</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border" :class="getHealthStatusBadge(health.security?.status)">
                            {{ health.security?.status || 'Healthy' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700 space-y-1 pt-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Sessions:</span>
                            <span class="font-semibold text-[#0D9488]">{{ health.security?.active_sessions }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">2FA Users:</span>
                            <span class="font-semibold">{{ health.security?.two_factor_users }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">SSO Links:</span>
                            <span class="font-semibold">{{ health.security?.sso_linked_users }}</span>
                        </div>
                    </div>
                </div>

                <!-- Queue & Workers -->
                <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Queue & Jobs</span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border" :class="getHealthStatusBadge(health.jobs_queue?.status)">
                            {{ health.jobs_queue?.status || 'Healthy' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-700 space-y-1 pt-1">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Failed Jobs:</span>
                            <span class="font-semibold" :class="health.jobs_queue?.failed_jobs > 0 ? 'text-rose-600' : 'text-emerald-600'">
                                {{ health.jobs_queue?.failed_jobs || 0 }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Driver:</span>
                            <span class="font-semibold">Database</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Worker Status:</span>
                            <span class="font-semibold text-emerald-600">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5.2 User & Role Management Quick View -->
        <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs overflow-hidden" id="user-management">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-1.5 h-6 bg-[#0D9488] rounded-full"></span>
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">User & Role Management</h3>
                        <p class="text-xs text-slate-400">Directory of provisioned users, system access, and organizational roles</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <input
                        v-model="userSearch"
                        type="text"
                        placeholder="Search users..."
                        class="text-xs rounded-lg border-slate-200 py-1.5 px-3 focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488] w-48 sm:w-60"
                    />
                    <Link
                        :href="route('admin.management')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 text-white hover:bg-slate-800 transition"
                    >
                        Manage Users &rarr;
                    </Link>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/75 border-b border-slate-100 text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="py-3 px-5">User</th>
                            <th class="py-3 px-4">System Role</th>
                            <th class="py-3 px-4">Functional Roles</th>
                            <th class="py-3 px-4">Section / Department</th>
                            <th class="py-3 px-4">Joined Date</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <tr v-if="filteredUsers.length === 0">
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                No users match your search query.
                            </td>
                        </tr>
                        <tr
                            v-for="u in filteredUsers"
                            :key="u.id"
                            class="hover:bg-slate-50/60 transition"
                        >
                            <td class="py-3 px-5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-[#F0FDFA] text-[#0D9488] flex items-center justify-center font-bold text-[10px]">
                                        {{ getInitials(u.name) }}
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-800">{{ u.name }}</span>
                                        <span class="block text-[11px] text-slate-400">{{ u.email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                                    :class="u.system_role_slug === 'admin' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-100 text-slate-700 border-slate-200'">
                                    {{ u.system_role }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-wrap gap-1 max-w-[240px]">
                                    <span
                                        v-for="r in u.functional_roles"
                                        :key="r"
                                        class="px-1.5 py-0.5 rounded text-[10px] bg-teal-50 text-[#0D9488] border border-teal-100 font-semibold"
                                    >
                                        {{ r }}
                                    </span>
                                    <span v-if="u.functional_roles.length === 0" class="text-slate-400 italic text-[11px]">None assigned</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-slate-600 text-[11px]">
                                <span v-if="u.sections.length > 0">{{ u.sections.join(', ') }}</span>
                                <span v-else class="text-slate-400 italic">Unassigned Section</span>
                            </td>
                            <td class="py-3 px-4 text-slate-400 text-[11px]">
                                {{ u.created_at }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <Link
                                    :href="route('admin.management')"
                                    class="text-[#0D9488] hover:text-[#0f766e] font-semibold hover:underline"
                                >
                                    Edit
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-3 bg-slate-50/50 border-t border-slate-100 text-right text-xs text-slate-400">
                Displaying {{ filteredUsers.length }} of {{ totalUsersCount }} provisioned accounts
            </div>
        </div>

        <!-- Lower Grid: Security Audit Logs & Global System Configuration -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- 5.3 Audit & Activity Logs -->
            <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5 flex flex-col justify-between" id="audit-logs">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-1.5 h-6 bg-rose-600 rounded-full"></span>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">Security & Audit Logs</h3>
                                <p class="text-xs text-slate-400">Real-time trail of administrative actions & events</p>
                            </div>
                        </div>
                        <span class="text-xs font-bold text-slate-400">{{ totalLogsCount }} total logs</span>
                    </div>

                    <div class="mb-3">
                        <input
                            v-model="logSearch"
                            type="text"
                            placeholder="Filter audit logs by action or actor..."
                            class="text-xs rounded-lg border-slate-200 py-1.5 px-3 focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488] w-full"
                        />
                    </div>

                    <div v-if="filteredAuditLogs.length === 0" class="py-12 text-center text-slate-400 text-xs">
                        No audit log entries matching your criteria.
                    </div>

                    <div v-else class="divide-y divide-slate-100 max-h-[380px] overflow-y-auto pr-1">
                        <div
                            v-for="log in filteredAuditLogs"
                            :key="log.id"
                            class="py-3 flex items-start justify-between gap-3 text-xs"
                        >
                            <div class="space-y-0.5 flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-800">{{ log.action }}</span>
                                    <span class="text-[10px] text-slate-400">&bull; {{ log.time_ago }}</span>
                                </div>
                                <p class="text-[11px] text-slate-600 line-clamp-2">{{ log.description }}</p>
                                <div class="flex items-center gap-3 text-[10px] text-slate-400 pt-0.5">
                                    <span>Actor: <strong class="text-slate-600 font-semibold">{{ log.actor }}</strong></span>
                                    <span>IP: {{ log.ip_address }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400 flex items-center justify-between">
                    <span>Audit storage driver:</span>
                    <span class="font-semibold text-slate-700">Database (system_logs)</span>
                </div>
            </div>

            <!-- 5.4 Global Lookup / System Configuration -->
            <div class="bg-white border border-slate-200/80 rounded-xl shadow-xs p-5 flex flex-col justify-between" id="system-configuration">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2.5">
                            <span class="w-1.5 h-6 bg-purple-600 rounded-full"></span>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">System Configuration & Lookups</h3>
                                <p class="text-xs text-slate-400">Core organizational structures, policies, and workflows</p>
                            </div>
                        </div>
                        <Link :href="route('admin.settings')" class="text-xs text-[#0D9488] font-semibold hover:underline">
                            Open Settings &rarr;
                        </Link>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="cfg in configList"
                            :key="cfg.name"
                            class="py-3.5 flex items-center justify-between gap-3 hover:bg-slate-50/50 px-2 rounded-lg transition"
                        >
                            <div>
                                <h5 class="text-xs font-bold text-slate-800">{{ cfg.name }}</h5>
                                <p class="text-[11px] text-slate-400">{{ cfg.description }}</p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ cfg.usage_count }}
                                </span>
                                <Link
                                    :href="route(cfg.route)"
                                    class="text-xs text-[#0D9488] hover:text-[#0f766e] font-semibold hover:underline"
                                >
                                    Manage
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-400 flex items-center justify-between">
                    <span>Administrative scope:</span>
                    <span class="font-semibold text-slate-700">Full System Governance</span>
                </div>
            </div>
        </div>
    </div>
</template>
