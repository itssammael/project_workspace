<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    subtaskIndex: Number,
    form: Object
});

const emit = defineEmits(['close', 'add-requirement', 'remove-requirement']);

const modalRequirements = ref([]);

const parseDeliverables = (str) => {
    if (!str) return [];
    return str.split(', ').map(item => {
        if (item.startsWith('File Upload: ')) {
            return { type: 'File Upload', value: item.replace('File Upload: ', '') };
        } else if (item.startsWith('Commit ID: ')) {
            return { type: 'Commit ID', value: item.replace('Commit ID: ', '') };
        } else if (item.startsWith('Ticket Link: ')) {
            return { type: 'Ticket Link', value: item.replace('Ticket Link: ', '') };
        } else if (item === "PM's Approval") {
            return { type: 'Approval', value: "PM's Approval" };
        } else {
            return { type: 'File Upload', value: item };
        }
    });
};

watch(() => props.show, (newVal) => {
    if (newVal && props.subtaskIndex !== null && props.subtaskIndex !== undefined) {
        const subtask = props.form.subtasks[props.subtaskIndex];
        if (subtask) {
            modalRequirements.value = parseDeliverables(subtask.deliverables);
            if (modalRequirements.value.length === 0) {
                modalRequirements.value.push({ type: 'File Upload', value: '' });
            }
        }
    } else {
        modalRequirements.value = [];
    }
});

const addRequirementRow = () => {
    modalRequirements.value.push({ type: 'File Upload', value: '' });
    emit('add-requirement', props.subtaskIndex);
};

const removeRequirementRow = (idx) => {
    modalRequirements.value.splice(idx, 1);
    emit('remove-requirement', props.subtaskIndex, idx);
};

const onRequirementTypeChange = (row) => {
    if (row.type === 'Approval') {
        row.value = "PM's Approval";
    }else if (row.type === 'Commit ID') {
        row.value = "Attach the Repository Commit ID";
    }else if (row.type === 'Ticket Link') {
        row.value = "Provide the Ticket Link";
    }else {
        row.value = "";
    }
};

const saveRequirements = () => {
    if (props.subtaskIndex === null || props.subtaskIndex === undefined) return;
    const subtask = props.form.subtasks[props.subtaskIndex];
    
    // Compile to deliverables string
    subtask.deliverables = modalRequirements.value.map(r => {
        if (r.type === 'Approval') {
            return "PM's Approval";
        }
        if (r.type === 'Commit ID') {
            return 'Commit ID: ' + r.value;
        }
        if (r.type === 'Ticket Link') {
            return 'Ticket Link: ' + r.value;
        }
        return 'File Upload: ' + r.value;
    }).join(', ');
    
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden max-w-md w-full z-10 transform transition-all flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-sm">
                        Describe Requirements
                    </h3>
                    <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="saveRequirements" class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Requirements List</label>
                        <button 
                            type="button" 
                            @click="addRequirementRow" 
                            class="text-xs font-bold text-[#0D9488] hover:text-[#0f766e] flex items-center gap-1"
                        >
                            + Add Requirement
                        </button>
                    </div>

                    <div class="space-y-3 max-h-[250px] overflow-y-auto pr-1">
                        <div v-for="(row, idx) in modalRequirements" :key="idx" class="flex gap-2 items-center">
                            <select 
                                v-model="row.type" 
                                @change="onRequirementTypeChange(row)"
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs w-1/3"
                            >
                                <option value="File Upload">File Upload</option>
                                <option value="Approval">Approval</option>
                                <option value="Commit ID">Commit ID</option>
                                <option value="Ticket Link">Ticket Link</option>
                            </select>

                            <input 
                                type="text" 
                                v-model="row.value" 
                                :disabled="row.type === 'Approval'"
                                :pattern="row.type === 'Commit ID' ? '^[a-fA-F0-9]{40}$' : undefined"
                                :maxlength="row.type === 'Commit ID' ? 40 : undefined"
                                :minlength="row.type === 'Commit ID' ? 40 : undefined"
                                :title="row.type === 'Commit ID' ? 'A 40-character hexadecimal Commit ID is required' : undefined"
                                required
                                class="rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs flex-1 bg-white disabled:bg-slate-50 disabled:opacity-80"
                                :placeholder="row.type === 'Commit ID' ? 'Enter 40-char hex commit ID...' : (row.type === 'Ticket Link' ? 'Specify ticket link description...' : 'Specify file description...')"
                            />

                            <button 
                                type="button" 
                                @click="removeRequirementRow(idx)"
                                class="text-rose-500 hover:text-rose-700 p-1 transition"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="modalRequirements.length === 0" class="text-slate-400 text-xs text-center py-4">
                            No requirements defined yet. Click "+ Add Requirement".
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                        <button 
                            type="button" 
                            @click="$emit('close')" 
                            class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
