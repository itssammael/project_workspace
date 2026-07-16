<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    member: {
        type: Object,
        required: true,
    },
    allRoles: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: [Number, String],
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const isAttaching = ref(false);

const selectedRole = computed(() => {
    return props.allRoles.find(r => r.id === Number(props.modelValue));
});

const hasRoleGlobally = (roleId) => {
    // Check if the member has this role globally in their member_roles / member_role_ids
    if (props.member.member_roles) {
        return props.member.member_roles.some(r => r.id === roleId);
    }
    if (props.member.member_role_ids) {
        return props.member.member_role_ids.includes(roleId);
    }
    return false;
};

const selectRole = (role) => {
    if (!hasRoleGlobally(role.id)) return;
    emit('update:modelValue', role.id);
    isOpen.value = false;
};

const attachRole = (roleId) => {
    if (isAttaching.value) return;
    isAttaching.value = true;

    router.post(route('admin.members.attach-role', props.member.id), {
        member_role_id: roleId
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isAttaching.value = false;
            // Select this role immediately for the project
            emit('update:modelValue', roleId);
            isOpen.value = false;
        },
        onError: () => {
            isAttaching.value = false;
        }
    });
};
</script>

<template>
    <div class="relative w-full">
        <!-- Trigger button -->
        <button
            type="button"
            @click="isOpen = !isOpen"
            class="w-full flex items-center justify-between gap-2 px-3 py-2 border border-slate-200 rounded-lg shadow-sm bg-white text-left text-xs text-slate-700 focus:border-[#0D9488] focus:ring-1 focus:ring-[#0D9488] transition hover:bg-slate-50/50"
        >
            <span class="truncate font-medium">
                {{ selectedRole ? selectedRole.name : 'Choose role...' }}
            </span>
            <svg class="w-4 h-4 text-slate-400 shrink-0 transition" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <!-- Dropdown backdrop listener -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen"
            class="absolute left-0 mt-1 w-full bg-white border border-slate-150 rounded-xl shadow-xl z-50 py-1.5 max-h-[220px] overflow-y-auto"
        >
            <div
                v-for="role in allRoles"
                :key="role.id"
                class="flex items-center justify-between gap-2 px-3 py-1.5 text-xs transition"
                :class="[
                    hasRoleGlobally(role.id)
                        ? 'text-slate-700 hover:bg-teal-50/50 cursor-pointer font-medium'
                        : 'text-slate-400 bg-slate-50/10 cursor-default select-none'
                ]"
                @click="selectRole(role)"
            >
                <div class="flex items-center gap-1.5 min-w-0">
                    <span class="truncate">
                        {{ role.name }}
                    </span>
                    <!-- Label for unassigned roles -->
                    <span 
                        v-if="!hasRoleGlobally(role.id)" 
                        class="text-[9px] bg-slate-100 text-slate-500 rounded px-1.5 py-0.5 font-bold shrink-0 uppercase tracking-wider scale-90"
                    >
                       
                    </span>
                </div>
                
                <!-- Display "+" button for unassigned functional roles -->
                <button
                    v-if="!hasRoleGlobally(role.id)"
                    type="button"
                    @click.stop="attachRole(role.id)"
                    :disabled="isAttaching"
                    class="flex items-center justify-center w-5 h-5 rounded-md border border-slate-200 hover:border-[#0D9488] hover:bg-[#F0FDFA] hover:text-[#0D9488] transition text-slate-500 font-bold shrink-0 disabled:opacity-50"
                    title="Add globally to member roles"
                >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </button>

                <!-- Checkmark for selected role -->
                <svg
                    v-else-if="Number(modelValue) === role.id"
                    class="w-4 h-4 text-[#0D9488] shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2.5"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
        </div>
    </div>
</template>
