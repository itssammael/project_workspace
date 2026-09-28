<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

const isOpen = ref(false);
const isLoading = ref(false);
const activeTab = ref('all'); // 'all', 'personal', 'system'

const notifications = ref([]);
const unreadCount = ref(0);
const personalCount = ref(0);
const systemCount = ref(0);

const dropdownRef = ref(null);

const fetchNotifications = async () => {
    try {
        isLoading.value = true;
        const res = await fetch('/notifications', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        if (res.ok) {
            const data = await res.json();
            notifications.value = data.notifications || [];
            unreadCount.value = data.unread_count || 0;
            personalCount.value = data.personal_count || 0;
            systemCount.value = data.system_count || 0;
        }
    } catch (err) {
        console.error('Failed to fetch notifications:', err);
    } finally {
        isLoading.value = false;
    }
};

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const markAsRead = async (notification) => {
    if (notification.read) return;
    notification.read = true;
    if (unreadCount.value > 0) unreadCount.value--;

    try {
        await fetch('/notifications/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ notification_key: notification.id }),
        });
    } catch (err) {
        console.error('Error marking notification as read:', err);
    }
};

const markAllAsRead = async () => {
    const unreadKeys = notifications.value.filter(n => !n.read).map(n => n.id);
    if (unreadKeys.length === 0) return;

    notifications.value.forEach(n => n.read = true);
    unreadCount.value = 0;

    try {
        await fetch('/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ notification_keys: unreadKeys }),
        });
    } catch (err) {
        console.error('Error marking all notifications as read:', err);
    }
};

const handleItemClick = (notification) => {
    markAsRead(notification);
    isOpen.value = false;

    // If notification has a task board, open it and highlight the task
    if (notification.task_board_id) {
        let url = typeof route === 'function'
            ? route('task-boards.show', notification.task_board_id)
            : `/task-boards/${notification.task_board_id}`;

        if (notification.task_id) {
            url += `?highlight_task=${notification.task_id}`;
        }

        router.visit(url);
    } else if (notification.action_url) {
        router.visit(notification.action_url);
    }
};

const filteredNotifications = computed(() => {
    if (activeTab.value === 'personal') {
        return notifications.value.filter(n => n.type === 'personal');
    }
    if (activeTab.value === 'system') {
        return notifications.value.filter(n => n.type === 'system');
    }
    return notifications.value;
});

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

let pollInterval = null;

onMounted(() => {
    fetchNotifications();
    document.addEventListener('click', handleClickOutside);
    // Poll every 60 seconds for fresh updates
    pollInterval = setInterval(fetchNotifications, 60000);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (pollInterval) clearInterval(pollInterval);
});

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const now = new Date();
    const diffSecs = Math.floor((now - date) / 1000);

    if (diffSecs < 60) return 'Just now';
    const diffMins = Math.floor(diffSecs / 60);
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
};

