<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

const query = ref('');
const results = ref([]);
const isOpen = ref(false);
const isLoading = ref(false);
const activeIndex = ref(-1);
const searchContainerRef = ref(null);
const searchInputRef = ref(null);

let debounceTimeout = null;
let abortController = null;

// Debounced search trigger
const onInput = () => {
    activeIndex.value = -1;
    const trimmed = query.value.trim();

    if (!trimmed) {
        results.value = [];
        isOpen.value = false;
        isLoading.value = false;
        if (abortController) {
            abortController.abort();
        }
        return;
    }

    isOpen.value = true;
    isLoading.value = true;

    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }

    debounceTimeout = setTimeout(() => {
        performSearch(trimmed);
    }, 150);
};

const performSearch = async (searchTerm) => {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();

    try {
        const searchUrl = typeof route === 'function' 
            ? route('task-boards.search') 
            : '/task-boards/search';
            
        const response = await fetch(`${searchUrl}?q=${encodeURIComponent(searchTerm)}`, {
            signal: abortController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`Search failed: ${response.statusText}`);
        }

        const data = await response.json();
        results.value = Array.isArray(data) ? data : [];
        activeIndex.value = results.value.length > 0 ? 0 : -1;
    } catch (err) {
        if (err.name !== 'AbortError') {
            console.error('Navbar search error:', err);
            results.value = [];
        }
    } finally {
        isLoading.value = false;
    }
};

const selectBoard = (board) => {
    if (!board || !board.id) return;
    isOpen.value = false;
    query.value = '';
    results.value = [];
    
    const targetUrl = typeof route === 'function'
        ? route('task-boards.show', board.id)
        : `/task-boards/${board.id}`;

    router.visit(targetUrl);
};

