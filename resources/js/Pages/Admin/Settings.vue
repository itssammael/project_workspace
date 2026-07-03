<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
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
        form.logo = file;
        previewUrl.value = URL.createObjectURL(file);
        form.remove_logo = false;
    }
};

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleRemoveLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            successMessage.value = 'Settings saved successfully!';
            setTimeout(() => {
                successMessage.value = '';
            }, 3000);
        },
    });
};

const presets = [
    {
        id: 'refined_indigo',
        name: 'Refined Indigo & Slate',
        desc: 'Rich deep indigo buttons, slate dark text, and soft iris active states.',
        colors: {
            primary: '#4338CA',
            secondary: '#EEF2FF',
            text: '#1E293B',
            bg: '#F8FAFC',
        }
    },
    {
        id: 'corporate_teal',
        name: 'Corporate Teal & Charcoal',
        desc: 'Highly professional deep teal, soft mint highlights, and dark charcoal text.',
        colors: {
            primary: '#0D9488',
            secondary: '#F0FDFA',
            text: '#0F172A',
            bg: '#F1F5F9',
        }
    },
    {
        id: 'modern_midnight',
        name: 'Modern Midnight & Obsidian',
        desc: 'Neon violet accents against Obsidian cards and Midnight dark backgrounds.',
        colors: {
            primary: '#7C3AED',
            secondary: '#1E1E2F',
            text: '#F3F4F6',
            bg: '#12121A',
        }
    }
];
</script>

<template>
    <AppLayout title="System Settings">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                System Settings
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Success Message -->
                <div v-if="successMessage" class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg shadow-sm flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ successMessage }}</span>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 lg:p-8">
                    <form @submit.prevent="submit" class="space-y-8">
                        
                        <!-- General Settings Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-6">General Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                
                                <!-- System Name -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">System Name</label>
                                    <input 
                                        type="text" 
                                        v-model="form.system_name" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#0D9488] focus:ring-[#0D9488] sm:text-sm"
                                        placeholder="e.g. Project Tracker"
                                        required
                                    />
                                    <p class="mt-1 text-xs text-gray-500">This name will be displayed in browser tabs and across the app layout headers.</p>
                                </div>

                                <!-- Logo Uploader -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">System Logo</label>
                                    <div class="flex items-center space-x-6 mt-1">
                                        <!-- Preview -->
                                        <div class="h-16 w-16 rounded border border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden">
                                            <img v-if="previewUrl" :src="previewUrl" class="h-full w-full object-contain" />
                                            <span v-else class="text-xs text-gray-400">No Logo</span>
                                        </div>

                                        <!-- Buttons -->
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
                                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0D9488]"
                                            >
                                                Upload Logo
                                            </button>
                                            <button 
                                                v-if="previewUrl"
                                                type="button" 
                                                @click="handleRemoveLogo"
                                                class="ml-2 px-4 py-2 border border-transparent rounded-md text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            >
                                                Remove
                                            </button>
                                            <p class="text-xs text-gray-500">Supports PNG, JPG, or SVG up to 2MB.</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Theme Settings Section -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 border-b pb-2 mb-6">Preset Color Palette</h3>
                            <p class="text-sm text-gray-500 mb-6">Choose a brand identity system to skin your Project Tracker workspace. Hover for description, click to apply.</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div 
                                    v-for="preset in presets" 
                                    :key="preset.id"
                                    @click="form.theme = preset.id"
                                    :class="[
                                        'relative flex flex-col p-5 border rounded-xl cursor-pointer transition duration-200 select-none shadow-sm hover:shadow-md',
                                        form.theme === preset.id 
                                            ? 'border-[#0D9488] ring-2 ring-[#0D9488] bg-teal-50/20' 
                                            : 'border-gray-200 bg-white hover:border-gray-300'
                                    ]"
                                >
                                    <!-- Selected Indicator Checkmark -->
                                    <div v-if="form.theme === preset.id" class="absolute top-3 right-3 text-[#0D9488]">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>

                                    <!-- Swatch Info -->
                                    <span class="font-bold text-gray-900 text-base">{{ preset.name }}</span>
                                    <span class="text-xs text-gray-500 mt-1 mb-4 leading-relaxed">{{ preset.desc }}</span>

                                    <!-- Swatches Display -->
                                    <div class="flex items-center space-x-2 mt-auto">
                                        <!-- Primary Color -->
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border border-gray-300/40 shadow-sm" :style="{ backgroundColor: preset.colors.primary }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Primary</span>
                                        </div>
                                        <!-- Secondary Color -->
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border border-gray-300/40 shadow-sm" :style="{ backgroundColor: preset.colors.secondary }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Interactiv.</span>
                                        </div>
                                        <!-- Text Color -->
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border border-gray-300/40 shadow-sm" :style="{ backgroundColor: preset.colors.text }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Text</span>
                                        </div>
                                        <!-- Background Color -->
                                        <div class="flex flex-col items-center">
                                            <div class="w-8 h-8 rounded-full border border-gray-300/40 shadow-sm" :style="{ backgroundColor: preset.colors.bg }"></div>
                                            <span class="text-[10px] text-gray-400 mt-1">Bg</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="pt-6 border-t flex justify-end">
                            <button 
                                type="submit" 
                                :disabled="form.processing"
                                class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-md shadow-sm text-white bg-[#0D9488] hover:bg-[#0f766e] active:bg-[#115e59] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0D9488] disabled:opacity-50 transition duration-150"
                            >
                                <span v-if="form.processing">Saving...</span>
                                <span v-else>Save Settings</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
