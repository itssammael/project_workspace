<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: Boolean,
    mode: String,
    form: Object,
    selectedTask: Object,
    teamMembers: Array,
    currentMemberId: [Number, String],
    canManageTasks: Boolean,
    attachmentForms: Object,
    commentInputs: Object,
    collapsedSubtasks: Object,
    projectStartDate: String,
    workflows: Array,
    canDeleteTask: Boolean
});

const emit = defineEmits([
    'close', 
    'switch-mode', 
    'submit', 
    'add-subtask', 
    'remove-subtask',
    'toggle-subtask-collapse', 
    'open-requirements', 
    'open-preview',
    'submit-attachment', 
    'submit-comment', 
    'delete-task'
]);

const getFileExtension = (filename) => {
    if (!filename) return 'FILE';
    const parts = filename.split('.');
    if (parts.length < 2) return 'FILE';
    return parts[parts.length - 1].toUpperCase();
};

const isViewableFile = (filename) => {
    if (!filename) return false;
    const nameLower = filename.toLowerCase();
    return nameLower.endsWith('.jpg') || nameLower.endsWith('.jpeg') || nameLower.endsWith('.png') || nameLower.endsWith('.pdf');
};

const isSubtaskCollapsed = (index) => {
    return !!props.collapsedSubtasks[index];
};

const onRequirementsCheckboxChange = (index, isChecked) => {
    const subtask = props.form.subtasks[index];
    subtask.has_requirements = isChecked;
    if (isChecked) {
        emit('open-requirements', index);
    } else {
        subtask.deliverables = '';
    }
};

