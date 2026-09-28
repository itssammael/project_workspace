<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const page = usePage();

const dashboardRoles = computed(() => page.props.dashboardRoles || {
    active_view: 'staff',
    available_views: [],
    user_info: null
});

const activeViewKey = computed(() => dashboardRoles.value.active_view || 'staff');
const availableViews = computed(() => dashboardRoles.value.available_views || []);
const userInfo = computed(() => dashboardRoles.value.user_info);
const canShowSwitcher = computed(() => dashboardRoles.value.show_role_switcher !== false);

const activeViewMeta = computed(() => {
    return availableViews.value.find(v => v.key === activeViewKey.value) || {
        key: activeViewKey.value,
        name: activeViewKey.value === 'admin' ? 'Administrator View' :
              activeViewKey.value === 'department_head' ? 'Department Head View' :
              activeViewKey.value === 'supervisor' ? 'Supervisor View' : 'Staff View',
        badge: activeViewKey.value === 'admin' ? 'Admin' :
               activeViewKey.value === 'department_head' ? 'Dept Head' :
               activeViewKey.value === 'supervisor' ? 'Supervisor' : 'Staff',
    };
});

const isOpen = ref(false);
const isSwitching = ref(false);

const switchRoleView = (viewKey) => {
    if (viewKey === activeViewKey.value || isSwitching.value) {
        isOpen.value = false;
        return;
    }

    isSwitching.value = true;
    isOpen.value = false;

    // Use router to switch view and persist for session
    router.get(route('dashboard'), { view: viewKey }, {
        preserveState: false,
        preserveScroll: true,
        onFinish: () => {
            isSwitching.value = false;
        },
    });
};
</script>

<template>
    <div class="relative inline-flex items-center text-left" v-if="canShowSwitcher && availableViews.length > 0">
        <!-- Single Role View Display (non-interactive badge) -->
        <div v-if="availableViews.length <= 1" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200/80 rounded-lg text-xs">
            <span class="text-slate-400 font-medium">Viewing as:</span>
            <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-[#0D9488]"></span>
                {{ activeViewMeta.name }}
            </span>
        </div>

        <!-- Multi-Role Dropdown Selector -->
        <div v-else class="relative">
            <div class="flex items-center">
                <button
                    type="button"
                    @click="isOpen = !isOpen"
                    :disabled="isSwitching"
                    class="group inline-flex items-center gap-2 px-3 py-1.5 text-xs rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#0D9488]/40"
                    :class="isOpen 
                        ? 'bg-[#F0FDFA] border-[#0D9488] text-[#0D9488] shadow-sm' 
                        : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-700 shadow-xs'"
                    aria-label="Switch Dashboard Role View"
                >
                    <span class="text-slate-400 font-medium hidden md:inline">Viewing as:</span>
                    <span class="flex items-center gap-1.5 font-semibold text-slate-800">
                        <span class="w-2 h-2 rounded-full bg-[#0D9488] animate-pulse" v-if="isSwitching"></span>
                        <span class="w-2 h-2 rounded-full bg-[#0D9488]" v-else></span>
                        {{ activeViewMeta.name }}
                    </span>
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-teal-50 text-[#0D9488] border border-teal-200/60 hidden sm:inline-block">
                        {{ activeViewMeta.badge }}
                    </span>
                    <svg
                        class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-600 transition-transform duration-200"
                        :class="{ 'rotate-180 text-[#0D9488]': isOpen }"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </div>

            <!-- Backdrop -->
            <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

            <!-- Menu Card -->
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-72 sm:w-80 bg-white border border-slate-200 rounded-xl shadow-xl z-50 py-2 overflow-hidden animate-in fade-in zoom-in-95 duration-150"
            >
                <!-- Header with user profile hint -->
                <div class="px-4 py-2 border-b border-slate-100 bg-slate-50/60">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Select Operational Perspective</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-xs font-semibold text-slate-700 truncate max-w-[180px]">
                            {{ userInfo?.name || $page.props.auth?.user?.name }}
                        </span>
                        <span class="text-[10px] text-[#0D9488] font-medium bg-[#F0FDFA] px-1.5 py-0.5 rounded truncate max-w-[110px]" :title="userInfo?.functional_roles_string">
                            {{ userInfo?.functional_roles_string || 'Authorized' }}
                        </span>
                    </div>
                </div>

                <!-- Roles List -->
                <div class="p-1 space-y-1">
                    <button
                        v-for="view in availableViews"
                        :key="view.key"
                        type="button"
                        @click="switchRoleView(view.key)"
                        class="w-full text-left px-3 py-2.5 rounded-lg flex items-start gap-3 transition-colors duration-150"
                        :class="view.key === activeViewKey
                            ? 'bg-[#F0FDFA] text-[#0D9488]'
                            : 'hover:bg-slate-50 text-slate-700'"
                    >
                        <!-- Icon indicator -->
                        <div
                            class="p-2 rounded-lg shrink-0 mt-0.5"
                            :class="view.key === activeViewKey
                                ? 'bg-[#0D9488] text-white shadow-xs'
                                : 'bg-slate-100 text-slate-500'"
                        >
                            <!-- Staff Icon -->
                            <svg v-if="view.key === 'staff'" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <!-- Supervisor Icon -->
                            <svg v-else-if="view.key === 'supervisor'" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                            </svg>
                            <!-- Dept Head Icon -->
                            <svg v-else-if="view.key === 'department_head'" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                            </svg>
                            <!-- Admin Icon -->
                            <svg v-else class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <span class="font-semibold text-xs text-slate-800" :class="{ 'text-[#0D9488] font-bold': view.key === activeViewKey }">
                                    {{ view.name }}
                                </span>
                                <span v-if="view.key === activeViewKey" class="text-[#0D9488]">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 line-clamp-2 mt-0.5 leading-snug">
                                {{ view.description }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
