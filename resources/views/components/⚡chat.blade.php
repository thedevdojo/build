<?php

use App\Ai\Agent;
use App\Ai\AiManager;
use App\Models\Conversation;
use App\Models\Memory;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $conversationId = 0;

    public string $draft = '';

    public bool $awaitingReply = false;

    public string $engineLabel = 'Preview';

    public bool $engineLive = false;

    public function mount(AiManager $ai): void
    {
        $this->engineLabel = $ai->label();
        $this->engineLive = $ai->isLive();

        $existing = Conversation::find(session('builder.conversation_id'));

        $existing ? $this->activate($existing) : $this->startConversation();
    }

    public function send(): void
    {
        $prompt = trim($this->draft);

        if ($prompt === '' || $this->awaitingReply) {
            return;
        }

        $conversation = $this->conversation();
        $conversation->messages()->create(['role' => 'user', 'content' => $prompt]);
        $conversation->touch();

        $this->draft = '';
        $this->awaitingReply = true;

        // Render the user message + thinking state first, then run the
        // agent in a follow-up request so activity can stream in live.
        $this->js('$wire.reply()');
    }

    public function reply(Agent $agent): void
    {
        if (! $this->awaitingReply) {
            return;
        }

        set_time_limit(0);

        try {
            $message = $agent->respond($this->conversation(), function (string $activity): void {
                $this->stream(to: 'activity', content: e($activity), replace: true);
            });

            if ($message->meta['mutated'] ?? false) {
                $this->dispatch('app-updated');
            }
        } catch (\Throwable $exception) {
            report($exception);

            $this->conversation()->messages()->create([
                'role' => 'assistant',
                'content' => 'Something went wrong while I was working: '.$exception->getMessage()."\n\nYour app is untouched beyond the steps shown above — try sending that again.",
                'meta' => ['error' => true],
            ]);
        } finally {
            $this->awaitingReply = false;
        }
    }

    public function suggest(string $prompt): void
    {
        $this->draft = $prompt;
    }

    public function startConversation(): void
    {
        // Reuse the current conversation instead of stacking empty ones.
        if ($this->conversationId && $this->conversation()->messages()->doesntExist()) {
            return;
        }

        $this->activate(Conversation::create());
    }

    public function openConversation(int $id): void
    {
        if ($conversation = Conversation::find($id)) {
            $this->activate($conversation);
        }
    }

    public function deleteConversation(int $id): void
    {
        Conversation::destroy($id);

        if ($id === $this->conversationId) {
            $this->conversationId = 0;
            $this->startConversation();
        }
    }

    public function forgetMemory(int $id): void
    {
        Memory::destroy($id);
    }

    #[Computed]
    public function messages()
    {
        return $this->conversation()->messages()->orderBy('id')->get();
    }

    #[Computed]
    public function conversations()
    {
        return Conversation::query()->latest('updated_at')->limit(15)->get();
    }

    #[Computed]
    public function memories()
    {
        return Memory::query()->latest()->get();
    }

    #[Computed]
    public function activeTitle(): string
    {
        return $this->conversation()->title ?? 'New chat';
    }

    protected function conversation(): Conversation
    {
        return Conversation::findOrFail($this->conversationId);
    }

    protected function activate(Conversation $conversation): void
    {
        $this->conversationId = $conversation->id;
        $this->awaitingReply = false;

        session(['builder.conversation_id' => $conversation->id]);
    }
};
?>