const onKeydown = (e) => {
    if (!isOpen.value) {
        if (e.key === 'ArrowDown' && results.value.length > 0) {
            isOpen.value = true;
            e.preventDefault();
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (results.value.length > 0) {
            activeIndex.value = (activeIndex.value + 1) % results.value.length;
            scrollToActiveItem();
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (results.value.length > 0) {
            activeIndex.value = (activeIndex.value - 1 + results.value.length) % results.value.length;
            scrollToActiveItem();
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex.value >= 0 && activeIndex.value < results.value.length) {
            selectBoard(results.value[activeIndex.value]);
        } else if (query.value.trim()) {
            // Fallback: navigate to task boards index with search query
            isOpen.value = false;
            const indexUrl = typeof route === 'function' ? route('task-boards.index') : '/task-boards';
            router.get(indexUrl, { search: query.value.trim() });
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        isOpen.value = false;
    }
};

const scrollToActiveItem = () => {
    nextTick(() => {
        const activeEl = searchContainerRef.value?.querySelector(`[data-index="${activeIndex.value}"]`);
        if (activeEl) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }
    });
};

const clearSearch = () => {
    query.value = '';
    results.value = [];
    isOpen.value = false;
    isLoading.value = false;
    searchInputRef.value?.focus();
};

const onFocus = () => {
    if (query.value.trim()) {
        isOpen.value = true;
        if (results.value.length === 0 && !isLoading.value) {
            performSearch(query.value.trim());
        }
    }
};

const handleClickOutside = (e) => {
    if (searchContainerRef.value && !searchContainerRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    if (debounceTimeout) clearTimeout(debounceTimeout);
    if (abortController) abortController.abort();
});

// Helper for highlighting text matches
const highlightText = (text, searchTerm) => {
    if (!text) return '';
    if (!searchTerm || !searchTerm.trim()) return escapeHtml(text);

    const term = searchTerm.trim();
    const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escaped})`, 'gi');

    return escapeHtml(text).replace(regex, '<mark class="bg-teal-100 text-teal-900 font-semibold px-0.5 rounded">$1</mark>');
};

// Helper for snippet extraction with match context
const getSnippet = (desc, searchTerm) => {
    if (!desc) return '';
    if (!searchTerm || !searchTerm.trim()) {
        return desc.length > 90 ? desc.substring(0, 90) + '...' : desc;
    }

    const term = searchTerm.trim().toLowerCase();
    const lowerDesc = desc.toLowerCase();
    const idx = lowerDesc.indexOf(term);

    if (idx === -1) {
        return desc.length > 90 ? desc.substring(0, 90) + '...' : desc;
    }

    const start = Math.max(0, idx - 30);
    const end = Math.min(desc.length, idx + term.length + 50);
    let snippet = desc.substring(start, end);

    if (start > 0) snippet = '...' + snippet;
    if (end < desc.length) snippet = snippet + '...';

    return snippet;
};

const escapeHtml = (unsafe) => {
    return unsafe
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'active':
            return { label: 'Active', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
        case 'completed':
            return { label: 'Completed', class: 'bg-teal-50 text-teal-700 border-teal-200' };
        case 'on_hold':
            return { label: 'On Hold', class: 'bg-amber-50 text-amber-700 border-amber-200' };
        case 'planning':
        default:
            return { label: 'Planning', class: 'bg-slate-100 text-slate-700 border-slate-200' };
    }
};
</script>

<template>
    <div ref="searchContainerRef" class="relative">
        <!-- Search Input Bar -->
        <div class="relative flex items-center">
            <input
                ref="searchInputRef"
                type="text"
                v-model="query"
                @input="onInput"
                @keydown="onKeydown"
                @focus="onFocus"
                placeholder="Search boards by description, workflow..."
                class="text-xs rounded-xl border-slate-200 pl-8 pr-8 py-2 focus:border-[#0D9488] focus:ring-2 focus:ring-[#0D9488]/20 w-full sm:w-56 sm:focus:w-72 md:w-64 md:focus:w-96 bg-slate-50/80 focus:bg-white text-slate-800 placeholder-slate-400 shadow-inner transition-all duration-200 ease-in-out"
                autocomplete="off"
                spellcheck="false"
            />

            <!-- Left Search Icon or Loading Spinner -->
            <div class="absolute left-2.5 flex items-center pointer-events-none text-slate-400">
                <svg v-if="!isLoading" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <svg v-else class="w-3.5 h-3.5 animate-spin text-[#0D9488]" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <!-- Right Clear (X) Button -->
            <button
                v-if="query"
                type="button"
                @click="clearSearch"
                class="absolute right-2.5 p-0.5 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition"
                title="Clear search"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Dynamic Floating Dropdown List -->
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div
                v-if="isOpen && query.trim()"
                class="absolute left-0 mt-2 w-80 sm:w-96 md:w-[420px] bg-white rounded-2xl shadow-2xl border border-slate-200/90 z-50 overflow-hidden divide-y divide-slate-100 backdrop-blur-md"
            >
                <!-- Dropdown Header -->
                <div class="px-4 py-2.5 bg-slate-50/80 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 font-bold text-slate-700">
                        <span class="inline-block w-2 h-2 rounded-full bg-[#0D9488]"></span>
                        <span>Related Task Boards</span>
                    </div>
                    <span v-if="!isLoading" class="text-[11px] font-semibold text-slate-400 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                        {{ results.length }} {{ results.length === 1 ? 'board' : 'boards' }}
                    </span>
                    <span v-else class="text-[11px] text-[#0D9488] font-medium flex items-center gap-1">
                        Searching...
                    </span>
                </div>

                <!-- Dropdown Content: Results List -->
                <div class="max-h-[360px] overflow-y-auto divide-y divide-slate-50 py-1">
                    <!-- Results Available -->
                    <template v-if="results.length > 0">
                        <div
                            v-for="(board, idx) in results"
                            :key="board.id"
                            :data-index="idx"
                            @click="selectBoard(board)"
                            @mouseenter="activeIndex = idx"
                            class="px-4 py-3 cursor-pointer transition flex items-start gap-3 group relative select-none"
                            :class="[
                                activeIndex === idx 
                                    ? 'bg-teal-50/60 border-l-4 border-l-[#0D9488] pl-3' 
                                    : 'hover:bg-slate-50/80'
                            ]"
                        >
                            <!-- Task Board Icon -->
                            <div 
                                class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center transition border shadow-xs"
                                :class="[
                                    activeIndex === idx 
                                        ? 'bg-[#0D9488] text-white border-[#0D9488]' 
                                        : 'bg-slate-100 text-slate-500 border-slate-200/60 group-hover:bg-[#F0FDFA] group-hover:text-[#0D9488] group-hover:border-teal-200'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.375c1.88 0 3.42-1.59 3.42-3.56c0-1.97-1.54-3.56-3.42-3.56H9v7.12z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h.008v.008H3.75V12zm0 3h.008v.008H3.75V15zm0-6h.008v.008H3.75V9zm3-3H6.758v.008H6.75V6zm0 12h.008v.008H6.75V18z" />
                                </svg>
                            </div>

                            <!-- Board Info -->
                            <div class="flex-1 min-w-0">
                                <!-- Board Name & Status Badge -->
                                <div class="flex items-center justify-between gap-2">
                                    <h4 
                                        class="font-bold text-xs text-slate-800 truncate group-hover:text-[#0D9488] transition"
                                        v-html="highlightText(board.name, query)"
                                    ></h4>
                                    
                                    <span 
                                        v-if="board.status" 
                                        :class="['text-[10px] px-1.5 py-0.2 rounded border font-semibold tracking-wider shrink-0 uppercase', getStatusBadge(board.status).class]"
                                    >
                                        {{ getStatusBadge(board.status).label }}
                                    </span>
                                </div>

                                <!-- Workflow Used Badge (DO NOT include Kanban) -->
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="text-slate-400 font-semibold text-[10px] uppercase tracking-wider">Workflow:</span>
                                    <span 
                                        v-if="board.workflow_used"
                                        class="inline-flex items-center px-2 py-0.5 rounded-md font-medium text-xs bg-teal-50 text-teal-800 border border-teal-200/80"
                                        v-html="highlightText(board.workflow_used, query)"
                                    ></span>
                                    <span 
                                        v-else
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-xs text-slate-400 italic"
                                    >
                                        None
                                    </span>

                                    <!-- Matched Workflow Stage pill if applicable -->
                                    <span 
                                        v-if="board.matched_workflow_stage" 
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                    >
                                        <span>Stage:</span>
                                        <span v-html="highlightText(board.matched_workflow_stage, query)"></span>
                                    </span>
                                </div>

                                <!-- Content-Aware Description Snippet -->
                                <p 
                                    v-if="board.description" 
                                    class="text-[11px] text-slate-500 mt-1 line-clamp-2 leading-relaxed"
                                    v-html="highlightText(getSnippet(board.description, query), query)"
                                ></p>

                                <!-- Context Pill / Section Name -->
                                <div class="mt-1.5 flex items-center justify-between text-[10px] text-slate-400">
                                    <span v-if="board.section_name" class="truncate max-w-[200px]">
                                        Section: <strong class="text-slate-600">{{ board.section_name }}</strong>
                                    </span>
                                    <span v-else></span>

                                    <!-- Match reason indicator -->
                                    <span 
                                        v-if="board.matched_fields && board.matched_fields.length > 0" 
                                        class="text-[9px] font-bold uppercase tracking-wider text-[#0D9488]"
                                    >
                                        Matched in {{ board.matched_fields.join(' & ') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Right Arrow Icon -->
                            <div class="shrink-0 self-center text-slate-300 group-hover:text-[#0D9488] group-hover:translate-x-0.5 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State: No boards found -->
                    <div v-else-if="!isLoading" class="py-8 px-4 text-center space-y-2">
                        <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <h5 class="text-xs font-bold text-slate-700">No related task boards found</h5>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto leading-relaxed">
                            No task boards matched "<span class="font-medium text-slate-600">{{ query }}</span>" in description or workflow.
                        </p>
                    </div>

                    <!-- Loading State Skeleton -->
                    <div v-else class="py-6 px-4 space-y-3">
                        <div v-for="n in 2" :key="n" class="animate-pulse flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-slate-200"></div>
                            <div class="flex-1 space-y-1.5">
                                <div class="h-3 bg-slate-200 rounded w-1/2"></div>
                                <div class="h-2.5 bg-slate-100 rounded w-3/4"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dropdown Footer with keyboard hints -->
                <div class="px-4 py-2 bg-slate-50/70 flex items-center justify-between text-[10px] text-slate-400">
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px] shadow-2xs">↵</kbd>
                        <span>to open</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px] shadow-2xs">↑</kbd>
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px] shadow-2xs">↓</kbd>
                        <span>to navigate</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded font-mono text-[9px] shadow-2xs">ESC</kbd>
                        <span>to close</span>
                    </span>
                </div>
            </div>
        </transition>
    </div>
</template>