const getCategoryStyles = (category, urgency) => {
    switch (category) {
        case 'upcoming_task':
            return {
                bg: urgency === 'critical' ? 'bg-amber-100 text-amber-700' : 'bg-amber-50 text-amber-600',
                badge: urgency === 'critical' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200',
            };
        case 'overdue_task':
            return {
                bg: 'bg-rose-100 text-rose-700',
                badge: 'bg-rose-100 text-rose-800 border-rose-300 font-bold',
            };
        case 'task_submission':
            return {
                bg: 'bg-purple-100 text-purple-700',
                badge: 'bg-purple-50 text-purple-700 border-purple-200 font-semibold',
            };
        case 'task_assignment':
            return {
                bg: 'bg-blue-100 text-blue-700',
                badge: 'bg-blue-50 text-blue-700 border-blue-200',
            };
        case 'board_assignment':
        case 'board_created':
            return {
                bg: 'bg-teal-100 text-teal-700',
                badge: 'bg-teal-50 text-teal-700 border-teal-200',
            };
        case 'profile_change':
            return {
                bg: 'bg-slate-200 text-slate-700',
                badge: 'bg-slate-100 text-slate-700 border-slate-200',
            };
        case 'system_update':
        default:
            return {
                bg: 'bg-indigo-100 text-indigo-700',
                badge: 'bg-indigo-50 text-indigo-700 border-indigo-200',
            };
    }
};
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <!-- Bell Icon Trigger Button -->
        <button
            type="button"
            @click="toggleDropdown"
            class="p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition relative focus:outline-none focus:ring-2 focus:ring-[#0D9488]/30"
            title="System & Personal Notifications"
            aria-label="System Notifications"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>

            <!-- Unread Badge Counter -->
            <span
                v-if="unreadCount > 0"
                class="absolute -top-0.5 -right-0.5 flex h-4 min-w-[16px] px-1 items-center justify-center rounded-full bg-rose-500 text-[9px] font-extrabold text-white ring-2 ring-white shadow-xs animate-in zoom-in duration-200"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
            <span
                v-else
                class="w-2 h-2 rounded-full bg-[#0D9488] absolute top-1.5 right-1.5 ring-2 ring-white opacity-40"
            ></span>
        </button>

        <!-- Dynamic Floating Dropdown -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 mt-2 w-80 sm:w-96 md:w-[440px] bg-white rounded-2xl shadow-2xl border border-slate-200/90 z-50 overflow-hidden divide-y divide-slate-100 backdrop-blur-md"
            >
                <!-- Dropdown Header -->
                <div class="px-4 py-3 bg-slate-50/90 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#0D9488]"></span>
                        <h3 class="font-bold text-sm text-slate-800">Notifications</h3>
                        <span 
                            v-if="unreadCount > 0" 
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-rose-50 text-rose-600 border border-rose-200"
                        >
                            {{ unreadCount }} new
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            @click="markAllAsRead"
                            class="text-xs font-semibold text-[#0D9488] hover:text-teal-700 hover:underline transition"
                        >
                            Mark all as read
                        </button>
                    </div>
                </div>

                <!-- Tabs: All / Personal / System -->
                <div class="px-4 pt-2 pb-2 bg-slate-50/40 flex items-center gap-1 border-b border-slate-100 text-xs">
                    <button
                        type="button"
                        @click="activeTab = 'all'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-semibold transition flex items-center gap-1.5',
                            activeTab === 'all' 
                                ? 'bg-white text-slate-800 shadow-xs border border-slate-200' 
                                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        <span>All</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600 font-bold">
                            {{ notifications.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'personal'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-semibold transition flex items-center gap-1.5',
                            activeTab === 'personal' 
                                ? 'bg-white text-slate-800 shadow-xs border border-slate-200' 
                                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        <span>Personal</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-teal-50 text-teal-700 font-bold">
                            {{ personalCount }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'system'"
                        :class="[
                            'px-2.5 py-1 rounded-lg font-semibold transition flex items-center gap-1.5',
                            activeTab === 'system' 
                                ? 'bg-white text-slate-800 shadow-xs border border-slate-200' 
                                : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'
                        ]"
                    >
                        <span>System</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-indigo-50 text-indigo-700 font-bold">
                            {{ systemCount }}
                        </span>
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 py-1">
                    <!-- If items exist -->
                    <template v-if="filteredNotifications.length > 0">
                        <div
                            v-for="item in filteredNotifications"
                            :key="item.id"
                            @click="handleItemClick(item)"
                            class="px-4 py-3 hover:bg-slate-50/80 transition cursor-pointer flex items-start gap-3 relative group select-none"
                            :class="[!item.read ? 'bg-teal-50/20' : 'opacity-90']"
                        >
                            <!-- Unread Indicator Dot -->
                            <span 
                                v-if="!item.read" 
                                class="w-2 h-2 rounded-full bg-[#0D9488] shrink-0 mt-1.5 ring-2 ring-teal-200"
                                title="Unread"
                            ></span>

                            <!-- Category Icon -->
                            <div 
                                class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center border shadow-2xs"
                                :class="getCategoryStyles(item.category, item.urgency).bg"
                            >
                                <!-- Clock / Upcoming Icon -->
                                <svg v-if="item.icon === 'clock'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <!-- Warning / Overdue Icon -->
                                <svg v-else-if="item.icon === 'exclamation-circle'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                                <!-- Task Submission Check Icon -->
                                <svg v-else-if="item.icon === 'check-badge'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                                </svg>
                                <!-- Task Assignment User Icon -->
                                <svg v-else-if="item.icon === 'user-plus'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                                </svg>
                                <!-- Board Assignment Icon -->
                                <svg v-else-if="item.icon === 'user-group' || item.icon === 'squares-plus'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.375c1.88 0 3.42-1.59 3.42-3.56c0-1.97-1.54-3.56-3.42-3.56H9v7.12z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h.008v.008H3.75V12zm0 3h.008v.008H3.75V15zm0-6h.008v.008H3.75V9zm3-3H6.758v.008H6.75V6zm0 12h.008v.008H6.75V18z" />
                                </svg>
                                <!-- Profile Security Icon -->
                                <svg v-else-if="item.icon === 'shield-check'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                </svg>
                                <!-- Default Cog Icon -->
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                                </svg>
                            </div>

                            <!-- Notification Body -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1.5">
                                    <span 
                                        :class="['text-[9px] px-1.5 py-0.2 rounded font-bold uppercase tracking-wider border shrink-0', getCategoryStyles(item.category, item.urgency).badge]"
                                    >
                                        {{ item.badge || item.title }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 shrink-0">
                                        {{ formatTimeAgo(item.created_at) }}
                                    </span>
                                </div>

                                <!-- Main Message -->
                                <p class="text-xs text-slate-800 font-medium mt-1 leading-snug break-words">
                                    {{ item.message }}
                                </p>

                                <!-- Context Meta (Board / Workflow / Due date) -->
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-500 font-medium">
                                    <span v-if="item.task_board_name" class="text-teal-700 font-semibold truncate max-w-[160px]">
                                        {{ item.task_board_name }}
                                    </span>
                                    <span v-if="item.workflow_name" class="text-slate-400">
                                        &bull; {{ item.workflow_name }}
                                    </span>
                                    <span v-if="item.due_date" class="text-amber-700 font-semibold">
                                        &bull; Due: {{ item.due_date }}
                                    </span>
                                    <span v-if="item.performed_by" class="text-slate-400">
                                        &bull; By {{ item.performed_by }}
                                    </span>
                                </div>
                            </div>

                            <!-- Right Arrow (on hover) -->
                            <div class="shrink-0 self-center text-slate-300 group-hover:text-[#0D9488] group-hover:translate-x-0.5 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="py-8 px-4 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mx-auto border border-slate-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                            </svg>
                        </div>
                        <h5 class="text-xs font-bold text-slate-700">All caught up!</h5>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto">
                            No notifications in this category right now.
                        </p>
                    </div>
                </div>

                <!-- Dropdown Footer -->
                <div class="px-4 py-2 bg-slate-50/70 flex items-center justify-between text-[10px] text-slate-400">
                    <span>Clicking an item opens the task board & highlights the task</span>
                    <button 
                        type="button" 
                        @click="fetchNotifications" 
                        class="hover:text-slate-600 transition flex items-center gap-1 font-semibold"
                        title="Refresh notifications"
                    >
                        <svg class="w-3 h-3" :class="{ 'animate-spin': isLoading }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
