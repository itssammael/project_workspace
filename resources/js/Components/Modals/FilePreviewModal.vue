<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    filePath: String
});

const emit = defineEmits(['close']);

const isPdf = computed(() => props.filePath?.toLowerCase().endsWith('.pdf'));
const previewFileUrl = computed(() => props.filePath ? `/attachments/${props.filePath}` : '');

</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-[60] flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-10 transform transition-all flex flex-col max-w-4xl w-full">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg truncate max-w-2xl" :title="filePath">
                        Preview File: {{ filePath }}
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 flex items-center justify-center p-1 rounded-lg hover:bg-slate-100 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 bg-slate-50/50 flex items-center justify-center min-h-[300px]">
                    <iframe 
                        v-if="isPdf" 
                        :src="previewFileUrl" 
                        class="w-full h-[70vh] border-0 rounded-xl shadow-sm bg-white"
                    ></iframe>
                    
                    <img 
                        v-else 
                        :src="previewFileUrl" 
                        class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-sm border border-slate-100 bg-white"
                        alt="Image Preview"
                    />
                </div>
                
                <div class="px-6 py-4 border-t border-slate-100 flex justify-end bg-slate-50/50 gap-3">
                    <a :href="previewFileUrl" download class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm">
                        Download
                    </a>
                    <button @click="$emit('close')" class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition bg-white">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
