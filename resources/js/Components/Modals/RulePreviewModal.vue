<script setup>
const props = defineProps({
    show: Boolean,
    rule: Object
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show && rule" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="$emit('close')"></div>
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-xl w-full z-10 p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-lg">Rule Simulation Preview</h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                    <p class="font-bold text-slate-700">System Rule: <span class="text-[#0D9488]">{{ rule.name }}</span></p>
                    <p class="text-slate-500">{{ rule.description }}</p>
                </div>
                <div class="p-4 bg-teal-50 border border-teal-200 text-[#0F766E] rounded-xl text-xs space-y-1">
                    <p class="font-bold uppercase tracking-wider text-[10px]">Simulation Result:</p>
                    <p>Enforcement Status: <strong class="text-emerald-700">Active & Enforced System-Wide</strong></p>
                    <p>Configured Actions: <strong>{{ (rule.actions || []).join(', ') }}</strong></p>
                </div>
                <div class="flex justify-end">
                    <button @click="$emit('close')" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl">Close Simulation</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
