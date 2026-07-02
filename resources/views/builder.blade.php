<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('platform.app_name') }} — DevDojo Builder</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="builder({ appUrl: @js(rtrim(config('platform.app_url'), '/')), projectName: @js(config('platform.app_name')) })"
    @app-updated.window="reload()"
    class="h-dvh overflow-hidden bg-[#0a0a0a] font-sans text-neutral-300 antialiased selection:bg-white/20"
>

    {{-- ================================================================ Top bar --}}
    <header class="relative z-40 flex h-12 items-center gap-3 border-b border-white/[0.07] bg-[#0c0c0c] px-3">

        {{-- Left: identity + project + connection --}}
        <div class="flex w-[368px] shrink-0 items-center gap-2.5">
            <a href="{{ route('home') }}" class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500 to-sky-500 shadow-[0_0_14px_rgba(124,58,237,0.35)]" aria-label="DevDojo Builder">
                <svg class="size-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 3L4 14h7l-1 7 9-11h-7l1-7z" /></svg>
            </a>

            {{-- Project switcher --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = ! open; if (open) fetchTarget()" class="flex h-8 items-center gap-1.5 rounded-lg px-2 text-[13px] font-medium text-neutral-200 transition-colors hover:bg-white/[0.06]">
                    <span x-text="projectName"></span>
                    <span class="rounded-md bg-white/[0.07] px-1.5 py-px font-mono text-[10px] text-neutral-400" x-text="target?.environment ?? 'app'"></span>
                    <svg class="size-3.5 text-neutral-500 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" /></svg>
                </button>

                <div x-cloak x-show="open" x-transition.origin.top.left.duration.150ms class="absolute top-10 left-0 w-72 rounded-xl border border-white/[0.09] bg-[#141414] p-1.5 shadow-2xl shadow-black/60">
                    <div class="px-2.5 pt-2 pb-2.5">
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] font-medium text-neutral-200" x-text="projectName"></p>
                            <template x-if="target?.reachable">
                                <span class="inline-flex items-center gap-1.5 text-[11px] text-emerald-400">
                                    <span class="size-1.5 rounded-full bg-emerald-400"></span> Online
                                </span>
                            </template>
                            <template x-if="target && ! target.reachable">
                                <span class="inline-flex items-center gap-1.5 text-[11px] text-neutral-500">
                                    <span class="size-1.5 rounded-full bg-neutral-600"></span> Unreachable
                                </span>
                            </template>
                        </div>
                        <p class="mt-0.5 font-mono text-[11px] text-neutral-500" x-text="appHost"></p>
                    </div>

                    <template x-if="target?.reachable">
                        <div class="border-t border-white/[0.06] px-2.5 py-2.5">
                            <div class="flex gap-4 font-mono text-[11px] text-neutral-500">
                                <span>Laravel <span class="text-neutral-300" x-text="target.laravel"></span></span>
                                <span>PHP <span class="text-neutral-300" x-text="target.php"></span></span>
                            </div>
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                <template x-for="(enabled, feature) in target.features ?? {}" :key="feature">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-white/[0.05] px-1.5 py-0.5 text-[10px] capitalize" :class="enabled ? 'text-neutral-300' : 'text-neutral-600 line-through'">
                                        <span class="size-1 rounded-full" :class="enabled ? 'bg-emerald-400' : 'bg-neutral-600'"></span>
                                        <span x-text="feature"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="border-t border-white/[0.06] p-1">
                        <a :href="appUrl" target="_blank" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-[13px] text-neutral-300 transition-colors hover:bg-white/[0.06]">
                            Open app
                            <svg class="size-3.5 text-neutral-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18v4.5M18 6l-7.5 7.5M10 6H7a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3" /></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Bridge connection state --}}
            <div class="flex items-center gap-1.5 rounded-full border border-white/[0.06] bg-white/[0.03] py-1 pr-2.5 pl-2 text-[11px] text-neutral-500">
                <span class="relative flex size-1.5">
                    <span x-show="status === 'connecting'" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-60"></span>
                    <span class="relative inline-flex size-1.5 rounded-full" :class="{ 'bg-emerald-400': status === 'bridge', 'bg-amber-400': status === 'connecting', 'bg-neutral-500': status === 'preview' }"></span>
                </span>
                <span x-text="statusLabel"></span>
            </div>
        </div>

        {{-- Center: navigation + URL + devices --}}
        <div class="flex min-w-0 flex-1 items-center justify-center gap-2">
            <div class="flex items-center gap-0.5">
                <button type="button" @click="back()" :disabled="! canBack" class="flex size-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-white/[0.06] hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-30" aria-label="Back">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button type="button" @click="forward()" :disabled="! canForward" class="flex size-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-white/[0.06] hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-30" aria-label="Forward">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </button>
                <button type="button" @click="reload()" class="flex size-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-white/[0.06] hover:text-neutral-200" aria-label="Reload preview">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0113.15-4.95M19.5 12a7.5 7.5 0 01-13.15 4.95M17.65 3v4.05H13.6M6.35 21v-4.05h4.05" /></svg>
                </button>
            </div>

            <div class="flex h-8 w-full max-w-sm items-center gap-1.5 rounded-lg border border-white/[0.07] bg-white/[0.04] px-2.5 font-mono text-xs transition-colors focus-within:border-white/[0.18]">
                <svg class="size-3 shrink-0 text-neutral-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2" /><path stroke-linecap="round" d="M8 11V8a4 4 0 018 0" /></svg>
                <span class="shrink-0 text-neutral-500" x-text="appHost"></span>
                <input
                    type="text"
                    x-model="pathDraft"
                    @keydown.enter="commitPath()"
                    @focus="$el.select()"
                    spellcheck="false"
                    autocomplete="off"
                    aria-label="Preview path"
                    class="w-full min-w-0 flex-1 bg-transparent text-neutral-200 placeholder:text-neutral-600 focus:outline-none"
                    placeholder="/"
                >
            </div>

            <button type="button" @click="openExternal()" class="flex size-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-white/[0.06] hover:text-neutral-200" aria-label="Open in new tab">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18v4.5M18 6l-7.5 7.5M10 6H7a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3" /></svg>
            </button>

            <div class="ml-1 flex h-8 items-center gap-0.5 rounded-lg border border-white/[0.06] bg-white/[0.04] p-0.5">
                <button type="button" @click="setDevice('desktop')" :class="device === 'desktop' ? 'bg-white/[0.1] text-neutral-100' : 'text-neutral-500 hover:text-neutral-300'" class="flex h-7 w-8 items-center justify-center rounded-md transition-colors" aria-label="Desktop preview">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" /><path stroke-linecap="round" d="M8 20h8m-4-4v4" /></svg>
                </button>
                <button type="button" @click="setDevice('tablet')" :class="device === 'tablet' ? 'bg-white/[0.1] text-neutral-100' : 'text-neutral-500 hover:text-neutral-300'" class="flex h-7 w-8 items-center justify-center rounded-md transition-colors" aria-label="Tablet preview">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2" /><path stroke-linecap="round" d="M11 17.5h2" /></svg>
                </button>
                <button type="button" @click="setDevice('phone')" :class="device === 'phone' ? 'bg-white/[0.1] text-neutral-100' : 'text-neutral-500 hover:text-neutral-300'" class="flex h-7 w-8 items-center justify-center rounded-md transition-colors" aria-label="Phone preview">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3" width="10" height="18" rx="2" /><path stroke-linecap="round" d="M11 17.5h2" /></svg>
                </button>
            </div>
        </div>

        {{-- Right: preview theme, share, publish --}}
        <div class="flex shrink-0 items-center gap-1.5">
            <button type="button" @click="togglePreviewTheme()" class="flex size-8 items-center justify-center rounded-lg text-neutral-400 transition-colors hover:bg-white/[0.06] hover:text-neutral-200" :aria-label="previewTheme === 'dark' ? 'Switch preview to light mode' : 'Switch preview to dark mode'" :title="'Preview theme: ' + previewTheme">
                <svg x-show="previewTheme !== 'dark'" class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" /></svg>
                <svg x-cloak x-show="previewTheme === 'dark'" class="size-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32l1.41-1.41" /></svg>
            </button>

            <div class="mx-1 h-5 w-px bg-white/[0.08]"></div>

            <button type="button" @click="share()" class="flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium text-neutral-300 transition-colors hover:bg-white/[0.06]">
                <svg x-show="! copied" class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                <svg x-cloak x-show="copied" class="size-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                <span x-text="copied ? 'Copied' : 'Share'"></span>
            </button>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = ! open" class="flex h-8 items-center gap-1.5 rounded-lg bg-white px-3.5 text-[13px] font-semibold text-neutral-900 shadow-[0_0_16px_rgba(255,255,255,0.12)] transition-colors hover:bg-neutral-200">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18" /></svg>
                    Publish
                </button>

                <div x-cloak x-show="open" x-transition.origin.top.right.duration.150ms class="absolute top-10 right-0 w-72 rounded-xl border border-white/[0.09] bg-[#141414] p-4 shadow-2xl shadow-black/60">
                    <div class="flex items-center gap-2">
                        <span class="relative flex size-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <p class="text-[13px] font-medium text-neutral-200">Your app is live</p>
                    </div>
                    <p class="mt-1.5 font-mono text-xs text-neutral-500" x-text="appHost"></p>
                    <p class="mt-3 text-xs leading-relaxed text-neutral-500">Changes you make here go straight to your app — there's no separate deploy step while you're building.</p>
                    <a :href="appUrl" target="_blank" class="mt-3.5 flex h-8 items-center justify-center gap-1.5 rounded-lg bg-white/[0.07] text-[13px] font-medium text-neutral-200 transition-colors hover:bg-white/[0.12]">
                        Visit site
                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18v4.5M18 6l-7.5 7.5" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    {{-- ================================================================ Workspace --}}
    <div class="flex h-[calc(100dvh-3rem)]">

        {{-- Chat sidebar --}}
        <aside class="flex w-[380px] shrink-0 flex-col border-r border-white/[0.07] bg-[#0c0c0c]">
            <livewire:chat />
        </aside>

        {{-- Canvas --}}
        <main class="min-w-0 flex-1 p-3">
            <div class="relative flex h-full justify-center overflow-hidden rounded-xl border border-white/[0.08] bg-[#111111] p-2">
                <div class="h-full transition-[width] duration-300 ease-out" :style="{ width: deviceWidth }">
                    <iframe
                        x-ref="canvas"
                        :src="appUrl"
                        @load="onCanvasLoad()"
                        title="Live preview of {{ config('platform.app_name') }}"
                        class="h-full w-full rounded-lg bg-white shadow-2xl shadow-black/50"
                    ></iframe>
                </div>

                {{-- Boot overlay --}}
                <div x-show="! loaded" x-transition:leave="transition-opacity duration-500" x-transition:leave-end="opacity-0" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 rounded-xl bg-[#111111]">
                    <div class="size-8 animate-spin rounded-full border-2 border-white/10 border-t-white/60"></div>
                    <p class="font-mono text-xs text-neutral-500">Starting <span class="text-neutral-300" x-text="appHost"></span>…</p>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
