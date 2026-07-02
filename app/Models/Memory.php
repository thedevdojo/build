<?php

namespace App\Models;

use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'kind', 'content'];

    /**
     * Render every memory as a block for the system prompt, oldest first,
     * capped so a large memory bank can't crowd out the conversation.
     */
    public static function toPromptBlock(int $maxChars = 6000): string
    {
        $block = '';

        foreach (static::query()->orderBy('id')->get() as $memory) {
            $entry = "- [{$memory->kind}] {$memory->slug}: {$memory->content}\n";

            if (strlen($block) + strlen($entry) > $maxChars) {
                break;
            }

            $block .= $entry;
        }

        return rtrim($block);
    }
}