<div class="flex h-full min-h-0 flex-col">

    @php
        $toolIcons = [
            'list_files' => 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z',
            'read_file' => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25',
            'write_file' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3h-6M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            'edit_file' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z',
            'delete_file' => 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0',
            'search_files' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
            'run_command' => 'M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z',
            'save_memory' => 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z',
            'forget_memory' => 'M3 3l18 18M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185v9.243M4.5 8.25V21L12 17.25 15.75 19.125',
        ];
    @endphp

    {{-- Sidebar header --}}
    <div class="flex items-center justify-between gap-2 px-4 pt-3 pb-1.5">
        <p class="min-w-0 truncate text-xs font-medium text-neutral-400" title="{{ $this->activeTitle }}">
            {{ $this->activeTitle }}
        </p>

        <div class="flex shrink-0 items-center gap-0.5">
            {{-- Memory panel --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = ! open" title="What I remember about this app" class="relative flex size-7 items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-white/[0.06] hover:text-neutral-300">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    @if ($this->memories->isNotEmpty())
                        <span class="absolute top-0.5 right-0.5 size-1.5 rounded-full bg-violet-400"></span>
                    @endif
                </button>

                <div x-cloak x-show="open" x-transition.origin.top.right.duration.150ms class="absolute top-8 right-0 z-50 max-h-96 w-80 overflow-y-auto rounded-xl border border-white/[0.09] bg-[#141414] p-1.5 shadow-2xl shadow-black/60">
                    <p class="px-2.5 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">Memory</p>
                    <p class="px-2.5 pb-2 text-xs leading-relaxed text-neutral-500">Things I've learned about your app. I save these as we chat and use them in every conversation.</p>

                    @forelse ($this->memories as $memory)
                        <div class="group flex items-start gap-2 rounded-lg px-2.5 py-2 hover:bg-white/[0.04]" wire:key="memory-{{ $memory->id }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate font-mono text-[11px] text-neutral-300">{{ $memory->slug }}</span>
                                    <span class="shrink-0 rounded bg-white/[0.06] px-1 py-px text-[9px] text-neutral-500 uppercase">{{ $memory->kind }}</span>
                                </div>
                                <p class="mt-0.5 text-xs leading-relaxed text-neutral-400">{{ $memory->content }}</p>
                            </div>
                            <button type="button" wire:click="forgetMemory({{ $memory->id }})" title="Forget" class="mt-0.5 shrink-0 rounded p-1 text-neutral-600 opacity-0 transition-opacity group-hover:opacity-100 hover:text-neutral-300">
                                <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    @empty
                        <p class="px-2.5 pb-2.5 text-xs text-neutral-600">Nothing yet — tell me about your product and I'll start remembering.</p>
                    @endforelse
                </div>
            </div>

            {{-- Conversation history --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = ! open" title="Conversation history" class="flex size-7 items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-white/[0.06] hover:text-neutral-300">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </button>

                <div x-cloak x-show="open" x-transition.origin.top.right.duration.150ms class="absolute top-8 right-0 z-50 max-h-96 w-72 overflow-y-auto rounded-xl border border-white/[0.09] bg-[#141414] p-1.5 shadow-2xl shadow-black/60">
                    <p class="px-2.5 pt-2 pb-1.5 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase">Conversations</p>

                    @forelse ($this->conversations as $item)
                        <div class="group flex items-center gap-1 rounded-lg pr-1 {{ $item->id === $conversationId ? 'bg-white/[0.06]' : 'hover:bg-white/[0.04]' }}" wire:key="conversation-{{ $item->id }}">
                            <button type="button" wire:click="openConversation({{ $item->id }})" @click="open = false" class="min-w-0 flex-1 px-2.5 py-2 text-left">
                                <span class="block truncate text-[13px] text-neutral-300">{{ $item->title ?? 'New chat' }}</span>
                                <span class="block text-[10px] text-neutral-600">{{ $item->updated_at->diffForHumans() }}</span>
                            </button>
                            <button type="button" wire:click="deleteConversation({{ $item->id }})" wire:confirm="Delete this conversation?" title="Delete" class="shrink-0 rounded p-1 text-neutral-600 opacity-0 transition-opacity group-hover:opacity-100 hover:text-neutral-300">
                                <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    @empty
                        <p class="px-2.5 pb-2.5 text-xs text-neutral-600">No conversations yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- New chat --}}
            <button type="button" wire:click="startConversation" title="New chat" class="flex size-7 items-center justify-center rounded-md text-neutral-500 transition-colors hover:bg-white/[0.06] hover:text-neutral-300">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14m-7-7h14" /></svg>
            </button>
        </div>
    </div>

    {{-- Message stream --}}
    <div
        class="chat-scroll min-h-0 flex-1 overflow-y-auto px-4 pb-4"
        x-data
        x-init="const pin = () => $el.scrollTo({ top: $el.scrollHeight }); pin(); new MutationObserver(pin).observe($el, { childList: true, subtree: true })"
    >
        {{-- Project init card --}}
        <div class="rounded-xl border border-white/[0.07] bg-white/[0.03] px-3.5 py-3">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-neutral-200">Initialized project</p>
                <span class="rounded-md bg-white/[0.06] px-1.5 py-0.5 font-mono text-[10px] text-neutral-400">v1</span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-neutral-500">Loaded the DevDojo starter — authentication, billing, profiles, and notifications are on and ready.</p>
        </div>

        {{-- Assistant intro --}}
        <div class="mt-5 flex gap-2.5">
            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/25 to-sky-500/25 ring-1 ring-white/10">
                <svg class="size-3 text-violet-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
            </span>
            <div class="min-w-0 space-y-1.5">
                <p class="text-sm leading-relaxed text-neutral-300">Welcome to your builder. Your app is running live in the canvas — tell me what you want to create and I'll build it with you. I remember what we decide, so we can pick up where we left off anytime.</p>
                @if ($this->messages->isEmpty())
                    <p class="text-[13px] text-neutral-500">Or start from one of these:</p>
                @endif
            </div>
        </div>

        {{-- Suggestions --}}
        @if ($this->messages->isEmpty())
            <div class="mt-2.5 flex flex-col items-start gap-1.5 pl-[34px]">
                @foreach ([
                    'Design a landing page for my product',
                    'Add a pricing page with three tiers',
                    'Rebrand — new logo, colors, and copy',
                ] as $suggestion)
                    <button
                        type="button"
                        wire:click="suggest('{{ addslashes($suggestion) }}')"
                        class="rounded-full border border-white/[0.08] bg-white/[0.03] px-3 py-1.5 text-left text-xs text-neutral-400 transition-colors hover:border-white/[0.16] hover:text-neutral-200"
                    >
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Conversation --}}
        @foreach ($this->messages as $message)
            @if ($message->role === 'user')
                <div class="mt-5 flex justify-end" wire:key="message-{{ $message->id }}">
                    <div class="max-w-[85%] rounded-2xl rounded-br-md border border-white/[0.05] bg-white/[0.07] px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-line text-neutral-200">{{ $message->content }}</div>
                </div>
            @else
                <div class="mt-5 flex gap-2.5" wire:key="message-{{ $message->id }}">
                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/25 to-sky-500/25 ring-1 ring-white/10">
                        <svg class="size-3 text-violet-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        {{-- What the agent did --}}
                        @if (filled($message->actions))
                            <div class="mb-2 flex flex-col gap-1">
                                @foreach ($message->actions as $action)
                                    <div class="flex items-center gap-2 rounded-lg border border-white/[0.06] bg-white/[0.02] px-2.5 py-1.5" wire:key="message-{{ $message->id }}-action-{{ $loop->index }}">
                                        <svg class="size-3.5 shrink-0 {{ ($action['status'] ?? 'ok') === 'ok' ? 'text-neutral-500' : 'text-rose-400' }}" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $toolIcons[$action['tool'] ?? ''] ?? 'M4.5 12.75l6 6 9-13.5' }}" />
                                        </svg>
                                        <span class="min-w-0 truncate font-mono text-[11px] {{ ($action['status'] ?? 'ok') === 'ok' ? 'text-neutral-400' : 'text-rose-400' }}">{{ $action['summary'] ?? $action['tool'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="text-sm leading-relaxed whitespace-pre-line {{ ($message->meta['error'] ?? false) ? 'text-rose-300' : 'text-neutral-300' }}">{{ $message->content }}</div>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Working state: live activity streamed from the agent loop --}}
        @if ($awaitingReply)
            <div class="mt-5 flex gap-2.5" wire:key="thinking">
                <span class="mt-0.5 flex size-6 shrink-0 animate-pulse items-center justify-center rounded-lg bg-gradient-to-br from-violet-500/25 to-sky-500/25 ring-1 ring-white/10">
                    <svg class="size-3 text-violet-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
                </span>
                <div class="flex min-w-0 items-center gap-2 pt-1">
                    <span class="flex gap-1" aria-hidden="true">
                        <span class="size-1 animate-bounce rounded-full bg-neutral-500 [animation-delay:0ms]"></span>
                        <span class="size-1 animate-bounce rounded-full bg-neutral-500 [animation-delay:120ms]"></span>
                        <span class="size-1 animate-bounce rounded-full bg-neutral-500 [animation-delay:240ms]"></span>
                    </span>
                    <span wire:stream.replace="activity" class="truncate font-mono text-[11px] text-neutral-500">Thinking…</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Composer --}}
    <div class="px-3 pb-3">
        <form wire:submit="send" class="rounded-2xl border border-white/[0.08] bg-white/[0.04] transition-colors focus-within:border-white/[0.18]">
            <textarea
                wire:model="draft"
                rows="1"
                placeholder="Describe what to build…"
                aria-label="Message the builder"
                x-data
                @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }"
                @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                class="max-h-40 w-full resize-none bg-transparent px-3.5 pt-3 pb-1 text-sm leading-relaxed text-neutral-200 placeholder:text-neutral-500 focus:outline-none"
            ></textarea>

            <div class="flex items-center gap-1 px-2 pb-2">
                <button type="button" disabled title="Attachments — coming soon" class="flex size-7 cursor-not-allowed items-center justify-center rounded-lg text-neutral-600" aria-label="Add attachment (coming soon)">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14m-7-7h14" /></svg>
                </button>

                <span class="flex h-7 items-center gap-1.5 rounded-lg px-2 text-xs font-medium {{ $engineLive ? 'text-neutral-300' : 'text-neutral-500' }}" title="{{ $engineLive ? 'Connected to '.$engineLabel : 'Add ANTHROPIC_API_KEY to go live' }}">
                    <span class="size-1.5 rounded-full {{ $engineLive ? 'bg-emerald-400' : 'bg-neutral-600' }}"></span>
                    {{ $engineLabel }}
                </span>

                <span class="flex-1"></span>

                <button
                    type="submit"
                    @disabled($awaitingReply)
                    wire:loading.attr="disabled"
                    class="flex size-7 items-center justify-center rounded-lg bg-white text-neutral-900 transition-colors hover:bg-neutral-200 disabled:opacity-40"
                    aria-label="Send message"
                >
                    <svg wire:loading.remove class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-6 6l6-6 6 6" /></svg>
                    <svg wire:loading class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" /></svg>
                </button>
            </div>
        </form>

        <p class="mt-2 text-center text-[10px] text-neutral-600">
            @if ($engineLive)
                {{ $engineLabel }} edits your app directly — changes appear in the canvas as it works.
            @else
                Preview mode — add <span class="font-mono">ANTHROPIC_API_KEY</span> to the builder's .env to go live.
            @endif
        </p>
    </div>
</div>
