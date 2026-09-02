<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['close', 'submit']);

const jsonImport = ref('');

const submitForm = () => {
    emit('submit', jsonImport.value);
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" @click="$emit('close')"></div>
            <div class="bg-white rounded-2xl shadow-2xl border border-slate-100 max-w-xl w-full z-10 p-6 space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="font-bold text-slate-800 text-lg">Import System Rules from JSON</h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Paste JSON Array Payload</label>
                    <textarea v-model="jsonImport" rows="8" class="w-full rounded-xl border-slate-200 font-mono text-xs" placeholder='[ { "name": "Rule 1", "type": "page_access_rule", ... } ]'></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="$emit('close')" class="px-4 py-2 border text-xs font-semibold text-slate-700 rounded-xl">Cancel</button>
                    <button @click="submitForm" class="px-4 py-2 bg-[#0D9488] text-white text-xs font-bold rounded-xl">Import Payload</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
