<?php

namespace Tests\Fakes;

use App\Ai\Contracts\Provider;
use App\Ai\ProviderResponse;

class FakeProvider implements Provider
{
    /** @var array<int, string> */
    public array $capturedSystems = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $capturedMessages = [];

    /**
     * @param  array<int, ProviderResponse>  $responses  Returned in order; repeats the last one when exhausted.
     */
    public function __construct(protected array $responses = []) {}

    public function chat(string $system, array $messages, array $tools = []): ProviderResponse
    {
        $this->capturedSystems[] = $system;
        $this->capturedMessages[] = $messages;

        return array_shift($this->responses) ?? new ProviderResponse(text: 'All done.');
    }

    public function name(): string
    {
        return 'fake';
    }

    public function supportsTools(): bool
    {
        return true;
    }
}