const handleFileChange = (event, stId) => {
    if (!props.attachmentForms[stId]) {
        props.attachmentForms[stId] = { type: 'File Upload', value: '', file: null };
    }
    props.attachmentForms[stId].file = event.target.files[0];
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-10 transform transition-all flex flex-col" :class="mode === 'view' || mode === 'status_only' ? 'max-w-lg w-full' : 'max-w-4xl w-full'">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="font-bold text-slate-800 text-lg">
                        {{ mode === 'create' ? 'Add New Task' : mode === 'edit' ? 'Edit Task Details' : mode === 'status_only' ? 'Update Task Status' : 'Task Details' }}
                    </h3>
                    
                    <div class="flex items-center gap-3">
                        <button 
                            v-if="canManageTasks && mode !== 'create'" 
                            type="button" 
                            @click="$emit('switch-mode', mode === 'edit' ? 'view' : 'edit')"
                            class="px-3 py-1.5 border border-slate-205 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition shadow-sm"
                        >
                            {{ mode === 'edit' ? 'Switch to View Details' : 'Switch to Edit Task' }}
                        </button>
                        
                        <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-600 flex items-center justify-center p-1 rounded-lg hover:bg-slate-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form @submit.prevent="$emit('submit')" class="p-6 space-y-4">
                    <!-- Read-Only View -->
                    <div v-if="mode === 'view'" class="space-y-4 text-sm text-slate-600">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Task Name</span>
                            <span class="text-slate-800 font-bold text-base mt-1 block">{{ selectedTask?.name }}</span>
                        </div>
                        <div v-if="selectedTask?.details">
                            <span class="block text-xs font-semibold text-slate-400 uppercase">Details</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ selectedTask?.details }}</p>
                        </div>
                        
                        <div class="border-t border-slate-100 pt-4">
                            <h4 class="font-bold text-slate-800 text-sm mb-3">Subtasks</h4>
                            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                <div v-for="st in selectedTask?.sub_tasks" :key="st.id" class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                                    <div class="flex justify-between items-start gap-2">
                                        <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                            {{ st.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 text-[11px] text-slate-400">
                                        <div><strong>Duration:</strong> {{ st.duration }} Days</div>
                                        <div><strong>Start:</strong> {{ st.start_date }}</div>
                                        <div><strong>Assignee:</strong> {{ st.member?.user?.name || st.member?.name || 'Unassigned' }}</div>
                                    </div>
                                    <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                        <strong>Deliverables:</strong> {{ st.deliverables }}
                                    </div>

                                    <!-- Deliverables Attachments & Comments Section -->
                                    <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                        <!-- Attachments -->
                                        <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                            <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                            <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                                <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                            {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                        </span>
                                                        <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                    </div>
                                                    <div class="shrink-0">
                                                        <button 
                                                            v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                            type="button"
                                                            @click="$emit('open-preview', att.attachment)" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                        >
                                                            View File
                                                        </button>
                                                        <a 
                                                            v-else-if="att.attachment_type === 'File Upload'" 
                                                            :href="'/attachments/' + att.attachment" 
                                                            target="_blank" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                        >
                                                            View File
                                                        </a>
                                                        <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                            Open Link
                                                        </a>
                                                        <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                        </div>

                                        <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                        <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                    <option value="File Upload">File Upload</option>
                                                    <option value="Commit ID">Commit ID</option>
                                                    <option value="Ticket Link">Ticket Link</option>
                                                </select>
                                                
                                                <div class="flex-1">
                                                    <input 
                                                        v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                        type="file" 
                                                        @change="handleFileChange($event, st.id)" 
                                                        class="block w-full text-xs text-slate-550 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                    />
                                                    <input 
                                                        v-else 
                                                        type="text" 
                                                        v-model="attachmentForms[st.id].value" 
                                                        class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                        placeholder="Enter link, commit ID or text..."
                                                    />
                                                </div>
                                                
                                                <button type="button" @click="$emit('submit-attachment', st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                    Attach
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Comments Section -->
                                        <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                            <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                            
                                            <!-- Comments list -->
                                            <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                                <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                    <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                        <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                        <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                    </div>
                                                    <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                            
                                            <!-- Comment input form -->
                                            <div class="flex gap-2 mt-2">
                                                <input 
                                                    type="text" 
                                                    v-model="commentInputs[st.id]" 
                                                    class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Write a comment..."
                                                    @keyup.enter="$emit('submit-comment', st.id)"
                                                />
                                                <button type="button" @click="$emit('submit-comment', st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                    Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Only Form -->
                    <div v-else-if="mode === 'status_only'" class="space-y-4">
                        <div>
                            <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Task Name</span>
                            <span class="text-slate-800 font-bold block">{{ selectedTask?.name }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Update Status</label>
                            <select v-model="form.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="submitted">Submitted</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <!-- List of assignee's subtasks with attachments & comments -->
                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <h4 class="font-bold text-slate-800 text-sm mb-3">Your Subtasks & Comments</h4>
                            <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                                <div 
                                    v-for="st in selectedTask?.sub_tasks?.filter(s => s.member_id === currentMemberId)" 
                                    :key="st.id" 
                                    class="border border-slate-100 rounded-xl p-4 bg-slate-50/50"
                                >
                                    <div class="flex justify-between items-start gap-2">
                                        <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                            {{ st.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                    <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                        <strong>Deliverables:</strong> {{ st.deliverables }}
                                    </div>
                                    
                                    <!-- Deliverables Attachments & Comments Section -->
                                    <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                        <!-- Attachments -->
                                        <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                            <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                            <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                                <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                            {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                        </span>
                                                        <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                    </div>
                                                    <div class="shrink-0">
                                                        <button 
                                                            v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                            type="button"
                                                            @click="$emit('open-preview', att.attachment)" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                        >
                                                            View File
                                                        </button>
                                                        <a 
                                                            v-else-if="att.attachment_type === 'File Upload'" 
                                                            :href="'/attachments/' + att.attachment" 
                                                            target="_blank" 
                                                            class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                        >
                                                            View File
                                                        </a>
                                                        <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                            Open Link
                                                        </a>
                                                        <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                        </div>

                                        <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                        <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                    <option value="File Upload">File Upload</option>
                                                    <option value="Commit ID">Commit ID</option>
                                                    <option value="Ticket Link">Ticket Link</option>
                                                </select>
                                                
                                                <div class="flex-1">
                                                    <input 
                                                        v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                        type="file" 
                                                        @change="handleFileChange($event, st.id)" 
                                                        class="block w-full text-xs text-slate-555 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                    />
                                                    <input 
                                                        v-else 
                                                        type="text" 
                                                        v-model="attachmentForms[st.id].value" 
                                                        class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                        placeholder="Enter link, commit ID or text..."
                                                    />
                                                </div>
                                                
                                                <button type="button" @click="$emit('submit-attachment', st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                    Attach
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Comments Section -->
                                        <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                            <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                            
                                            <!-- Comments list -->
                                            <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                                <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                    <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                        <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                        <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                    </div>
                                                    <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                                </div>
                                            </div>
                                            <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                            
                                            <!-- Comment input form -->
                                            <div class="flex gap-2 mt-2">
                                                <input 
                                                    type="text" 
                                                    v-model="commentInputs[st.id]" 
                                                    class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Write a comment..."
                                                    @keyup.enter="$emit('submit-comment', st.id)"
                                                />
                                                <button type="button" @click="$emit('submit-comment', st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                    Send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Full Create/Edit Form -->
                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Left Column: Task Group Details -->
                            <div class="md:col-span-1 space-y-4">
                                <h4 class="font-bold text-slate-800 text-sm border-b pb-2">Task Details</h4>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Task Name</label>
                                    <input type="text" v-model="form.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="e.g. Implement Checkout Flow" />
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Details / Description</label>
                                    <textarea v-model="form.details" rows="5" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-sm" placeholder="Detail the task scope..."></textarea>
                                </div>
                            </div>

                            <!-- Right Column: Subtasks List -->
                            <div class="md:col-span-2 space-y-4">
                                <div class="flex justify-between items-center border-b pb-2">
                                    <h4 class="font-bold text-slate-800 text-sm">Subtasks Setup</h4>
                                    <button type="button" @click="$emit('add-subtask')" class="inline-flex items-center gap-1.5 text-[#0D9488] hover:text-[#0f766e] text-xs font-bold transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        Add Subtask
                                    </button>
                                </div>

                                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                                    <div 
                                        v-for="(subtask, index) in form.subtasks" 
                                        :key="index" 
                                        class="border border-slate-100 rounded-xl overflow-hidden bg-white shadow-sm transition-all"
                                    >
                                        <!-- Subtask Header (Togglable) -->
                                        <div 
                                            @click="$emit('toggle-subtask-collapse', index)"
                                            class="flex justify-between items-center p-3 bg-slate-50/50 hover:bg-slate-50 cursor-pointer select-none transition border-b border-transparent"
                                            :class="{ 'border-slate-100': !isSubtaskCollapsed(index) }"
                                        >
                                            <div class="flex items-center gap-2 min-w-0">
                                                <!-- Collapse Arrow -->
                                                <svg 
                                                    class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
                                                    :class="{ 'rotate-180': !isSubtaskCollapsed(index) }"
                                                    fill="none" 
                                                    viewBox="0 0 24 24" 
                                                    stroke-width="2.5" 
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider shrink-0">Subtask #{{ index + 1 }}</span>
                                                <span 
                                                    class="text-xs font-semibold text-slate-800 truncate"
                                                    v-if="isSubtaskCollapsed(index)"
                                                >
                                                    &mdash; {{ subtask.name || 'Untitled Subtask' }}
                                                </span>
                                            </div>

                                            <!-- Remove Action -->
                                            <button 
                                                type="button" 
                                                v-if="form.subtasks.length > 1" 
                                                @click.stop="$emit('remove-subtask', index)" 
                                                class="flex items-center justify-center p-1 rounded hover:bg-rose-50 text-rose-500 hover:text-rose-700 transition shrink-0"
                                                title="Delete Subtask"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Subtask Body Fields -->
                                        <div v-show="!isSubtaskCollapsed(index)" class="p-4 space-y-3">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subtask Name</label>
                                                    <input type="text" v-model="subtask.name" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" placeholder="e.g. Write integration tests" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Key Deliverables</label>
                                                    <input type="text" v-model="subtask.deliverables" :disabled="subtask.has_requirements" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs bg-slate-50 disabled:opacity-80 disabled:cursor-not-allowed" placeholder="e.g. Unit tests, test suites" />
                                                    
                                                    <!-- Requirements checkbox -->
                                                    <div class="flex items-center gap-2 mt-1.5">
                                                        <input 
                                                            type="checkbox" 
                                                            :id="'req-check-' + index"
                                                            v-model="subtask.has_requirements"
                                                            @change="onRequirementsCheckboxChange(index, $event.target.checked)"
                                                            class="rounded text-[#0D9488] border-slate-300 focus:ring-[#0D9488] h-3.5 w-3.5"
                                                        />
                                                        <label :for="'req-check-' + index" class="text-[11px] font-semibold text-slate-600 cursor-pointer select-none">
                                                            Add Requirements
                                                        </label>
                                                        
                                                        <button
                                                            v-if="subtask.has_requirements"
                                                            type="button"
                                                            @click="$emit('open-requirements', index)"
                                                            class="text-[10px] text-[#0D9488] hover:underline font-bold"
                                                        >
                                                            (Edit Requirements)
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Details</label>
                                                <textarea v-model="subtask.details" rows="2" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" placeholder="Details of this specific subtask..."></textarea>
                                            </div>

                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Duration (d)</label>
                                                    <input type="number" v-model="subtask.duration" min="1" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Start Date</label>
                                                    <input type="date" v-model="subtask.start_date" required class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs" />
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Assignee</label>
                                                    <select v-model="subtask.member_id" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs">
                                                        <option value="">Unassigned</option>
                                                        <option v-for="member in teamMembers" :key="member.id" :value="member.id">
                                                            {{ member.name }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                                                    <select v-model="subtask.status" class="w-full rounded-lg border-slate-200 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] text-xs">
                                                        <option value="pending">Pending</option>
                                                        <option value="in_progress">In Progress</option>
                                                        <option value="submitted">Submitted</option>
                                                        <option value="completed">Completed</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subtask Deliverables, Attachments & Comments (Edit Mode) -->
                    <div v-if="mode === 'edit' && selectedTask?.sub_tasks && selectedTask.sub_tasks.length > 0" class="border-t border-slate-100 pt-4 mt-6">
                        <h4 class="font-bold text-slate-800 text-sm mb-3">Subtasks Deliverables, Attachments & Comments</h4>
                        <div class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                            <div v-for="st in selectedTask?.sub_tasks" :key="st.id" class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
                                <div class="flex justify-between items-start gap-2">
                                    <h5 class="font-bold text-slate-800 text-sm">{{ st.name }}</h5>
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase border bg-white shrink-0" :class="st.status === 'completed' ? 'text-teal-700 bg-teal-50 border-teal-200' : st.status === 'submitted' ? 'text-indigo-700 bg-indigo-50 border-indigo-200' : st.status === 'in_progress' ? 'text-blue-700 bg-blue-50 border-blue-200' : 'text-slate-600 border-slate-200'">
                                        {{ st.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p v-if="st.details" class="text-xs text-slate-500 mt-1">{{ st.details }}</p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 text-[11px] text-slate-400">
                                    <div><strong>Duration:</strong> {{ st.duration }} Days</div>
                                    <div><strong>Start:</strong> {{ st.start_date }}</div>
                                    <div><strong>Assignee:</strong> {{ st.member?.name || 'Unassigned' }}</div>
                                </div>
                                <div v-if="st.deliverables" class="text-[11px] text-slate-500 mt-1.5">
                                    <strong>Deliverables:</strong> {{ st.deliverables }}
                                </div>
                                
                                <!-- Deliverables Attachments & Comments Section -->
                                <div class="mt-4 pt-3 border-t border-slate-200/60 space-y-4">
                                    <!-- Attachments -->
                                    <div v-if="(st.attachments && st.attachments.length > 0) || (st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link')))">
                                        <div class="text-[11px] font-bold text-slate-700 mb-1.5">Attachments & Deliverables</div>
                                        <div v-if="st.attachments && st.attachments.length > 0" class="space-y-1">
                                            <div v-for="att in st.attachments" :key="att.id" class="flex items-center justify-between bg-white border border-slate-100 rounded-xl p-2 text-xs">
                                                <div class="flex items-center gap-1.5 min-w-0">
                                                    <span class="px-1.5 py-0.5 text-[8px] font-bold rounded uppercase border shrink-0 bg-slate-50 text-slate-500" :class="att.attachment_type === 'File Upload' ? 'text-teal-700 border-teal-200 bg-teal-50/50' : 'text-blue-700 border-blue-200 bg-blue-50/50'">
                                                        {{ att.attachment_type === 'File Upload' ? getFileExtension(att.attachment) : att.attachment_type }}
                                                    </span>
                                                    <span class="text-slate-650 truncate max-w-[200px]" :title="att.attachment">{{ att.attachment }}</span>
                                                </div>
                                                <div class="shrink-0">
                                                    <button 
                                                        v-if="att.attachment_type === 'File Upload' && isViewableFile(att.attachment)" 
                                                        type="button"
                                                        @click="$emit('open-preview', att.attachment)" 
                                                        class="text-[#0D9488] hover:underline font-bold text-[10px] bg-transparent border-0 p-0"
                                                    >
                                                        View File
                                                    </button>
                                                    <a 
                                                        v-else-if="att.attachment_type === 'File Upload'" 
                                                        :href="'/attachments/' + att.attachment" 
                                                        target="_blank" 
                                                        class="text-[#0D9488] hover:underline font-bold text-[10px]"
                                                    >
                                                        View File
                                                    </a>
                                                    <a v-else-if="att.attachment.startsWith('http')" :href="att.attachment" target="_blank" class="text-blue-600 hover:underline font-bold text-[10px]">
                                                        Open Link
                                                    </a>
                                                    <span v-else class="text-slate-450 text-[10px] italic">Text attached</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-[11px] text-slate-400 italic">No attachments yet.</div>
                                    </div>

                                    <!-- Attachment Form (Only for subtask assignee when deliverables specify attachments) -->
                                    <div v-if="st.member_id === currentMemberId && st.deliverables && (st.deliverables.includes('File Upload') || st.deliverables.includes('Commit ID') || st.deliverables.includes('Ticket Link'))" class="bg-slate-100/50 rounded-xl p-3 border border-slate-200/40 space-y-2">
                                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Submit Deliverable Attachment</div>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <select v-model="attachmentForms[st.id].type" class="rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white">
                                                <option value="File Upload">File Upload</option>
                                                <option value="Commit ID">Commit ID</option>
                                                <option value="Ticket Link">Ticket Link</option>
                                            </select>
                                            
                                            <div class="flex-1">
                                                <input 
                                                    v-if="attachmentForms[st.id].type === 'File Upload'" 
                                                    type="file" 
                                                    @change="handleFileChange($event, st.id)" 
                                                    class="block w-full text-xs text-slate-555 file:mr-3 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[11px] file:font-semibold file:bg-teal-55 file:text-[#0d9488] hover:file:bg-teal-100"
                                                />
                                                <input 
                                                    v-else 
                                                    type="text" 
                                                    v-model="attachmentForms[st.id].value" 
                                                    class="w-full rounded-lg border-slate-200 text-xs py-1 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                    placeholder="Enter link, commit ID or text..."
                                                />
                                            </div>
                                            
                                            <button type="button" @click="$emit('submit-attachment', st.id)" class="px-3 py-1 bg-[#0D9488] hover:bg-[#0f766e] text-white text-xs font-bold rounded-lg transition shadow-sm self-end sm:self-auto">
                                                Attach
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Comments Section -->
                                    <div v-if="st.can_comment" class="space-y-2 mt-3 pt-3 border-t border-slate-200/40">
                                        <div class="text-[11px] font-bold text-slate-700">Subtask Comments Thread</div>
                                        
                                        <!-- Comments list -->
                                        <div v-if="st.comments && st.comments.length > 0" class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                                            <div v-for="c in st.comments" :key="c.id" class="bg-white border border-slate-100 rounded-xl p-2.5 text-xs space-y-1.5 shadow-sm">
                                                <div class="flex justify-between items-center text-[9px] text-slate-400">
                                                    <span class="font-bold text-slate-600">{{ c.member.name }}</span>
                                                    <span>{{ new Date(c.created_at).toLocaleString() }}</span>
                                                </div>
                                                <p class="text-slate-700 leading-relaxed text-[11px]">{{ c.comment }}</p>
                                            </div>
                                        </div>
                                        <div v-else class="text-[11px] text-slate-400 italic">No comments yet.</div>
                                        
                                        <!-- Comment input form -->
                                        <div class="flex gap-2 mt-2">
                                            <input 
                                                type="text" 
                                                v-model="commentInputs[st.id]" 
                                                class="flex-1 rounded-lg border-slate-200 text-xs py-1.5 focus:border-[#0D9488] focus:ring-[#0D9488] bg-white" 
                                                placeholder="Write a comment..."
                                                @keyup.enter="$emit('submit-comment', st.id)"
                                            />
                                            <button type="button" @click="$emit('submit-comment', st.id)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                                                Send
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-between items-center pt-4 border-t border-slate-100 mt-6">
                        <div>
                            <button 
                                v-if="mode === 'edit'" 
                                type="button" 
                                @click="$emit('delete-task')" 
                                class="inline-flex items-center px-4 py-2 border border-rose-200 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 transition"
                            >
                                Delete Task
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <button 
                                type="button" 
                                @click="$emit('close')" 
                                class="px-4 py-2 border border-slate-200 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-50 transition"
                            >
                                Close
                            </button>
                            <button 
                                v-if="mode !== 'view'" 
                                type="submit" 
                                :disabled="form.processing"
                                class="px-4 py-2 bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] text-white text-xs font-bold rounded-lg focus:outline-none focus:ring-2 focus:ring-[#0D9488] focus:ring-offset-2 transition shadow-sm"
                            >
                                Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
