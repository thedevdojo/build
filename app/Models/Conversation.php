<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = ['title', 'summary', 'summarized_up_to'];

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Messages that have not been folded into the rolling summary yet —
     * the part of the transcript sent verbatim to the model.
     *
     * @return HasMany<Message, $this>
     */
    public function unsummarizedMessages(): HasMany
    {
        return $this->messages()
            ->when($this->summarized_up_to, fn ($query) => $query->where('id', '>', $this->summarized_up_to))
            ->orderBy('id');
    }
}
