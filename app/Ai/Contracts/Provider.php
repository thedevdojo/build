<?php

namespace App\Ai\Contracts;

use App\Ai\ProviderResponse;
use App\Ai\Tools\Tool;

/**
 * A chat-completion provider the build agent can drive.
 *
 * Message arrays follow the Anthropic Messages shape (role + content,
 * where content is a string or an array of typed blocks including
 * tool_use / tool_result); drivers for other vendors adapt internally.
 *
 * To plug in a custom provider, bind an implementation to this interface
 * in a service provider — AiManager prefers a container binding over the
 * configured driver.
 */
interface Provider
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, Tool>  $tools
     */
    public function chat(string $system, array $messages, array $tools = []): ProviderResponse;

    public function name(): string;

    public function supportsTools(): bool;
}
