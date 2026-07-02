<?php

namespace App\Ai;

use App\Ai\Contracts\Provider;
use App\Ai\Tools\DeleteFile;
use App\Ai\Tools\EditFile;
use App\Ai\Tools\ForgetMemory;
use App\Ai\Tools\ListFiles;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\RunCommand;
use App\Ai\Tools\SaveMemory;
use App\Ai\Tools\SearchFiles;
use App\Ai\Tools\Tool;
use App\Ai\Tools\WriteFile;
use App\Models\Conversation;
use App\Models\Message;
use Closure;
use Illuminate\Support\Str;
use Throwable;

class Agent
{
    /** @var array<string, Tool>|null */
    protected ?array $toolbox = null;

    public function __construct(
        protected AiManager $ai,
        protected Workspace $workspace,
        protected SystemPrompt $systemPrompt,
    ) {}

    /**
     * Answer the conversation's latest user message: run the provider in
     * a tool-use loop against the workspace, record every action, persist
     * the assistant's reply, and keep the title and rolling summary fresh.
     *
     * @param  Closure(string): void|null  $onActivity  Called with a human label as each tool runs (streamed to the UI).
     */
    public function respond(Conversation $conversation, ?Closure $onActivity = null): Message
    {
        $notify = $onActivity ?? fn (string $activity) => null;
        $provider = $this->ai->provider();
        $tools = $provider->supportsTools() ? $this->tools() : [];

        // Preview mode ignores the prompt, so skip the tree walk + status
        // fetch that building it costs.
        $system = $provider->supportsTools()
            ? $this->systemPrompt->build($this->workspace, $conversation)
            : '';

        $transcript = $this->transcript($conversation);

        $actions = [];
        $mutated = false;
        $finalText = '';
        $steps = 0;
        $maxSteps = max(1, (int) config('builder.max_steps', 16));

        while ($steps < $maxSteps) {
            $steps++;

            $response = $provider->chat($system, $transcript, $tools);

            if ($response->text !== '') {
                $finalText = $response->text;
            }

            if (! $response->wantsTools()) {
                break;
            }

            $transcript[] = ['role' => 'assistant', 'content' => $response->contentBlocks()];
            $transcript[] = ['role' => 'user', 'content' => $this->executeToolCalls($response->toolCalls, $notify, $actions, $mutated)];
        }

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $finalText !== '' ? $finalText : 'Done — tell me what you’d like next.',
            'actions' => $actions,
            'meta' => ['provider' => $provider->name(), 'mutated' => $mutated, 'steps' => $steps],
        ]);

        $this->refreshTitle($conversation);
        $this->foldSummary($conversation, $provider);

        return $message;
    }

    /**
     * Execute one round of tool calls and shape the results into
     * tool_result blocks for the next provider turn.
     *
     * @param  array<int, ToolCall>  $toolCalls
     * @param  Closure(string): void  $notify
     * @param  array<int, array<string, string>>  $actions
     * @return array<int, array<string, mixed>>
     */
    protected function executeToolCalls(array $toolCalls, Closure $notify, array &$actions, bool &$mutated): array
    {
        $results = [];

        foreach ($toolCalls as $call) {
            $tool = $this->tools()[$call->name] ?? null;
            $summary = $tool?->summarize($call->input) ?? $call->name;

            $notify($summary);

            try {
                $output = $tool?->run($call->input) ?? "Unknown tool: {$call->name}.";
                $failed = $tool === null;
            } catch (WorkspaceException $exception) {
                $output = $exception->getMessage();
                $failed = true;
            } catch (Throwable $exception) {
                $output = 'The tool failed: '.$exception->getMessage();
                $failed = true;
            }

            if (! $failed && $tool?->mutatesWorkspace()) {
                $mutated = true;
            }

            $actions[] = ['tool' => $call->name, 'summary' => $summary, 'status' => $failed ? 'error' : 'ok'];

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $call->id,
                'content' => $output,
                'is_error' => $failed,
            ];
        }

        return $results;
    }

    /**
     * The verbatim tail of the conversation — everything after the rolling
     * summary cut-off, as plain role/content turns.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function transcript(Conversation $conversation): array
    {
        return $conversation->unsummarizedMessages()
            ->get()
            ->map(fn (Message $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    /**
     * @return array<string, Tool>
     */
    protected function tools(): array
    {
        return $this->toolbox ??= collect([
            new ListFiles($this->workspace),
            new ReadFile($this->workspace),
            new WriteFile($this->workspace),
            new EditFile($this->workspace),
            new DeleteFile($this->workspace),
            new SearchFiles($this->workspace),
            new RunCommand($this->workspace),
            new SaveMemory,
            new ForgetMemory,
        ])->keyBy(fn (Tool $tool): string => $tool->name())->all();
    }

    protected function refreshTitle(Conversation $conversation): void
    {
        if ($conversation->title !== null) {
            return;
        }

        $firstPrompt = $conversation->messages()->where('role', 'user')->orderBy('id')->value('content');

        if ($firstPrompt !== null) {
            $conversation->update(['title' => Str::limit(trim($firstPrompt), 60)]);
        }
    }

    /**
     * Fold older turns into the rolling summary so long conversations
     * never overflow the context window. Best-effort: a failed summary
     * must never break a reply.
     */
    protected function foldSummary(Conversation $conversation, Provider $provider): void
    {
        if (! $provider->supportsTools()) {
            return;
        }

        $foldAfter = (int) config('builder.summary.fold_after', 24);
        $keepRecent = (int) config('builder.summary.keep_recent', 12);

        $pending = $conversation->unsummarizedMessages()->get();

        if ($pending->count() <= $foldAfter) {
            return;
        }

        $fold = $pending->slice(0, $pending->count() - $keepRecent);

        $turns = $fold
            ->map(fn (Message $message): string => strtoupper($message->role).': '.$message->content)
            ->implode("\n\n");

        try {
            $response = $provider->chat(
                'You maintain the running summary of a conversation between a person and the AI that is building their Laravel app. '
                .'Merge the existing summary with the new turns. Keep decisions, file and page names, branding, preferences, and unresolved threads. '
                .'At most 300 words. Reply with the summary only.',
                [[
                    'role' => 'user',
                    'content' => "Existing summary:\n".($conversation->summary ?: '(none)')."\n\nNew turns:\n".$turns,
                ]],
            );

            if ($response->text !== '') {
                $conversation->update([
                    'summary' => $response->text,
                    'summarized_up_to' => $fold->last()->id,
                ]);
            }
        } catch (Throwable) {
            // Skip folding this round; the transcript is still intact.
        }
    }
}
