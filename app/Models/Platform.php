<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
    ];

    public function platformAccounts(): HasMany
    {
        return $this->hasMany(PlatformAccount::class, 'platform_id');
    }
    public function aiPostPrompts(): BelongsTo
    {
        return $this->belongsTo(AiPostPrompt::class);
    }

    
}